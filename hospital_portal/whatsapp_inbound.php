<?php
declare(strict_types=1);

/**
 * Parse and process inbound WhatsApp messages (Meta Cloud, Mteja forward, or flat JSON).
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/messaging.php';
require_once __DIR__ . '/afya_rafiki_content.php';

function whatsapp_inbound_normalize_phone(string $raw): string
{
    $digits = preg_replace('/\D+/', '', trim($raw)) ?? '';
    if ($digits === '') {
        return '';
    }
    if (str_starts_with($digits, '0')) {
        $digits = '254' . substr($digits, 1);
    } elseif (strlen($digits) === 9) {
        $digits = '254' . $digits;
    }
    return '+' . $digits;
}

function whatsapp_inbound_payload_value(array $payload, array $keys): string
{
    $lower = [];
    foreach ($payload as $k => $v) {
        if (is_scalar($v)) {
            $lower[strtolower((string) $k)] = trim((string) $v);
        }
    }
    foreach ($keys as $key) {
        $v = $lower[strtolower($key)] ?? '';
        if ($v !== '') {
            return $v;
        }
    }
    return '';
}

/** @return list<array{from: string, body: string}> */
function whatsapp_inbound_parse_messages(array $payload): array
{
    $out = [];
    $seen = [];

    $add = static function (string $from, string $body) use (&$out, &$seen): void {
        $from = whatsapp_inbound_normalize_phone($from);
        $body = trim($body);
        if ($from === '' || $body === '') {
            return;
        }
        $key = $from . '|' . $body;
        if (isset($seen[$key])) {
            return;
        }
        $seen[$key] = true;
        $out[] = ['from' => $from, 'body' => $body];
    };

    // Meta WhatsApp Cloud: entry[].changes[].value.messages[]
    foreach ($payload['entry'] ?? [] as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        foreach ($entry['changes'] ?? [] as $change) {
            if (!is_array($change)) {
                continue;
            }
            $value = $change['value'] ?? [];
            if (!is_array($value)) {
                continue;
            }
            foreach ($value['messages'] ?? [] as $msg) {
                if (!is_array($msg)) {
                    continue;
                }
                $type = (string) ($msg['type'] ?? 'text');
                if ($type !== 'text' && $type !== 'button' && $type !== 'interactive') {
                    continue;
                }
                $from = (string) ($msg['from'] ?? '');
                $body = (string) ($msg['text']['body'] ?? '');
                if ($body === '' && isset($msg['button']['text'])) {
                    $body = (string) $msg['button']['text'];
                }
                if ($body === '' && isset($msg['button']['payload'])) {
                    $body = (string) $msg['button']['payload'];
                }
                if ($body === '' && isset($msg['interactive']['button_reply']['title'])) {
                    $body = (string) $msg['interactive']['button_reply']['title'];
                }
                if ($body === '' && isset($msg['interactive']['list_reply']['title'])) {
                    $body = (string) $msg['interactive']['list_reply']['title'];
                }
                $add($from, $body);
            }
        }
    }

    // Flat messages[] (EnableX / some providers)
    foreach ($payload['messages'] ?? [] as $msg) {
        if (!is_array($msg)) {
            continue;
        }
        $from = (string) ($msg['from'] ?? $msg['sender'] ?? $msg['customerNumber'] ?? '');
        $body = (string) ($msg['text']['body'] ?? $msg['body'] ?? $msg['text'] ?? $msg['message'] ?? '');
        if (is_array($msg['text'] ?? null)) {
            $body = (string) (($msg['text']['body'] ?? '') ?: $body);
        }
        $add($from, $body);
    }

    // Single flat payload (Mteja / custom webhook)
    $from = whatsapp_inbound_payload_value($payload, [
        'from', 'fromNumber', 'sender', 'customerNumber', 'customer_number', 'phone', 'msisdn', 'wa_id',
    ]);
    $body = whatsapp_inbound_payload_value($payload, [
        'text', 'message', 'body', 'content', 'messageText', 'message_text',
    ]);
    if ($from !== '' && $body !== '') {
        $add($from, $body);
    }

    // Nested data / message object
    foreach (['data', 'message', 'payload'] as $nestKey) {
        $nested = $payload[$nestKey] ?? null;
        if (!is_array($nested)) {
            continue;
        }
        $from = whatsapp_inbound_payload_value($nested, ['from', 'customerNumber', 'sender']);
        $body = whatsapp_inbound_payload_value($nested, ['text', 'body', 'message', 'content']);
        if ($body === '' && isset($nested['text']) && is_array($nested['text'])) {
            $body = (string) ($nested['text']['body'] ?? '');
        }
        if ($from !== '' && $body !== '') {
            $add($from, $body);
        }
    }

    return $out;
}

function whatsapp_inbound_phone_variants(string $phone): array
{
    $normalized = whatsapp_inbound_normalize_phone($phone);
    $digits = preg_replace('/\D+/', '', $normalized) ?? '';
    $variants = array_filter(array_unique([
        trim($phone),
        $normalized,
        $digits,
        $digits !== '' ? '+' . $digits : '',
    ]));
    return array_values($variants);
}

function whatsapp_inbound_find_patient(string $phone): ?array
{
    $variants = whatsapp_inbound_phone_variants($phone);
    if ($variants === []) {
        return null;
    }
    $placeholders = implode(',', array_fill(0, count($variants), '?'));
    $st = db()->prepare(
        "SELECT p.id, p.full_name, p.preferred_language
         FROM contact_channels c
         INNER JOIN patients p ON p.id = c.patient_id
         WHERE c.opted_in = 1 AND c.address IN ({$placeholders})
         ORDER BY c.is_primary DESC, c.id ASC
         LIMIT 1"
    );
    $st->execute($variants);
    $row = $st->fetch();
    return $row ?: null;
}

function whatsapp_inbound_save(?int $patientId, string $from, string $body, array $payload): void
{
    $st = db()->prepare(
        'INSERT INTO inbound_messages (patient_id, channel, from_address, body, raw_payload)
         VALUES (?,?,?,?,?)'
    );
    $st->execute([$patientId, 'whatsapp', $from, $body, json_encode($payload, JSON_UNESCAPED_UNICODE)]);
}

/** Language intro: 1=English, 2=Kiswahili, 3=stop messages. */
function whatsapp_inbound_try_language_reply(int $patientId, string $body, string $channel): bool
{
    $trim = trim($body);
    if ($trim === '1') {
        db()->prepare('UPDATE patients SET preferred_language = ? WHERE id = ?')->execute(['en', $patientId]);
        send_patient_message($patientId, 'system', 'Thank you. Afya Rafiki will send messages in English. Reply HELP anytime.');
        return true;
    }
    if ($trim === '2') {
        db()->prepare('UPDATE patients SET preferred_language = ? WHERE id = ?')->execute(['sw', $patientId]);
        send_patient_message($patientId, 'system', 'Asante. Afya Rafiki itatumia Kiswahili. Jibu HELP wakati wowote.');
        return true;
    }
    if ($trim === '3' || is_consent_no_reply($body)) {
        record_consent_no($patientId, $channel);
        $lang = 'en';
        send_patient_message(
            $patientId,
            'system',
            'You have been unsubscribed from Afya Rafiki messages. Contact Nyeri Town Health Centre if you need help.'
        );
        return true;
    }
    return false;
}

function whatsapp_inbound_send_unlinked(string $from, string $body): void
{
    require_once __DIR__ . '/openai_assistant.php';
    $lang = ai_detect_message_language($body, 'en');
    $suffix = $lang === 'sw' ? 'sw' : 'en';
    if (function_exists('mteja_whatsapp_enabled') && mteja_whatsapp_enabled()) {
        require_once __DIR__ . '/mteja_whatsapp.php';
        mteja_whatsapp_send_template(
            $from,
            mteja_template_name('afya_unlinked', $suffix),
            mteja_lang_code($suffix),
            []
        );
        return;
    }
    if (function_exists('whatsapp_cloud_enabled') && whatsapp_cloud_enabled()) {
        require_once __DIR__ . '/whatsapp_cloud.php';
        whatsapp_cloud_send($from, ai_unlinked_reply($lang));
    }
}

function whatsapp_inbound_process_registered_patient(array $patient, string $body): void
{
    require_once __DIR__ . '/hpv_results.php';
    require_once __DIR__ . '/openai_assistant.php';
    require_once __DIR__ . '/doctor_call_requests.php';

    $patientId = (int) $patient['id'];
    $registeredLang = strtolower((string) ($patient['preferred_language'] ?? 'en')) === 'sw' ? 'sw' : 'en';
    $lang = ai_detect_message_language($body, $registeredLang);
    $msg = strtoupper(trim($body));
    $replyLang = $lang === 'sw' ? 'sw' : 'en';

    if (whatsapp_inbound_try_language_reply($patientId, $body, 'whatsapp')) {
        return;
    }

    $faqReply = afya_faq_reply($body, $replyLang);
    if ($faqReply !== null) {
        send_patient_message($patientId, 'ai_reply', $faqReply);
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

    send_patient_message($patientId, 'ai_reply', ai_fallback_reply($lang));
}

/** Handle one inbound WhatsApp POST body (JSON or form). */
function whatsapp_inbound_handle_request(string $rawBody, array $query = []): void
{
    error_log('WHATSAPP_INBOUND_RAW: ' . substr($rawBody, 0, 4000));

    $payload = [];
    if ($rawBody !== '') {
        $json = json_decode($rawBody, true);
        if (is_array($json)) {
            $payload = $json;
        } else {
            parse_str($rawBody, $form);
            if (is_array($form) && $form !== []) {
                $payload = $form;
            }
        }
    }
    foreach ($_POST as $k => $v) {
        if (is_scalar($v)) {
            $payload[(string) $k] = (string) $v;
        }
    }
    foreach ($query as $k => $v) {
        if (is_scalar($v) && !isset($payload[$k])) {
            $payload[(string) $k] = (string) $v;
        }
    }

    $messages = whatsapp_inbound_parse_messages($payload);
    if ($messages === []) {
        error_log('WHATSAPP_INBOUND: no messages parsed from payload keys: ' . implode(',', array_keys($payload)));
        if ($payload !== []) {
            whatsapp_inbound_save(null, 'unknown', '[unparsed webhook]', $payload);
        }
        return;
    }

    foreach ($messages as $item) {
        $from = $item['from'];
        $body = $item['body'];
        $patient = null;
        $patientId = null;
        try {
            $patient = whatsapp_inbound_find_patient($from);
            $patientId = $patient ? (int) $patient['id'] : null;
        } catch (Throwable $e) {
            error_log('WHATSAPP_INBOUND find_patient: ' . $e->getMessage());
        }

        whatsapp_inbound_save($patientId, $from, $body, $payload);
        error_log("WHATSAPP_INBOUND: from={$from} patient=" . ($patientId ?? 'none') . ' body=' . substr($body, 0, 120));

        try {
            if (!$patient) {
                whatsapp_inbound_send_unlinked($from, $body);
                continue;
            }
            whatsapp_inbound_process_registered_patient($patient, $body);
        } catch (Throwable $e) {
            error_log('WHATSAPP_INBOUND process: ' . $e->getMessage());
        }
    }
}
