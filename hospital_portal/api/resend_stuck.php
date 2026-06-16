<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../data_wipe.php';
require_once __DIR__ . '/../stuck_messages.php';

@set_time_limit(300);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$cronSecret = getenv('CRON_SECRET') ?: '';
$providedCron = (string) ($_GET['key'] ?? $_SERVER['HTTP_X_CRON_SECRET'] ?? '');
$cronOk = $cronSecret !== '' && hash_equals($cronSecret, $providedCron);

if ($method === 'GET' && $cronOk) {
    $lookbackHours = (int) ($_GET['hours'] ?? 168);
    $maxResends = (int) ($_GET['limit'] ?? 200);
    try {
        $result = resend_stuck_messages($lookbackHours, $maxResends);
        api_json(['ok' => true, 'timestamp' => date('c'), 'resend' => $result]);
    } catch (Throwable $e) {
        api_json(['ok' => false, 'error' => $e->getMessage()], 500);
    }
}

if ($method !== 'POST') {
    api_json(['ok' => false, 'error' => 'POST required (or GET with valid CRON_SECRET)'], 405);
}

$body = api_body();
$password = trim((string) ($body['password'] ?? ''));
if (!$cronOk) {
    if (!wipe_data_password_configured()) {
        api_json([
            'ok' => false,
            'error' => 'Admin password is not configured on the server.',
            'code' => 'auth_not_configured',
        ], 503);
    }
    if (!wipe_data_password_valid($password)) {
        api_json(['ok' => false, 'error' => 'Invalid password', 'code' => 'invalid_password'], 401);
    }
}

$lookbackHours = (int) ($body['hours'] ?? 168);
$maxResends = (int) ($body['limit'] ?? 200);

try {
    $result = resend_stuck_messages($lookbackHours, $maxResends);
    api_json(['ok' => true, 'timestamp' => date('c'), 'resend' => $result]);
} catch (Throwable $e) {
    error_log('resend_stuck API error: ' . $e->getMessage());
    api_json(['ok' => false, 'error' => $e->getMessage()], 500);
}
