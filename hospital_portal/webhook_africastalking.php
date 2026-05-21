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

// Get language-specific greeting based on patient's preferred language
function get_greeting_response(int $patientId, string $channel, string $message): ?string
{
    $db = db();
    $stmt = $db->prepare("SELECT preferred_language FROM patients WHERE id = ?");
    $stmt->execute([$patientId]);
    $patient = $stmt->fetch();
    $lang = $patient['preferred_language'] ?? detect_language($message);
    
    if ($lang === 'sw') {
        return "Habari! Karibu " . HOSPITAL_NAME . ". Je, nikusaidieje leo? Unaweza kuniuliza kuhusu miadi, dalili za tahadhari, kinga, au kuwasiliana na daktari. Tuma HELP kwa chaguo zaidi.";
    }
    return "Hello! Welcome to " . HOSPITAL_NAME . ". How can I help you today? You can ask about appointments, warning symptoms, prevention tips, or contact a doctor. Reply HELP for more options.";
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
    // Patient not registered - send registration message
    $unlinkedReply = "Hi. To help you with PHV updates, please register your number with the hospital first. If this is urgent, contact the hospital directly.";
    if (detect_language($body) === 'sw') {
        $unlinkedReply = "Habari. Ili kukusaidia kwa taarifa za PHV, tafadhali sajili nambari yako hospitalini kwanza. Ikiwa ni dharura, wasiliana na hospitali moja kwa moja.";
    }
    send_unlinked_reply($channel, $from, $unlinkedReply);
    echo 'OK';
    exit;
}

$msg = strtoupper(trim($body));

// Get patient's preferred language for responses
$stmt = db()->prepare("SELECT preferred_language FROM patients WHERE id = ?");
$stmt->execute([$patientId]);
$patientData = $stmt->fetch();
$userLanguage = $patientData['preferred_language'] ?? detect_language($body);

// Simple command handlers with language support
if (in_array($msg, ['HI', 'HELLO', 'HEY', 'MAMBO', 'SAWA', 'JAMBO', 'HABARI'], true)) {
    $greeting = get_greeting_response($patientId, $channel, $body);
    send_patient_message($patientId, 'system', $greeting);
    echo 'OK';
    exit;
}

if ($msg === 'HELP' || $msg === 'MENU' || $msg === '0') {
    $menuMessage = ($userLanguage === 'sw') 
        ? "MENU YA HUDUMA:\n1) Dalili za tahadhari\n2) Kinga na ushauri\n3) Miadi yangu\n4) Wasiliana na daktari\n\nTuma namba ili kupata maelezo."
        : "HELP MENU:\n1) Warning symptoms\n2) Prevention tips\n3) My appointments\n4) Contact doctor\n\nSend number for details.";
    send_patient_message($patientId, 'education_menu', $menuMessage);
    echo 'OK';
    exit;
}

if ($msg === '1') {
    $response = ($userLanguage === 'sw')
        ? "DALILI ZA TAHADHARI: Homa kali (>39°C), ugumu wa kupumua, maumivu ya kifua, kuzirai, au kutokwa na damu isiyo kawaida. Ikiwa una dalili hizi, tafuta matibabu mara moja."
        : "WARNING SYMPTOMS: High fever (>39°C), difficulty breathing, chest pain, fainting, or unusual bleeding. If you have these symptoms, seek emergency care immediately.";
    send_patient_message($patientId, 'system', $response);
    echo 'OK';
    exit;
}

if ($msg === '2') {
    $response = ($userLanguage === 'sw')
        ? "KINGA BORA: Tumia dawa kama ilivyoagizwa, hudhuria miadi yote, kunywa maji mengi, pumzika, na ripoti dalili mapema. Tuma HELP kwa chaguo zaidi."
        : "PREVENTION TIPS: Take prescribed medication, attend all appointments, stay hydrated, rest, and report symptoms early. Reply HELP for more options.";
    send_patient_message($patientId, 'system', $response);
    echo 'OK';
    exit;
}

if ($msg === '3') {
    $response = ($userLanguage === 'sw')
        ? "KUANGALIA MIADI: Tuma nambari yako ya kitambulisho kupata miadi yako ijayo. Au wasiliana nasi kwa simu " . HOSPITAL_PHONE . "."
        : "CHECK APPOINTMENTS: Send your patient ID to get your next appointment. Or contact us at " . HOSPITAL_PHONE . ".";
    send_patient_message($patientId, 'system', $response);
    echo 'OK';
    exit;
}

if ($msg === 'DOCTOR' || $msg === '4') {
    upsert_escalation($patientId, 'Patient requested direct doctor contact via messaging channel.');
    $response = ($userLanguage === 'sw')
        ? "Asante. Ombi lako limetumwa kwa " . HOSPITAL_NAME . ". Timu yetu itawasiliana nawe hivi karibuni."
        : "Thank you. Your request has been sent to " . HOSPITAL_NAME . ". A care team member will contact you shortly.";
    send_patient_message($patientId, 'escalation_notice', $response);
    echo 'OK';
    exit;
}

// For all other messages, use AI to generate response in the user's language
$ai = ai_generate_reply($patientId, $channel, $body);

if ($ai['ok']) {
    send_patient_message($patientId, 'system', $ai['reply']);
    // Log the AI conversation for analytics
    log_ai_conversation($patientId, $body, $ai['reply'], $ai['language']);
    echo 'OK';
    exit;
}

// Ultimate fallback
$fallbackResponse = ($userLanguage === 'sw')
    ? "Asante kwa ujumbe wako. Tunajaribu kukusaidia. Tuma HELP kwa chaguo au DOCTOR kuwasiliana na daktari."
    : "Thank you for your message. We're here to help. Reply HELP for options or DOCTOR to contact a doctor.";

send_patient_message($patientId, 'system', $fallbackResponse);
echo 'OK';
?>
