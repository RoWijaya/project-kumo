<?php
declare(strict_types=1);

$kumoGoogleLocalConfig = [];
$kumoGoogleRootConfig = [];
$kumoGoogleConfigPath = __DIR__ . '/config.local.php';
if (is_file($kumoGoogleConfigPath)) {
    $loadedGoogleConfig = require $kumoGoogleConfigPath;
    if (is_array($loadedGoogleConfig)) {
        $kumoGoogleRootConfig = $loadedGoogleConfig;
        $kumoGoogleLocalConfig = is_array($loadedGoogleConfig['google'] ?? null)
            ? $loadedGoogleConfig['google']
            : [];
    }
}

if (!defined('KUMO_GOOGLE_CLIENT_ID')) {
    define('KUMO_GOOGLE_CLIENT_ID', (string)(getenv('KUMO_GOOGLE_CLIENT_ID') ?: ($kumoGoogleLocalConfig['client_id'] ?? '')));
}
if (!defined('KUMO_GOOGLE_CLIENT_SECRET')) {
    define('KUMO_GOOGLE_CLIENT_SECRET', (string)(getenv('KUMO_GOOGLE_CLIENT_SECRET') ?: ($kumoGoogleLocalConfig['client_secret'] ?? '')));
}
if (!defined('KUMO_GOOGLE_REDIRECT_URI')) {
    $rawBase = getenv('KUMO_BASE_URL') ?: ($kumoGoogleRootConfig['base_url'] ?? '');
    if (is_array($rawBase)) {
        $rawBase = $rawBase['production'] ?? ($rawBase['local'] ?? '');
    }
    $configuredBase = rtrim((string)$rawBase, '/');
    $defaultRedirect = $configuredBase !== '' ? $configuredBase . '/assets/backend/google_callback.php' : '';
    define('KUMO_GOOGLE_REDIRECT_URI', (string)(getenv('KUMO_GOOGLE_REDIRECT_URI') ?: ($kumoGoogleLocalConfig['redirect_uri'] ?? $defaultRedirect)));
}

define('KUMO_GOOGLE_AUTHORIZE_URL', 'https://accounts.google.com/o/oauth2/v2/auth');
define('KUMO_GOOGLE_TOKEN_URL', 'https://oauth2.googleapis.com/token');
define('KUMO_GOOGLE_USERINFO_URL', 'https://openidconnect.googleapis.com/v1/userinfo');
