<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../data_wipe.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    api_json(['ok' => false, 'error' => 'POST required'], 405);
}

@set_time_limit(300);

$body = api_body();
$password = trim((string) ($body['password'] ?? ''));
$confirm = !empty($body['confirm']);

if (!wipe_data_password_configured()) {
    api_json([
        'ok' => false,
        'error' => 'Wipe password is not configured on the server. Set WIPE_DATA_PASSWORD (e.g. Adminpass) in Render environment variables.',
        'code' => 'wipe_not_configured',
    ], 503);
}

if (!wipe_data_password_valid($password)) {
    api_json(['ok' => false, 'error' => 'Invalid password', 'code' => 'invalid_password'], 401);
}

if (!$confirm) {
    api_json(['ok' => false, 'error' => 'Set confirm: true to erase all data'], 400);
}

try {
    $result = wipe_all_database_tables();
    $result['timestamp'] = date('c');
    api_json($result);
} catch (Throwable $e) {
    error_log('clear_data API error: ' . $e->getMessage());
    api_json(['ok' => false, 'error' => $e->getMessage(), 'code' => 'wipe_failed'], 500);
}