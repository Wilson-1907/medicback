<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../data_wipe.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    api_json(['ok' => false, 'error' => 'POST required'], 405);
}

$body = api_body();
$password = (string) ($body['password'] ?? '');
$confirm = !empty($body['confirm']);

if (!wipe_data_password_valid($password)) {
    api_json(['ok' => false, 'error' => 'Invalid password'], 401);
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
    api_json(['ok' => false, 'error' => $e->getMessage()], 500);
}
