<?php
/**
 * Kumo midterm demo-account seeder.
 *
 * Run ONCE from a terminal:
 *   php assets/backend/seed_demo_accounts.php
 *
 * Put this file temporarily in Kumo/assets/backend/ so it can use db.php,
 * or update the require path below if you put it elsewhere.
 *
 * IMPORTANT: Delete this file after seeding. Demo passwords are for local
 * coursework only; change them before any public deployment.
 */
// This script rewrites predictable demo-account passwords, so it must never
// be invokable over HTTP. Run it only from a local/secure terminal.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found');
}

require_once __DIR__ . '/db.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Add role column only if it does not already exist.
    $check = $conn->query("SHOW COLUMNS FROM userdata LIKE 'role'");
    if ($check->num_rows === 0) {
        $conn->query(
            "ALTER TABLE userdata
             ADD COLUMN role ENUM('user','admin','owner')
             NOT NULL DEFAULT 'user'"
        );
        echo "Added userdata.role column.\n";
    } else {
        echo "userdata.role already exists.\n";
    }

    // Convert legacy seller accounts to regular users, then remove seller as a role.
    $conn->query("UPDATE userdata SET role = 'user' WHERE role = 'seller'");
    $conn->query("ALTER TABLE userdata MODIFY COLUMN role ENUM('user','admin','owner') NOT NULL DEFAULT 'user'");

    // Seed accounts are intentionally local/demo credentials.
    $accounts = [
        [
            'username' => 'Kumo User',
            'email' => 'user@kumo.local',
            'password' => 'KumoUser!2026',
            'role' => 'user',
        ],
        [
            'username' => 'Kumo Admin',
            'email' => 'admin@kumo.local',
            'password' => 'KumoAdmin!2026',
            'role' => 'admin',
        ],
        [
            'username' => 'Kumo Owner',
            'email' => 'owner@kumo.local',
            'password' => 'KumoOwner!2026',
            'role' => 'owner',
        ],
    ];

    $find = $conn->prepare("SELECT id FROM userdata WHERE email = ?");
    $insert = $conn->prepare(
        "INSERT INTO userdata (username, email, password, role)
         VALUES (?, ?, ?, ?)"
    );
    $update = $conn->prepare(
        "UPDATE userdata SET username = ?, password = ?, role = ? WHERE email = ?"
    );

    foreach ($accounts as $account) {
        $hash = password_hash($account['password'], PASSWORD_DEFAULT);

        $find->bind_param("s", $account['email']);
        $find->execute();
        $result = $find->get_result();

        if ($existing = $result->fetch_assoc()) {
            // Refresh seeded demo credentials and role if the account exists.
            $update->bind_param(
                "ssss",
                $account['username'],
                $hash,
                $account['role'],
                $account['email']
            );
            $update->execute();
            echo "Updated {$account['email']} ({$account['role']}).\n";
        } else {
            $insert->bind_param(
                "ssss",
                $account['username'],
                $account['email'],
                $hash,
                $account['role']
            );
            $insert->execute();
            echo "Created {$account['email']} ({$account['role']}).\n";
        }
    }

    $find->close();
    $insert->close();
    $update->close();
    $conn->close();

    echo "\nDemo accounts are ready. Passwords:\n";
    foreach ($accounts as $account) {
        echo "{$account['role']}: {$account['email']} / {$account['password']}\n";
    }
    echo "\nNext: install the role dashboard patch and log in again.\n";
} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
    fwrite(STDERR, "Seeder failed: " . $e->getMessage() . "\n");
    exit(1);
}
