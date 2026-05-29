<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/messaging.php';
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
    $st = db()->prepare(
        'INSERT INTO doctor_call_requests (patient_id, reason, status, requested_at)
         VALUES (?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE status = ?, requested_at = NOW()'
    );
    $st->execute([$patientId, $reason, 'pending', 'pending']);
}

function send_unlinked_reply(string $channel, string $to, string $body): void
{
    if ($to === '') {
        return;
    }
    africastalking_send($channel, $to, $body);
}

error_log("=== WEBHOOK RECEIVED ===");
error_log("OPENAI_API_KEY configured: " . (openai_enabled() ? 'YES' : 'NO'));
error_log("AFRICASTALKING_API_KEY configured: " . (messaging_enabled() ? 'YES' : 'NO'));

$payload = request_payload();
error_log("WEBHOOK_PAYLOAD: " . json_encode($payload));

$from = normalize_inbound_phone(payload_value($payload, ['from', 'fromNumber', 'source', 'sender']));
$body = payload_value($payload, ['text', 'message', 'body', 'content']);
$channel = channel_from_payload($payload);

error_log("PARSED: from=$from, channel=$channel, body=$body");

$patient = find_patient_by_phone($from);
$patientId = $patient ? (int) $patient['id'] : null;
$lang = $patient ? ((string) $patient['preferred_language'] ?: 'en') : 'en';

error_log("PATIENT_LOOKUP: patientId=$patientId, lang=$lang, name=" . ($patient['full_name'] ?? 'N/A'));

save_inbound($patientId, $channel, $from, $body, $payload);

if ($body === '') {
    error_log("WEBHOOK_EXIT: Empty body");
    echo 'OK';
    exit;
}

// Unlinked patient (not registered)
if (!$patientId) {
    error_log("WEBHOOK_EXIT: Unlinked patient, sending unlinked reply");
    send_unlinked_reply(
        $channel,
        $from,
        'Hi. To get personalized health support, please register your number with the hospital. If this is urgent, contact the hospital directly.'
    );
    echo 'OK';
    exit;
}

$msg = strtoupper(trim($body));

// SPECIAL HANDLING: DOCTOR keyword triggers call booking (direct call to doctor/nurse)
if ($msg === 'DOCTOR' || $msg === '4') {
    error_log("WEBHOOK_ACTION: Doctor request detected");
    create_doctor_call_request($patientId, 'Patient requested direct doctor contact via ' . $channel);
    if ($lang === 'sw') {
        send_patient_message(
            $patientId,
            'system',
            'Asante. Ombi lako limetumwa kwa ' . HOSPITAL_NAME . '. Mwanachama wa timu atakupigia simu hivi karibuni kwa ajili ya mazungumzo ya umbea.'
        );
    } else {
        send_patient_message(
            $patientId,
            'system',
            'Thank you. Your request has been sent to ' . HOSPITAL_NAME . '. A care team member will call you shortly for a consultation.'
        );
    }
    error_log("WEBHOOK_EXIT: Doctor request processed");
    echo 'OK';
    exit;
}

// SPECIAL HANDLING: HELP/MENU shows options
if ($msg === 'HELP' || $msg === 'MENU' || $msg === '0') {
    error_log("WEBHOOK_ACTION: Help/Menu request detected");
    send_patient_message($patientId, 'education_menu', build_engagement_menu_message($lang));
    error_log("WEBHOOK_EXIT: Help/Menu processed");
    echo 'OK';
    exit;
}

// DEFAULT: ALL OTHER MESSAGES GO TO AI
// This includes: questions about PHV signs, prevention, symptoms, any medical topic
error_log("WEBHOOK_ACTION: Routing to AI");
$ai = ai_generate_reply($patientId, $channel, $body, $lang);
error_log("AI_RESPONSE: ok=" . ($ai['ok'] ? 'true' : 'false') . ", error=" . ($ai['error'] ?? 'none'));

if ($ai['ok'] && !empty($ai['reply'])) {
    error_log("WEBHOOK_ACTION: Sending AI reply: " . substr($ai['reply'], 0, 100) . "...");
    send_patient_message($patientId, 'system', $ai['reply']);
    error_log("WEBHOOK_EXIT: AI reply sent");
    echo 'OK';
    exit;
}

// Fallback if AI fails (no OPENAI_API_KEY or API error)
error_log("WEBHOOK_ACTION: Using fallback reply");
if ($lang === 'sw') {
    send_patient_message(
        $patientId,
        'system',
        'Asante kwa ujumbe wako. Tupo hapa kwako. Jibu DOCTOR kwa kuongea na daktari au HELP kwa mwongozo zaidi.'
    );
} else {
    send_patient_message(
        $patientId,
        'system',
        'Thank you for your message. We are here for you. Reply DOCTOR to speak with a doctor or HELP for more options.'
    );
}
error_log("WEBHOOK_EXIT: Fallback reply sent");
echo 'OK';
