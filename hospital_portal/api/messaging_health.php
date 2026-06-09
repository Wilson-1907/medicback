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
    $mtejaReady = function_exists('mteja_whatsapp_enabled') && mteja_whatsapp_enabled();

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

    if ($waProvider === 'mteja' && !$mtejaReady) {
        $setupRequired[] = 'WhatsApp (Mteja): set WHATSAPP_PROVIDER=mteja, MTEJA_APP_ID, MTEJA_API_KEY, MTEJA_VIRTUAL_NUMBER (+254142830423)';
    }

    if ($waProvider === 'cloud' && !$cloudReady) {

        $setupRequired[] = 'WhatsApp (Mteja): set WHATSAPP_PROVIDER=cloud, WHATSAPP_ACCESS_TOKEN, WHATSAPP_PHONE_NUMBER_ID on Render';

    }

    if (in_array($waProvider, ['cloud', 'mteja'], true) && !$verifySet) {
        $setupRequired[] = 'WhatsApp inbound: set WHATSAPP_VERIFY_TOKEN and register webhook_whatsapp.php in Mteja (Settings → Channels → WhatsApp → webhook mode)';
    }



    $baseUrl = 'https://medicback.onrender.com';
    if (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] !== '') {
        $scheme = 'https';
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            $scheme = strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https' ? 'https' : 'http';
        } elseif (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
            $scheme = str_contains((string) $_SERVER['HTTP_HOST'], 'onrender.com') ? 'https' : 'http';
        }
        $baseUrl = $scheme . '://' . $_SERVER['HTTP_HOST'];
    }



    $lastInbound = $pdo->query(
        "SELECT received_at, from_address, LEFT(body, 80) AS preview
         FROM inbound_messages
         ORDER BY id DESC LIMIT 1"
    )->fetch();

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
                'provider' => $waProvider === 'mteja' ? 'mteja_template_api' : ($waProvider === 'cloud' ? 'meta_cloud' : 'africastalking'),
                'ready' => $waReady,
                'mteja_configured' => $mtejaReady,
                'cloud_configured' => $cloudReady,
                'verify_token_set' => $verifySet,
                'virtual_number' => defined('MTEJA_VIRTUAL_NUMBER') && MTEJA_VIRTUAL_NUMBER !== '' ? MTEJA_VIRTUAL_NUMBER : null,
                'inbound_webhook' => $baseUrl . '/webhook_whatsapp.php',
                'mteja_send_endpoint' => defined('MTEJA_API_URL') ? MTEJA_API_URL : 'https://api.sentry.mteja.io/api/whatsapp-template',
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

        'inbound' => [
            'last_received_at' => $lastInbound['received_at'] ?? null,
            'last_from' => $lastInbound['from_address'] ?? null,
            'last_preview' => $lastInbound['preview'] ?? null,
            'webhook_url' => $baseUrl . '/webhook_whatsapp.php',
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

