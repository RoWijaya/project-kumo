<?php
declare(strict_types=1);

require_once __DIR__ . '/security.php';

function kumo_smtp_config(): array {
    $config = [];
    $path = __DIR__ . '/config.local.php';
    if (is_file($path)) {
        $loaded = require $path;
        if (is_array($loaded)) $config = $loaded;
    }
    $smtp = is_array($config['smtp'] ?? null) ? $config['smtp'] : [];
    $env = static function (string $key, string $fallback = ''): string {
        $value = getenv($key);
        return $value === false ? $fallback : (string)$value;
    };
    return [
        'host' => trim($env('KUMO_SMTP_HOST', (string)($smtp['host'] ?? ''))),
        'port' => (int)$env('KUMO_SMTP_PORT', (string)($smtp['port'] ?? 587)),
        'username' => $env('KUMO_SMTP_USERNAME', (string)($smtp['username'] ?? '')),
        'password' => $env('KUMO_SMTP_PASSWORD', (string)($smtp['password'] ?? '')),
        'encryption' => strtolower(trim($env('KUMO_SMTP_ENCRYPTION', (string)($smtp['encryption'] ?? 'tls')))),
        'from' => trim($env('KUMO_MAIL_FROM', (string)($config['mail_from'] ?? ''))),
    ];
}

/** Send a small HTML message through authenticated SMTP (STARTTLS 587 or implicit TLS 465). */
function kumo_smtp_send(string $to, string $subject, string $html): bool {
    $cfg = kumo_smtp_config();
    if (!filter_var($to, FILTER_VALIDATE_EMAIL) || !filter_var($cfg['from'], FILTER_VALIDATE_EMAIL)
        || $cfg['host'] === '' || $cfg['username'] === '' || $cfg['password'] === ''
        || !in_array($cfg['encryption'], ['tls', 'ssl'], true)
        || ($cfg['encryption'] === 'ssl' && $cfg['port'] !== 465)
        || ($cfg['encryption'] === 'tls' && $cfg['port'] !== 587)) {
        error_log('Kumo SMTP is not configured correctly.');
        return false;
    }

    $target = ($cfg['encryption'] === 'ssl' ? 'ssl://' : '') . $cfg['host'] . ':' . $cfg['port'];
    $errno = 0; $errstr = '';
    $socket = @stream_socket_client($target, $errno, $errstr, 15, STREAM_CLIENT_CONNECT);
    if (!is_resource($socket)) {
        error_log('Kumo SMTP connection failed (' . $errno . ').');
        return false;
    }
    stream_set_timeout($socket, 15);
    $read = static function (array $expected) use ($socket): string {
        $response = '';
        do {
            $line = fgets($socket, 515);
            if ($line === false) throw new RuntimeException('SMTP server closed the connection.');
            $response .= $line;
            if (strlen($line) < 4) throw new RuntimeException('Malformed SMTP response.');
        } while ($line[3] === '-');
        $code = (int)substr($line, 0, 3);
        if (!in_array($code, $expected, true)) throw new RuntimeException('SMTP command rejected with code ' . $code . '.');
        return $response;
    };
    $write = static function (string $command) use ($socket): void {
        $data = $command . "\r\n";
        $length = strlen($data);
        $offset = 0;
        while ($offset < $length) {
            $written = fwrite($socket, substr($data, $offset));
            if ($written === false || $written === 0) throw new RuntimeException('SMTP command write failed.');
            $offset += $written;
        }
    };
    $writeMessage = static function (string $message) use ($socket): void {
        $length = strlen($message);
        $offset = 0;
        while ($offset < $length) {
            $written = fwrite($socket, substr($message, $offset));
            if ($written === false || $written === 0) throw new RuntimeException('SMTP message write failed.');
            $offset += $written;
        }
    };
    $stage = 'server greeting';

    try {
        $read([220]);
        $stage = 'EHLO';
        $hostName = (string)($_SERVER['HTTP_HOST'] ?? 'kumo.ct.ws');
        $hostName = preg_replace('/[^A-Za-z0-9.-]/', '', $hostName) ?: 'localhost';
        $write('EHLO ' . $hostName); $read([250]);
        if ($cfg['encryption'] === 'tls') {
            $stage = 'STARTTLS';
            $write('STARTTLS'); $read([220]);
            $crypto = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if ($crypto !== true) throw new RuntimeException('SMTP TLS negotiation failed.');
            $write('EHLO ' . $hostName); $read([250]);
        }
        $stage = 'SMTP authentication';
        $write('AUTH LOGIN'); $read([334]);
        $write(base64_encode($cfg['username'])); $read([334]);
        $write(base64_encode($cfg['password'])); $read([235]);
        $stage = 'sender acceptance';
        $write('MAIL FROM:<' . $cfg['from'] . '>'); $read([250]);
        $stage = 'recipient acceptance';
        $write('RCPT TO:<' . $to . '>'); $read([250, 251]);
        $stage = 'message data negotiation';
        $write('DATA'); $read([354]);
        $safeSubject = preg_replace('/[\r\n]+/', ' ', $subject) ?? 'Kumo email verification';
        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'From: Kumo <' . $cfg['from'] . '>',
            'To: <' . $to . '>',
            'Subject: =?UTF-8?B?' . base64_encode($safeSubject) . '?=',
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            'X-Mailer: Kumo SMTP',
        ];
        $body = chunk_split(base64_encode($html), 76, "\r\n");
        $message = implode("\r\n", $headers) . "\r\n\r\n" . $body;
        $message = preg_replace('/(^|\r\n)\./', '$1..', $message) ?? $message;
        $stage = 'message submission';
        $writeMessage($message . "\r\n.\r\n");
        $read([250]);
        $stage = 'connection close';
        $write('QUIT'); $read([221]);
        fclose($socket);
        return true;
    } catch (Throwable $e) {
        error_log('Kumo SMTP send failed during ' . $stage . ': ' . $e->getMessage());
        if (is_resource($socket)) fclose($socket);
        return false;
    }
}

function kumo_create_email_verification(mysqli $conn, int $userId, string $email): bool {
    $baseUrl = kumo_project_base_url();
    if ($baseUrl === '') return false;
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $conn->begin_transaction();
    try {
        $delete = $conn->prepare('DELETE FROM email_verification_tokens WHERE user_id = ?');
        $delete->bind_param('i', $userId); $delete->execute(); $delete->close();
        $insert = $conn->prepare('INSERT INTO email_verification_tokens (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 30 MINUTE))');
        $insert->bind_param('is', $userId, $tokenHash); $insert->execute(); $insert->close();
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('Kumo verification token persistence failed: ' . $e->getMessage());
        return false;
    }
    $url = $baseUrl . '/assets/backend/verify_email.php?token=' . rawurlencode($token);
    $html = '<!doctype html><html><body style="font-family:Arial,sans-serif;color:#24352b;line-height:1.6"><div style="max-width:560px;margin:32px auto;padding:28px;border:1px solid #e3e9e3;border-radius:18px"><h1 style="margin-top:0">Verify your Kumo email</h1><p>Confirm that this email address belongs to you to activate your Kumo account.</p><p><a href="' . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" style="display:inline-block;background:#355b43;color:#fff;padding:12px 20px;border-radius:10px;text-decoration:none">Verify email address</a></p><p>This link expires in 30 minutes and can only be used once. If you did not request a Kumo account, you can ignore this email.</p></div></body></html>';
    return kumo_smtp_send($email, 'Verify your Kumo email address', $html);
}
