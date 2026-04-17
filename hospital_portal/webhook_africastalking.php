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
        'SELECT p.id, p.full_name
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

$msg = strtoupper($body);
if (in_array($msg, ['HI', 'HELLO', 'HEY', 'MAMBO', 'SAWA'], true)) {
    send_patient_message(
        $patientId,
        'system',
        'Hi. What do you want to know about PHV today? You can ask about signs, prevention, appointments, or reply DOCTOR for direct hospital support.'
    );
    echo 'OK';
    exit;
}

if ($msg === 'HELP' || $msg === 'MENU' || $msg === '0') {
    send_patient_message($patientId, 'education_menu', build_engagement_menu_message());
    echo 'OK';
    exit;
}

if ($msg === '1') {
    send_patient_message(
        $patientId,
        'system',
        'PHV signs to watch: sudden severe symptoms, high fever, persistent pain, worsening breathing, or unusual bleeding. '
        . 'If symptoms are severe, seek emergency care immediately.'
    );
    echo 'OK';
    exit;
}

if ($msg === '2') {
    send_patient_message(
        $patientId,
        'system',
        'PHV prevention tips: take prescribed medication, keep follow-up visits, stay hydrated, rest, and report any worsening signs early. '
        . 'Reply HELP for more options.'
    );
    echo 'OK';
    exit;
}

if ($msg === 'DOCTOR' || $msg === '4') {
    upsert_escalation($patientId, 'Patient requested direct doctor contact via messaging channel.');
    send_patient_message(
        $patientId,
        'escalation_notice',
        'Thank you. Your request has been sent to ' . HOSPITAL_NAME . '. A care team member will contact you shortly.'
    );
    echo 'OK';
    exit;
}

if (str_contains($msg, 'PHV')) {
    send_patient_message(
        $patientId,
        'system',
        'PHV is a health condition that needs close follow-up, early symptom reporting, and prevention support. '
        . 'At ' . HOSPITAL_NAME . ', we help you with appointment reminders, warning signs, and practical prevention guidance. '
        . 'If you feel worse or have severe symptoms, seek urgent care immediately. Reply DOCTOR for direct hospital contact.'
    );
    echo 'OK';
    exit;
}

$ai = ai_generate_reply($patientId, $channel, $body);
if ($ai['ok']) {
    send_patient_message($patientId, 'system', $ai['reply']);
    echo 'OK';
    exit;
}

send_patient_message(
    $patientId,
    'system',
    'Thank you for your message. We are here for you. Reply HELP for PHV guidance or DOCTOR for direct hospital support.'
);
echo 'OK';
