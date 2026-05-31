<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/reminders.php';

header('Content-Type: application/json; charset=UTF-8');

try {
    $appointmentReminders = process_due_appointment_reminders();
    $engagementMessages = process_random_engagement_messages();
    
    $response = [
        'ok' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'appointment_reminders' => $appointmentReminders,
        'engagement_boost' => $engagementMessages,
    ];
    
    http_response_code(200);
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('Cron job error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s'),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
