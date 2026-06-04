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



    $waProvider = defined('WHATSAPP_PROVIDER') ? WHATSAPP_PROVIDER : 'africastalking';

    $cloudReady = function_exists('whatsapp_cloud_enabled') && whatsapp_cloud_enabled();

    $smsReady = function_exists('africastalking_sms_ready') && africastalking_sms_ready();

    $waReady = function_exists('whatsapp_outbound_ready') && whatsapp_outbound_ready();

    $verifySet = defined('WHATSAPP_VERIFY_TOKEN') && WHATSAPP_VERIFY_TOKEN !== '';



    $whatsappPatients = (int) $pdo->query(

        "SELECT COUNT(DISTINCT patient_id) c FROM contact_channels WHERE channel = 'whatsapp' AND opted_in = 1"

    )->fetch()['c'];

    $smsPatients = (int) $pdo->query(

        "SELECT COUNT(DISTINCT patient_id) c FROM contact_channels WHERE channel = 'sms' AND opted_in = 1"

    )->fetch()['c'];



    $recentWa = (int) $pdo->query(

        "SELECT COUNT(*) c FROM outbound_messages WHERE channel = 'whatsapp' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"

    )->fetch()['c'];

    $recentSms = (int) $pdo->query(

        "SELECT COUNT(*) c FROM outbound_messages WHERE channel = 'sms' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"

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

    $lastSmsError = $pdo->query(

        "SELECT error_detail FROM outbound_messages WHERE channel = 'sms' AND status = 'failed' ORDER BY id DESC LIMIT 1"

    )->fetch();



    $setupRequired = [];

    if (!$smsReady) {

        $setupRequired[] = 'SMS: set AFRICASTALKING_MODE=production and AFRICASTALKING_PROD_API_KEY, PROD_USERNAME, PROD_SMS_FROM';

    }

    if ($waProvider === 'cloud' && !$cloudReady) {

        $setupRequired[] = 'WhatsApp (Mteja): set WHATSAPP_PROVIDER=cloud, WHATSAPP_ACCESS_TOKEN, WHATSAPP_PHONE_NUMBER_ID on Render';

    }

    if ($waProvider === 'cloud' && !$verifySet) {
        $setupRequired[] = 'WhatsApp webhook: set WHATSAPP_VERIFY_TOKEN (same value in Mteja/Meta webhook settings)';
    }



    $baseUrl = (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] !== '')

        ? ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']

        : 'https://medicback.onrender.com';



    api_json([

        'ok' => true,

        'channels' => [

            'sms' => [

                'provider' => 'africastalking',

                'ready' => $smsReady,

                'mode' => $mode,

                'api_key_configured' => $apiKeySet,

                'username_configured' => $usernameSet,

                'from' => $smsFrom !== '' ? $smsFrom : null,

                'inbound_webhook' => $baseUrl . '/webhook_africastalking.php',

            ],

            'whatsapp' => [

                'provider' => $waProvider === 'cloud' ? 'mteja_meta_cloud' : 'africastalking',

                'ready' => $waReady,

                'cloud_configured' => $cloudReady,

                'verify_token_set' => $verifySet,

                'inbound_webhook' => $baseUrl . '/webhook_whatsapp.php',

            ],

        ],

        'messaging_ready' => $smsReady && ($whatsappPatients === 0 || $waReady),

        'setup_required' => $setupRequired,

        'patients' => [

            'whatsapp_opted_in' => $whatsappPatients,

            'sms_opted_in' => $smsPatients,

        ],

        'last_7_days' => [

            'whatsapp_sent' => $recentWa,

            'sms_sent' => $recentSms,

            'whatsapp_failed' => $recentWaFailed,

            'sms_failed' => $recentSmsFailed,

            'last_whatsapp_error' => $lastWaError['error_detail'] ?? null,

            'last_sms_error' => $lastSmsError['error_detail'] ?? null,

        ],

        'docs' => [

            'mteja_go_live' => '/hospital_portal/docs/MTEJA_WHATSAPP_GO_LIVE.md',

            'whatsapp_templates' => '/hospital_portal/docs/WHATSAPP_MESSAGE_TEMPLATES.md',

        ],

        'cron' => [

            'reminders_endpoint' => '/cron_run_reminders.php',

            'note' => 'Schedule every 30-60 min. Set CRON_SECRET on server and pass ?key=...',

        ],

    ]);

} catch (Throwable $e) {

    api_json(['ok' => false, 'error' => $e->getMessage()], 500);

}

