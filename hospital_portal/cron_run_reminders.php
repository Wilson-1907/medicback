<?php
declare(strict_types=1);

require_once __DIR__ . '/reminders.php';
require_once __DIR__ . '/scheduled_messages.php';
require_once __DIR__ . '/encouragement_drip.php';

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
    $scheduled = process_due_scheduled_messages();
    $engagementMessages = process_random_engagement_messages();
    $dripRepaired = repair_stalled_hpv_positive_drips();

    echo json_encode([
        'ok' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'appointment_reminders' => $appointmentReminders,
        'scheduled_messages' => $scheduled,
        'engagement_boost' => $engagementMessages,
        'hpv_drip_repaired' => $dripRepaired,
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
