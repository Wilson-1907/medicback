<?php
declare(strict_types=1);

/**
 * Meta WhatsApp Cloud API webhook (used with Mteja / direct WABA).
 * Configure in Meta Developer Portal or ask Mteja to set:
 *   https://medicback.onrender.com/webhook_whatsapp.php
 */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/messaging.php';
require_once __DIR__ . '/afya_rafiki_content.php';
require_once __DIR__ . '/hpv_results.php';
require_once __DIR__ . '/openai_assistant.php';
require_once __DIR__ . '/doctor_call_requests.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

/** Webhook verification (Meta GET) */
if ($method === 'GET') {
    $mode = (string) ($_GET['hub_mode'] ?? $_GET['hub.mode'] ?? '');
    $token = (string) ($_GET['hub_verify_token'] ?? $_GET['hub.verify_token'] ?? '');
    $challenge = (string) ($_GET['hub_challenge'] ?? $_GET['hub.challenge'] ?? '');
    $expected = defined('WHATSAPP_VERIFY_TOKEN') ? WHATSAPP_VERIFY_TOKEN : '';

    if ($mode === 'subscribe' && $expected !== '' && hash_equals($expected, $token)) {
        header('Content-Type: text/plain');
        echo $challenge;
        exit;
    }
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

header('Content-Type: text/plain');
echo 'OK';
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} elseif (ob_get_level()) {
    ob_end_flush();
}
flush();

$raw = file_get_contents('php://input');
$payload = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
if (!is_array($payload)) {
    exit;
}

/** @return list<array{from: string, body: string}> */
function whatsapp_cloud_parse_inbound_messages(array $payload): array
{
    $out = [];
    foreach ($payload['entry'] ?? [] as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        foreach ($entry['changes'] ?? [] as $change) {
            if (!is_array($change) || ($change['field'] ?? '') !== 'messages') {
                continue;
            }
            $value = $change['value'] ?? [];
            if (!is_array($value)) {
                continue;
            }
            foreach ($value['messages'] ?? [] as $msg) {
                if (!is_array($msg) || ($msg['type'] ?? '') !== 'text') {
                    continue;
                }
                $from = (string) ($msg['from'] ?? '');
                $body = (string) ($msg['text']['body'] ?? '');
                if ($from !== '' && $body !== '') {
                    $out[] = ['from' => '+' . preg_replace('/\D+/', '', $from), 'body' => $body];
                }
            }
        }
    }
    return $out;
}

function whatsapp_webhook_normalize_phone(string $raw): string
{
    $digits = preg_replace('/\D+/', '', $raw) ?? '';
    if ($digits === '') {
        return '';
    }
    if (!str_starts_with($digits, '254') && strlen($digits) === 9) {
        $digits = '254' . $digits;
    }
    return '+' . $digits;
}

function whatsapp_webhook_find_patient(string $phone): ?array
{
    if ($phone === '') {
        return null;
    }
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    $st = db()->prepare(
        'SELECT p.id, p.full_name, p.preferred_language
         FROM contact_channels c
         INNER JOIN patients p ON p.id = c.patient_id
         WHERE c.opted_in = 1
           AND (c.address = ? OR REPLACE(REPLACE(c.address, "+", ""), " ", "") = ?)
         ORDER BY c.is_primary DESC, c.id ASC
         LIMIT 1'
    );
    $st->execute([$phone, $digits]);
    $row = $st->fetch();
    return $row ?: null;
}

function whatsapp_webhook_save_inbound(?int $patientId, string $from, string $body, array $payload): void
{
    $st = db()->prepare(
        'INSERT INTO inbound_messages (patient_id, channel, from_address, body, raw_payload)
         VALUES (?,?,?,?,?)'
    );
    $st->execute([$patientId, 'whatsapp', $from, $body, json_encode($payload)]);
}

function whatsapp_webhook_process_message(array $patient, string $from, string $body): void
{
    $patientId = (int) $patient['id'];
    $registeredLang = strtolower((string) ($patient['preferred_language'] ?? 'en')) === 'sw' ? 'sw' : 'en';
    $lang = ai_detect_message_language($body, $registeredLang);
    $msg = strtoupper(trim($body));
    $replyLang = $lang === 'sw' ? 'sw' : 'en';

    $faqReply = afya_faq_reply($body, $replyLang);
    if ($faqReply !== null) {
        send_patient_message($patientId, 'system', $faqReply);
        return;
    }

    if (patient_awaiting_doctor_reason($patientId) && should_capture_as_doctor_reason($body, $msg)) {
        complete_doctor_call_with_patient_reason($patientId, $body, 'whatsapp', $replyLang);
        return;
    }

    $escalation = afya_escalation_check($body);
    if ($escalation['escalate']) {
        create_escalation($patientId, $escalation['reason'], $escalation['urgency']);
        if (preg_match('/\b(missed|sikuhudhuria|nilikosa)\b/ui', $body)) {
            send_patient_message(
                $patientId,
                'escalation_notice',
                build_missed_appointment_message((string) ($patient['full_name'] ?? ''), $replyLang)
            );
        } else {
            send_patient_message($patientId, 'escalation_notice', build_escalation_reply($replyLang));
        }
        return;
    }

    if (is_doctor_request_keyword($msg)) {
        handle_doctor_request_keyword($patientId, 'whatsapp', $replyLang);
        return;
    }

    $ai = ai_generate_reply($patientId, 'whatsapp', $body, $registeredLang);
    if ($ai['ok'] && !empty($ai['reply'])) {
        send_patient_message($patientId, 'ai_reply', $ai['reply']);
        return;
    }

    send_patient_message($patientId, 'system', ai_fallback_reply($lang));
}

$messages = whatsapp_cloud_parse_inbound_messages($payload);
foreach ($messages as $item) {
    $from = whatsapp_webhook_normalize_phone($item['from']);
    $body = trim($item['body']);
    if ($from === '' || $body === '') {
        continue;
    }

    $patient = whatsapp_webhook_find_patient($from);
    $patientId = $patient ? (int) $patient['id'] : null;
    whatsapp_webhook_save_inbound($patientId, $from, $body, $payload);

    if (!$patient) {
        if (function_exists('mteja_whatsapp_enabled') && mteja_whatsapp_enabled()) {
            $suffix = ai_detect_message_language($body, 'en') === 'sw' ? 'sw' : 'en';
            mteja_whatsapp_send_template(
                $from,
                'afya_unlinked_' . $suffix,
                mteja_lang_code($suffix),
                [mteja_body_component([])]
            );
        } elseif (function_exists('whatsapp_cloud_enabled') && whatsapp_cloud_enabled()) {
            whatsapp_cloud_send($from, ai_unlinked_reply(ai_detect_message_language($body, 'en')));
        }
        continue;
    }

    whatsapp_webhook_process_message($patient, $from, $body);
}
