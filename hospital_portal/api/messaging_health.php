<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

try {
    $pdo = db();
    $mode = defined('AFRICASTALKING_MODE') ? AFRICASTALKING_MODE : 'unknown';

    $smsFrom = defined('AFRICASTALKING_SMS_FROM') ? AFRICASTALKING_SMS_FROM : '';
    $waFrom = defined('AFRICASTALKING_WHATSAPP_FROM') ? AFRICASTALKING_WHATSAPP_FROM : '';
    $apiKeySet = defined('AFRICASTALKING_API_KEY') && AFRICASTALKING_API_KEY !== '';
    $usernameSet = defined('AFRICASTALKING_USERNAME') && AFRICASTALKING_USERNAME !== '';

    $whatsappPatients = (int) $pdo->query(
        "SELECT COUNT(DISTINCT patient_id) c FROM contact_channels WHERE channel = 'whatsapp' AND opted_in = 1"
    )->fetch()['c'];
    $smsPatients = (int) $pdo->query(
        "SELECT COUNT(DISTINCT patient_id) c FROM contact_channels WHERE channel = 'sms' AND opted_in = 1"
    )->fetch()['c'];

    $recentWa = (int) $pdo->query(
        "SELECT COUNT(*) c FROM outbound_messages WHERE channel = 'whatsapp' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
    )->fetch()['c'];
    $recentWaFailed = (int) $pdo->query(
        "SELECT COUNT(*) c FROM outbound_messages WHERE channel = 'whatsapp' AND status = 'failed' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
    )->fetch()['c'];
    $recentSmsFailed = (int) $pdo->query(
        "SELECT COUNT(*) c FROM outbound_messages WHERE channel = 'sms' AND status = 'failed' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
    )->fetch()['c'];

    $lastWaError = $pdo->query(
        "SELECT error_detail FROM outbound_messages WHERE channel = 'whatsapp' AND status = 'failed' ORDER BY id DESC LIMIT 1"
    )->fetch();

    api_json([
        'ok' => true,
        'africastalking' => [
            'mode' => $mode,
            'api_key_configured' => $apiKeySet,
            'username_configured' => $usernameSet,
            'sms_from' => $smsFrom !== '' ? $smsFrom : null,
            'whatsapp_from' => $waFrom !== '' ? $waFrom : null,
            'whatsapp_ready' => $apiKeySet && $usernameSet && $waFrom !== '',
            'sms_ready' => $apiKeySet && $usernameSet && $smsFrom !== '',
        ],
        'patients' => [
            'whatsapp_opted_in' => $whatsappPatients,
            'sms_opted_in' => $smsPatients,
        ],
        'last_7_days' => [
            'whatsapp_sent' => $recentWa,
            'whatsapp_failed' => $recentWaFailed,
            'sms_failed' => $recentSmsFailed,
            'last_whatsapp_error' => $lastWaError['error_detail'] ?? null,
        ],
        'webhook_urls' => [
            'inbound' => '/webhook_africastalking.php',
            'delivery' => '/webhook_delivery_report.php',
        ],
        'cron' => [
            'reminders_endpoint' => '/cron_run_reminders.php',
            'note' => 'Schedule every 30-60 min. Set CRON_SECRET on server and pass ?key=...',
        ],
    ]);
} catch (Throwable $e) {
    api_json(['ok' => false, 'error' => $e->getMessage()], 500);
}
