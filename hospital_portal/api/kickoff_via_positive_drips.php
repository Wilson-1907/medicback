<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../data_wipe.php';
require_once __DIR__ . '/../encouragement_drip.php';

@set_time_limit(300);

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $cronSecret = getenv('CRON_SECRET') ?: '';
    $providedCron = (string) ($_GET['key'] ?? $_SERVER['HTTP_X_CRON_SECRET'] ?? '');
    $cronOk = $cronSecret !== '' && hash_equals($cronSecret, $providedCron);

    if ($method === 'GET' && $cronOk) {
        $result = kickoff_post_via_positive_counseling_drips_now();
        api_json(['ok' => true, 'timestamp' => date('c'), 'kickoff' => $result]);
    }

    if ($method !== 'POST') {
        api_json(['ok' => false, 'error' => 'POST required (or GET with valid CRON_SECRET)'], 405);
    }

    $body = api_body();
    if (!$cronOk) {
        $password = trim((string) ($body['password'] ?? ''));
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

    $result = kickoff_post_via_positive_counseling_drips_now();
    api_json(['ok' => true, 'timestamp' => date('c'), 'kickoff' => $result]);
} catch (Throwable $e) {
    error_log('kickoff_via_positive_drips API: ' . $e->getMessage());
    api_json(['ok' => false, 'error' => $e->getMessage()], 500);
}
