<?php
declare(strict_types=1);

require_once __DIR__ . '/security.php';
require_once __DIR__ . '/google_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    exit('Method not allowed');
}

if (KUMO_GOOGLE_CLIENT_ID === '' || KUMO_GOOGLE_CLIENT_SECRET === '' || KUMO_GOOGLE_REDIRECT_URI === '') {
    header('Location: ../../auth/login.html?error=google_not_configured');
    exit;
}

$state = bin2hex(random_bytes(32));
$_SESSION['google_oauth_state'] = $state;
$_SESSION['google_oauth_started'] = time();

$params = [
    'client_id' => KUMO_GOOGLE_CLIENT_ID,
    'redirect_uri' => KUMO_GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope' => 'openid email profile',
    'state' => $state,
    'prompt' => 'select_account',
];
header('Location: ' . KUMO_GOOGLE_AUTHORIZE_URL . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986));
exit;
