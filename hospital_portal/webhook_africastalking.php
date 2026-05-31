<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/messaging.php';
require_once __DIR__ . '/openai_assistant.php';

/**
 * Africa's Talking inbound webhook handler.
 * Configure this URL in AT dashboard for both SMS and WhatsApp callbacks.
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
         WHERE c.address = ?
            OR REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(c.address, \'+\', \'\'), \' \', \'\'), \'-\', \'\'), \'(\', \'\'), \')\', \'\') = ?
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

function upsert_escalation(int $patientId, string $reason): void
{
    $st = db()->prepare(
        'INSERT INTO escalations (patient_id, reason, urgency, status)
         VALUES (?,?,?,?)'
    );
    $st->execute([$patientId, $reason, 'same_day', 'open']);
}

function send_unlinked_reply(string $channel, string $to, string $body): void
{
    if ($to === '') {
        return;
    }
    africastalking_send($channel, $to, $body);
}

$payload = request_payload();
$from = normalize_inbound_phone(payload_value($payload, ['from', 'fromNumber', 'source', 'sender']));
$body = payload_value($payload, ['text', 'message', 'body', 'content']);
$channel = channel_from_payload($payload);
$patient = find_patient_by_phone($from);
$patientId = $patient ? (int) $patient['id'] : null;
$lang = $patient && strtolower((string) ($patient['preferred_language'] ?? 'en')) === 'sw' ? 'sw' : 'en';

save_inbound($patientId, $channel, $from, $body, $payload);

if ($body === '') {
    echo 'OK';
    exit;
}

if (!$patientId) {
    send_unlinked_reply(
        $channel,
        $from,
        'Hi. To help you with PHV updates, please register your number with the hospital first. If this is urgent, contact the hospital directly.'
    );
    echo 'OK';
    exit;
}

$msg = strtoupper(trim($body));

// Still log an escalation request so staff are notified, but the patient reply
// itself is always produced by the AI assistant (see below).
if (str_contains($msg, 'DOCTOR') || str_contains($msg, 'DAKTARI')) {
    upsert_escalation($patientId, 'Patient requested direct doctor contact via messaging channel.');
}

// Every received message from a patient is answered by the AI assistant.
$ai = ai_generate_reply($patientId, $channel, $body, $lang);
if ($ai['ok']) {
    send_patient_message($patientId, 'system', $ai['reply']);
    echo 'OK';
    exit;
}

// Fallback only when AI is unavailable (no key / cURL): never leave a patient unanswered.
$greetings = ['HI', 'HELLO', 'HEY', 'HELLO!', 'HABARI', 'MAMBO', 'SAWA', 'NIAJE', 'JAMBO'];
$isGreeting = in_array($msg, $greetings, true);
foreach ($greetings as $g) {
    if (str_starts_with($msg, $g . ' ')) {
        $isGreeting = true;
        break;
    }
}

if ($isGreeting) {
    $reply = $lang === 'sw'
        ? 'Habari! Karibu ' . HOSPITAL_NAME . '. Ungependa kujua nini kuhusu PHV leo? Unaweza kuuliza kuhusu dalili, kuzuia, au miadi, au ujibu DAKTARI kupata msaada wa moja kwa moja wa hospitali.'
        : 'Hi! Welcome to ' . HOSPITAL_NAME . '. What would you like to know about PHV today? You can ask about signs, prevention, or appointments, or reply DOCTOR for direct hospital support.';
} else {
    $reply = $lang === 'sw'
        ? 'Asante kwa ujumbe wako. Tupo nawe. Kwa swali lolote la kiafya, tafadhali tembelea ' . HOSPITAL_NAME . '. Jibu MSAADA kupata mwongozo au DAKTARI kupata msaada wa moja kwa moja.'
        : 'Thank you for your message. We are here for you. For any medical question, please visit ' . HOSPITAL_NAME . '. Reply HELP for guidance or DOCTOR for direct hospital support.';
}
send_patient_message($patientId, 'system', $reply);
echo 'OK';
