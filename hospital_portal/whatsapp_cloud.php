<?php
declare(strict_types=1);

/**
 * WhatsApp Business Cloud API (Meta) — typical path when using Mteja / direct WABA.
 */

function whatsapp_cloud_enabled(): bool
{
    return defined('WHATSAPP_PROVIDER')
        && WHATSAPP_PROVIDER === 'cloud'
        && defined('WHATSAPP_ACCESS_TOKEN')
        && WHATSAPP_ACCESS_TOKEN !== ''
        && defined('WHATSAPP_PHONE_NUMBER_ID')
        && WHATSAPP_PHONE_NUMBER_ID !== '';
}

function whatsapp_cloud_graph_url(string $path): string
{
    $version = defined('WHATSAPP_GRAPH_VERSION') ? WHATSAPP_GRAPH_VERSION : 'v21.0';
    return 'https://graph.facebook.com/' . $version . '/' . ltrim($path, '/');
}

/** E.164 +254… → digits only for Graph API "to" field */
function whatsapp_cloud_to_digits(string $e164): string
{
    $digits = preg_replace('/\D+/', '', $e164) ?? '';
    if (str_starts_with($digits, '0')) {
        $digits = '254' . substr($digits, 1);
    }
    if (strlen($digits) === 9) {
        $digits = '254' . $digits;
    }
    return $digits;
}

/**
 * Send a text WhatsApp message via Cloud API.
 * @return array{ok: bool, message_id: ?string, error: ?string}
 */
function whatsapp_cloud_send(string $toE164, string $message): array
{
    if (!whatsapp_cloud_enabled()) {
        return ['ok' => false, 'message_id' => null, 'error' => 'WhatsApp Cloud API not configured'];
    }
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'message_id' => null, 'error' => 'PHP cURL extension is not enabled'];
    }

    $to = whatsapp_cloud_to_digits($toE164);
    if ($to === '') {
        return ['ok' => false, 'message_id' => null, 'error' => 'Invalid recipient number'];
    }

    $payload = [
        'messaging_product' => 'whatsapp',
        'recipient_type' => 'individual',
        'to' => $to,
        'type' => 'text',
        'text' => ['preview_url' => false, 'body' => $message],
    ];

    $url = whatsapp_cloud_graph_url(WHATSAPP_PHONE_NUMBER_ID . '/messages');
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . WHATSAPP_ACCESS_TOKEN,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20,
    ]);

    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($raw === false) {
        return ['ok' => false, 'message_id' => null, 'error' => $err !== '' ? $err : 'cURL error'];
    }

    $json = json_decode($raw, true);
    $messageId = null;
    if (is_array($json) && isset($json['messages'][0]['id'])) {
        $messageId = (string) $json['messages'][0]['id'];
    }

    if ($code >= 200 && $code < 300) {
        return ['ok' => true, 'message_id' => $messageId, 'error' => null];
    }

    return [
        'ok' => false,
        'message_id' => $messageId,
        'error' => 'HTTP ' . $code . ': ' . (is_array($json) ? json_encode($json) : $raw),
    ];
}
