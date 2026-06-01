<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/messaging.php';
require_once __DIR__ . '/afya_rafiki_content.php';
require_once __DIR__ . '/openai_assistant.php';

/**
 * Africa's Talking inbound webhook handler.
 * Configure this URL in AT dashboard for both SMS and WhatsApp callbacks.
 * 
 * FLOW: Patient message → Save to DB → Route to AI for response
 * Special keywords (DOCTOR, HELP) are handled AFTER AI tries
 */
header('Content-Type: text/plain; charset=UTF-8');

function request_payload(): array
{
    $payload = [];

    foreach ([$_GET, $_POST] as $source) {
        foreach ($source as $k => $v) {
            $payload[(string) $k] = is_scalar($v) ? (string) $v : json_encode($v);
        }
    }

    $raw = file_get_contents('php://input');
    if (is_string($raw) && trim($raw) !== '') {
        $json = json_decode($raw, true);
        if (is_array($json)) {
            foreach ($json as $k => $v) {
                $payload[(string) $k] = is_scalar($v) ? (string) $v : json_encode($v);
            }
        } else {
            parse_str($raw, $formPairs);
            if (is_array($formPairs)) {
                foreach ($formPairs as $k => $v) {
                    $payload[(string) $k] = is_scalar($v) ? (string) $v : json_encode($v);
                }
            }
        }
    }

    return $payload;
}

function payload_value(array $payload, array $keys): string
{
    $lower = [];
    foreach ($payload as $k => $v) {
        $lower[strtolower((string) $k)] = trim((string) $v);
    }
    foreach ($keys as $k) {
        $v = $lower[strtolower($k)] ?? '';
        if ($v !== '') {
            return $v;
        }
    }
    return '';
}

function channel_from_payload(array $payload): string
{
    $channel = strtolower(payload_value($payload, ['channel']));
    if ($channel === 'whatsapp') {
        return 'whatsapp';
    }
    $to = strtolower(payload_value($payload, ['to', 'toNumber', 'recipient']));
    if (str_contains($to, 'whatsapp')) {
        return 'whatsapp';
    }
    return 'sms';
}

function normalize_inbound_phone(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '') {
        return '';
    }
    if ($raw[0] === '+') {
        return '+' . preg_replace('/\D+/', '', substr($raw, 1));
    }
    return '+' . preg_replace('/\D+/', '', $raw);
}

function find_patient_by_phone(string $phone): ?array
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
            AND (c.address = ?
             OR REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(c.address, \'+\', \'\'), \' \', \'\'), \'-\', \'\'), \'(\', \'\'), \')\', \'\') = ?)
         ORDER BY c.is_primary DESC, c.id ASC
         LIMIT 1'
    );
    $st->execute([$phone, $digits]);
    $row = $st->fetch();
    return $row ?: null;
}

function save_inbound(?int $patientId, string $channel, string $from, string $body, array $payload): void
{
    $st = db()->prepare(
        'INSERT INTO inbound_messages (patient_id, channel, from_address, body, raw_payload)
         VALUES (?,?,?,?,?)'
    );
    $st->execute([$patientId, $channel, $from, $body, json_encode($payload)]);
}

function create_doctor_call_request(int $patientId, string $reason): void
{
    $pdo = db();

    // Dedicated record of the call request (one active request per patient).
    $st = $pdo->prepare(
        'INSERT INTO doctor_call_requests (patient_id, reason, status, requested_at)
         VALUES (?, ?, ?, NOW(3))
         ON DUPLICATE KEY UPDATE reason = VALUES(reason), status = ?, requested_at = NOW(3)'
    );
    $st->execute([$patientId, $reason, 'pending', 'pending']);

    // Also raise an escalation so it surfaces in the Message Center for staff follow-up.
    $esc = $pdo->prepare(
        'INSERT INTO escalations (patient_id, reason, urgency, status)
         VALUES (?, ?, ?, ?)'
    );
    $esc->execute([$patientId, $reason, 'same_day', 'open']);
}

function send_unlinked_reply(string $channel, string $to, string $body): void
{
    if ($to === '') {
        return;
    }
    africastalking_send($channel, $to, $body);
}

error_log("=== WEBHOOK RECEIVED ===");
error_log("GROQ_API_KEY configured: " . (ai_enabled() ? 'YES' : 'NO'));
error_log("AFRICASTALKING_API_KEY configured: " . (messaging_enabled() ? 'YES' : 'NO'));

$payload = request_payload();
error_log("WEBHOOK_PAYLOAD: " . json_encode($payload));

$from = normalize_inbound_phone(payload_value($payload, ['from', 'fromNumber', 'source', 'sender']));
$body = payload_value($payload, ['text', 'message', 'body', 'content']);
$channel = channel_from_payload($payload);

error_log("PARSED: from=$from, channel=$channel, body=$body");

$patient = find_patient_by_phone($from);
$patientId = $patient ? (int) $patient['id'] : null;
$registeredLang = $patient && strtolower((string) ($patient['preferred_language'] ?? 'en')) === 'sw' ? 'sw' : 'en';
$lang = ai_detect_message_language($body, $registeredLang);

error_log("PATIENT_LOOKUP: patientId=$patientId, registeredLang=$registeredLang, detectedLang=$lang, name=" . ($patient['full_name'] ?? 'N/A'));

save_inbound($patientId, $channel, $from, $body, $payload);

if ($body === '') {
    error_log("WEBHOOK_EXIT: Empty body");
    echo 'OK';
    exit;
}

// Unlinked patient (not registered)
if (!$patientId) {
    error_log("WEBHOOK_EXIT: Unlinked patient, sending unlinked reply (lang=$lang)");
    send_unlinked_reply($channel, $from, ai_unlinked_reply($lang));
    echo 'OK';
    exit;
}

$msg = strtoupper(trim($body));
$replyLang = $lang === 'sw' ? 'sw' : 'en';

// --- Consent confirmation (Afya Rafiki enrollment) ---
if (patient_awaiting_consent($patientId)) {
    if (is_consent_yes_reply($body)) {
        record_consent_yes($patientId, $channel);
        $counseling = get_next_counseling_message($patientId, $replyLang);
        if ($counseling !== null) {
            send_patient_message($patientId, 'education_menu', $counseling);
        }
        $ack = $replyLang === 'sw'
            ? 'Asante. Utaendelea kupokea ujumbe wa Afya Rafiki. Jibu HELP kwa maswali au DOCTOR kwa mhudumu wa afya.'
            : 'Thank you. You will continue receiving Afya Rafiki messages. Reply HELP for questions or DOCTOR for a provider.';
        send_patient_message($patientId, 'system', $ack);
        echo 'OK';
        exit;
    }
    if (is_consent_no_reply($body)) {
        record_consent_no($patientId, $channel);
        $ack = $replyLang === 'sw'
            ? 'Umechagua kusitopokea ujumbe. Unaweza kuwasiliana na kliniki yako moja kwa moja wakati wowote.'
            : 'You have chosen not to receive messages. You can contact your clinic directly anytime.';
        send_patient_message($patientId, 'system', $ack);
        echo 'OK';
        exit;
    }
}

// --- Rule-based FAQ / HELP menu ---
$faqReply = afya_faq_reply($body, $replyLang);
if ($faqReply !== null) {
    send_patient_message($patientId, 'system', $faqReply);
    echo 'OK';
    exit;
}

// --- Escalation triggers (symptoms, distress, missed visit, complex clinical) ---
$escalation = afya_escalation_check($body);
if ($escalation['escalate']) {
    create_escalation($patientId, $escalation['reason'], $escalation['urgency']);
    if (preg_match('/\b(missed|sikuhudhuria|nilikosa)\b/ui', $body)) {
        send_patient_message($patientId, 'escalation_notice', build_missed_appointment_message($replyLang));
    } else {
        send_patient_message($patientId, 'escalation_notice', build_escalation_reply($replyLang));
    }
    echo 'OK';
    exit;
}

// Side-effect: when a patient asks for a doctor/call, log a call request so staff can follow up.
if (str_contains($msg, 'DOCTOR') || str_contains($msg, 'DAKTARI') || $msg === '5') {
    error_log("WEBHOOK_ACTION: Doctor request detected, logging call request");
    create_doctor_call_request($patientId, 'Patient requested direct provider contact via ' . $channel);
    send_patient_message($patientId, 'escalation_notice', build_escalation_reply($replyLang));
    echo 'OK';
    exit;
}

// --- AI conversational support (mirrors patient language) ---
error_log("WEBHOOK_ACTION: Routing to AI (detected lang=$lang)");
$ai = ai_generate_reply($patientId, $channel, $body, $registeredLang);
error_log("AI_RESPONSE: ok=" . ($ai['ok'] ? 'true' : 'false') . ", error=" . ($ai['error'] ?? 'none'));

if ($ai['ok'] && !empty($ai['reply'])) {
    error_log("WEBHOOK_ACTION: Sending AI reply: " . substr($ai['reply'], 0, 100) . "...");
    send_patient_message($patientId, 'ai_reply', $ai['reply']);
    error_log("WEBHOOK_EXIT: AI reply sent");
    echo 'OK';
    exit;
}

// Fallback only when AI is unavailable (no key / API error): never leave a patient unanswered.
error_log("WEBHOOK_ACTION: Using fallback reply; AI error=" . ($ai['error'] ?? 'unknown'));
send_patient_message($patientId, 'system', ai_fallback_reply($lang));
error_log("WEBHOOK_EXIT: Fallback reply sent");
echo 'OK';
