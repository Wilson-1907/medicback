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

// Log the incoming message
error_log("Webhook received: From={$from}, Body={$body}, Channel={$channel}, PatientId={$patientId}");

save_inbound($patientId, $channel, $from, $body, $payload);

if ($body === '') {
    error_log("Empty message body, exiting");
    echo 'OK';
    exit;
}

// Handle unregistered patients
if (!$patientId) {
    $unlinkedReply = "Hi. To help you with PHV updates, please register your number with the hospital first. If this is urgent, contact the hospital directly.";
    // Check if message might be Swahili
    $body_lower = strtolower($body);
    if (strpos($body_lower, 'habari') !== false || strpos($body_lower, 'jambo') !== false || strpos($body_lower, 'asante') !== false) {
        $unlinkedReply = "Habari. Ili kukusaidia kwa taarifa za PHV, tafadhali sajili nambari yako hospitalini kwanza. Ikiwa ni dharura, wasiliana na hospitali moja kwa moja.";
    }
    send_unlinked_reply($channel, $from, $unlinkedReply);
    error_log("Sent unlinked reply to {$from}");
    echo 'OK';
    exit;
}

$msg = strtoupper(trim($body));

// Handle specific commands first (these don't need AI)
if (in_array($msg, ['HI', 'HELLO', 'HEY', 'MAMBO', 'SAWA', 'JAMBO', 'HABARI'], true)) {
    // Get greeting in patient's language
    $stmt = db()->prepare("SELECT preferred_language FROM patients WHERE id = ?");
    $stmt->execute([$patientId]);
    $patientData = $stmt->fetch();
    $lang = ($patientData && $patientData['preferred_language']) ? $patientData['preferred_language'] : 'en';
    
    $greeting = ($lang === 'sw') 
        ? "Habari! Karibu " . HOSPITAL_NAME . ". Je, nikusaidieje leo? Unaweza kuniuliza kuhusu miadi, dalili za tahadhari, kinga, au kuwasiliana na daktari. Tuma HELP kwa chaguo zaidi."
        : "Hello! Welcome to " . HOSPITAL_NAME . ". How can I help you today? You can ask about appointments, warning symptoms, prevention tips, or contact a doctor. Reply HELP for more options.";
    
    send_patient_message($patientId, 'system', $greeting);
    error_log("Sent greeting to patient {$patientId}");
    echo 'OK';
    exit;
}

if ($msg === 'HELP' || $msg === 'MENU' || $msg === '0') {
    $stmt = db()->prepare("SELECT preferred_language FROM patients WHERE id = ?");
    $stmt->execute([$patientId]);
    $patientData = $stmt->fetch();
    $lang = ($patientData && $patientData['preferred_language']) ? $patientData['preferred_language'] : 'en';
    
    $menu = ($lang === 'sw') 
        ? "MENU YA HUDUMA:\n1) Dalili za tahadhari\n2) Kinga na ushauri\n3) Miadi yangu\n4) Wasiliana na daktari\n\nTuma namba ili kupata maelezo."
        : "HELP MENU:\n1) Warning symptoms\n2) Prevention tips\n3) My appointments\n4) Contact doctor\n\nSend number for details.";
    
    send_patient_message($patientId, 'education_menu', $menu);
    error_log("Sent menu to patient {$patientId}");
    echo 'OK';
    exit;
}

if ($msg === '1') {
    $stmt = db()->prepare("SELECT preferred_language FROM patients WHERE id = ?");
    $stmt->execute([$patientId]);
    $patientData = $stmt->fetch();
    $lang = ($patientData && $patientData['preferred_language']) ? $patientData['preferred_language'] : 'en';
    
    $response = ($lang === 'sw')
        ? "DALILI ZA TAHADHARI: Homa kali (>39°C), ugumu wa kupumua, maumivu ya kifua, kuzirai, au kutokwa na damu isiyo kawaida. Ikiwa una dalili hizi, tafuta matibabu mara moja."
        : "WARNING SYMPTOMS: High fever (>39°C), difficulty breathing, chest pain, fainting, or unusual bleeding. If you have these symptoms, seek emergency care immediately.";
    
    send_patient_message($patientId, 'system', $response);
    error_log("Sent symptoms info to patient {$patientId}");
    echo 'OK';
    exit;
}

if ($msg === '2') {
    $stmt = db()->prepare("SELECT preferred_language FROM patients WHERE id = ?");
    $stmt->execute([$patientId]);
    $patientData = $stmt->fetch();
    $lang = ($patientData && $patientData['preferred_language']) ? $patientData['preferred_language'] : 'en';
    
    $response = ($lang === 'sw')
        ? "KINGA BORA: Tumia dawa kama ilivyoagizwa, hudhuria miadi yote, kunywa maji mengi, pumzika, na ripoti dalili mapema. Tuma HELP kwa chaguo zaidi."
        : "PREVENTION TIPS: Take prescribed medication, attend all appointments, stay hydrated, rest, and report symptoms early. Reply HELP for more options.";
    
    send_patient_message($patientId, 'system', $response);
    error_log("Sent prevention tips to patient {$patientId}");
    echo 'OK';
    exit;
}

if ($msg === '3') {
    $stmt = db()->prepare("SELECT preferred_language FROM patients WHERE id = ?");
    $stmt->execute([$patientId]);
    $patientData = $stmt->fetch();
    $lang = ($patientData && $patientData['preferred_language']) ? $patientData['preferred_language'] : 'en';
    
    $response = ($lang === 'sw')
        ? "KUANGALIA MIADI: Tuma nambari yako ya kitambulisho kupata miadi yako ijayo. Au wasiliana nasi kwa simu " . HOSPITAL_PHONE . "."
        : "CHECK APPOINTMENTS: Send your patient ID to get your next appointment. Or contact us at " . HOSPITAL_PHONE . ".";
    
    send_patient_message($patientId, 'system', $response);
    error_log("Sent appointment info to patient {$patientId}");
    echo 'OK';
    exit;
}

if ($msg === 'DOCTOR' || $msg === '4') {
    upsert_escalation($patientId, 'Patient requested direct doctor contact via messaging channel.');
    
    $stmt = db()->prepare("SELECT preferred_language FROM patients WHERE id = ?");
    $stmt->execute([$patientId]);
    $patientData = $stmt->fetch();
    $lang = ($patientData && $patientData['preferred_language']) ? $patientData['preferred_language'] : 'en';
    
    $response = ($lang === 'sw')
        ? "Asante. Ombi lako la kuwasiliana na daktari limepokelewa. Timu yetu itawasiliana nawe hivi karibuni. Kama ni dharura, tafadhali wasiliana nasi kwa simu."
        : "Thank you. Your request to contact a doctor has been received. Our team will contact you shortly. If this is an emergency, please call us.";
    
    send_patient_message($patientId, 'escalation_notice', $response);
    error_log("Sent escalation notice to patient {$patientId}");
    echo 'OK';
    exit;
}

// ============================================
// FOR ALL OTHER MESSAGES - USE AI
// ============================================
error_log("Sending message to AI for patient {$patientId}: {$body}");

try {
    // Call the AI function to generate a reply
    $aiResult = ai_generate_reply($patientId, $channel, $body);
    
    error_log("AI Result: ok=" . ($aiResult['ok'] ? 'true' : 'false') . ", language=" . ($aiResult['language'] ?? 'unknown'));
    
    if ($aiResult['ok'] && !empty($aiResult['reply'])) {
        // Send the AI response back to the patient
        send_patient_message($patientId, 'system', $aiResult['reply']);
        error_log("AI response sent to patient {$patientId}: " . substr($aiResult['reply'], 0, 100) . "...");
    } else {
        // Fallback response if AI failed
        $errorMsg = $aiResult['error'] ?? 'Unknown error';
        error_log("AI generation failed: {$errorMsg}");
        
        // Get patient's language for fallback
        $stmt = db()->prepare("SELECT preferred_language FROM patients WHERE id = ?");
        $stmt->execute([$patientId]);
        $patientData = $stmt->fetch();
        $lang = ($patientData && $patientData['preferred_language']) ? $patientData['preferred_language'] : 'en';
        
        $fallbackReply = ($lang === 'sw')
            ? "Samahani, kuna hitilafu ya kiufundi. Tafadhali jaribu tena baadaye au wasiliana nasi kwa simu. Tuma HELP kwa chaguo zaidi."
            : "Sorry, there's a technical issue. Please try again later or contact us by phone. Reply HELP for more options.";
        
        send_patient_message($patientId, 'system', $fallbackReply);
        error_log("Sent fallback response to patient {$patientId}");
    }
    
} catch (Throwable $e) {
    error_log("AI Exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    
    // Ultimate fallback
    $stmt = db()->prepare("SELECT preferred_language FROM patients WHERE id = ?");
    $stmt->execute([$patientId]);
    $patientData = $stmt->fetch();
    $lang = ($patientData && $patientData['preferred_language']) ? $patientData['preferred_language'] : 'en';
    
    $emergencyReply = ($lang === 'sw')
        ? "Samahani, tumepata hitilafu. Timu yetu imejulishwa. Tafadhali wasiliana nasi kwa simu " . HOSPITAL_PHONE . " kwa msaada wa haraka."
        : "Sorry, we encountered an error. Our team has been notified. Please contact us at " . HOSPITAL_PHONE . " for immediate assistance.";
    
    send_patient_message($patientId, 'system', $emergencyReply);
}

echo 'OK';
exit;
?>
