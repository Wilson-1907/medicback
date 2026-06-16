<?php
declare(strict_types=1);

require_once __DIR__ . '/stuck_messages.php';

header('Content-Type: application/json; charset=UTF-8');

$cronSecret = getenv('CRON_SECRET') ?: '';
if ($cronSecret !== '') {
    $provided = (string) ($_GET['key'] ?? $_SERVER['HTTP_X_CRON_SECRET'] ?? '');
    if (!hash_equals($cronSecret, $provided)) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$lookbackHours = (int) ($_GET['hours'] ?? 168);
$maxResends = (int) ($_GET['limit'] ?? 200);

try {
    $result = resend_stuck_messages($lookbackHours, $maxResends);
    echo json_encode([
        'ok' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'resend' => $result,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('cron_resend_stuck error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s'),
    ], JSON_UNESCAPED_UNICODE);
}
