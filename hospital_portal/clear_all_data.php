<?php
declare(strict_types=1);

/**
 * Wipe all rows from every table (schema kept).
 * GET /clear_all_data.php?confirm=yes
 * Auth: CRON_SECRET (if set) OR password=Adminpass (override via WIPE_DATA_PASSWORD env)
 */
require_once __DIR__ . '/data_wipe.php';

header('Content-Type: application/json; charset=UTF-8');

$authorized = false;

$cronSecret = getenv('CRON_SECRET') ?: '';
if ($cronSecret !== '') {
    $provided = (string) ($_GET['key'] ?? $_SERVER['HTTP_X_CRON_SECRET'] ?? '');
    if (hash_equals($cronSecret, $provided)) {
        $authorized = true;
    }
}

$password = (string) ($_GET['password'] ?? $_POST['password'] ?? '');
if (!$authorized && wipe_data_password_valid($password)) {
    $authorized = true;
}

if (!$authorized) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_GET['confirm'] ?? '') !== 'yes') {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'Missing confirm=yes. This deletes all data in every table.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $result = wipe_all_database_tables();
    $result['timestamp'] = date('c');
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('clear_all_data error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('c'),
    ], JSON_UNESCAPED_UNICODE);
}
