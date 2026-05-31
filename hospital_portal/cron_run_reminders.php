<?php
declare(strict_types=1);

require_once __DIR__ . '/reminders.php';

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

try {
    $appointmentReminders = process_due_appointment_reminders();
    $engagementMessages = process_random_engagement_messages();

    echo json_encode([
        'ok' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'appointment_reminders' => $appointmentReminders,
        'engagement_boost' => $engagementMessages,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('Cron job error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s'),
    ], JSON_UNESCAPED_UNICODE);
}
