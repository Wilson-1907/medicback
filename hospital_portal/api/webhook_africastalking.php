<?php
/**
 * Professional Africa's Talking Webhook Handler
 * Handles both SMS and WhatsApp incoming messages
 */

// Enable error logging for debugging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/at_webhook.log');

// Log every incoming request for debugging
$requestId = uniqid('at_', true);
$logEntry = [
    'request_id' => $requestId,
    'timestamp' => date('Y-m-d H:i:s'),
    'method' => $_SERVER['REQUEST_METHOD'],
    'headers' => getallheaders(),
    'get_params' => $_GET,
    'post_params' => $_POST,
    'raw_input' => file_get_contents('php://input'),
    'server_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
];

// Write to debug log
$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
file_put_contents($logDir . '/at_incoming.log', json_encode($logEntry, JSON_PRETTY_PRINT) . "\n\n", FILE_APPEND);

// Always return OK to Africa's Talking first (prevents timeouts)
header('Content-Type: text/plain');
echo "OK";

// Flush output to ensure AT gets the response immediately
if (ob_get_level()) ob_end_flush();
flush();

// Now process the message asynchronously (if possible)
// For shared hosting without async, we process normally

try {
    require_once __DIR__ . '/bootstrap.php';
    require_once __DIR__ . '/messaging.php';
    require_once __DIR__ . '/openai_assistant.php';
    
    // Extract message data
    $from = extract_from_number($logEntry['post_params']);
    $message = extract_message_text($logEntry['post_params']);
    $channel = detect_channel($logEntry['post_params']);
    
    if (empty($message)) {
        file_put_contents($logDir . '/at_incoming.log', "[{$requestId}] Empty message, ignored\n", FILE_APPEND);
        exit;
    }
    
    file_put_contents($logDir . '/at_incoming.log', "[{$requestId}] Processing: From={$from}, Message={$message}, Channel={$channel}\n", FILE_APPEND);
    
    // Find or create patient
    $patientId = find_or_create_patient($from, $channel);
    
    if (!$patientId) {
        // Send unregistered response
        $reply = "Thank you for your message. To better assist you, please register at our hospital first. For emergencies, call " . HOSPITAL_PHONE;
        if (strpos(strtolower($message), 'habari') !== false) {
            $reply = "Asante kwa ujumbe wako. Ili kukusaidia vizuri, tafadhali sajili hospitalini kwanza. Kwa dharura, piga simu " . HOSPITAL_PHONE;
        }
        send_immediate_reply($channel, $from, $reply);
        file_put_contents($logDir . '/at_incoming.log', "[{$requestId}] Sent unregistered reply\n", FILE_APPEND);
        exit;
    }
    
    // Save inbound message
    save_inbound_message($patientId, $channel, $from, $message);
    
    // Generate AI response
    $aiResponse = generate_ai_response($patientId, $channel, $message);
    
    // Send response back to patient
    send_patient_message($patientId, $aiResponse, $channel);
    
    file_put_contents($logDir . '/at_incoming.log', "[{$requestId}] Sent AI response: " . substr($aiResponse, 0, 100) . "...\n", FILE_APPEND);
    
} catch (Throwable $e) {
    file_put_contents($logDir . '/at_incoming.log', "[{$requestId}] ERROR: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n", FILE_APPEND);
    
    // Try to send error response to user
    if (isset($from) && isset($channel)) {
        $errorReply = "We're experiencing technical issues. Please try again later or call " . HOSPITAL_PHONE . " for assistance.";
        send_immediate_reply($channel, $from, $errorReply);
    }
}

// Helper functions
function extract_from_number($data): string
{
    $possibleKeys = ['from', 'fromNumber', 'source', 'sender', 'phoneNumber'];
    foreach ($possibleKeys as $key) {
        if (!empty($data[$key])) {
            $number = preg_replace('/\D/', '', $data[$key]);
            if (strlen($number) >= 9) {
                return '+' . $number;
            }
        }
    }
    return '';
}

function extract_message_text($data): string
{
    $possibleKeys = ['text', 'message', 'body', 'content'];
    foreach ($possibleKeys as $key) {
        if (!empty($data[$key])) {
            return trim($data[$key]);
        }
    }
    return '';
}

function detect_channel($data): string
{
    if (!empty($data['channel']) && strtolower($data['channel']) === 'whatsapp') {
        return 'whatsapp';
    }
    if (!empty($data['to']) && strpos(strtolower($data['to']), 'whatsapp') !== false) {
        return 'whatsapp';
    }
    return 'sms';
}

function find_or_create_patient(string $phone, string $channel): ?int
{
    try {
        $db = db();
        
        // Try to find existing patient
        $stmt = $db->prepare("
            SELECT p.id 
            FROM patients p
            JOIN contact_channels c ON p.id = c.patient_id
            WHERE c.address LIKE ? OR REPLACE(c.address, '+', '') = ?
            LIMIT 1
        ");
        $cleanPhone = ltrim($phone, '+');
        $stmt->execute(["%{$cleanPhone}%", $cleanPhone]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($patient) {
            return (int)$patient['id'];
        }
        
        // Auto-create patient for testing (optional - remove in production)
        // Comment this out in production - only for testing
        $stmt = $db->prepare("
            INSERT INTO patients (full_name, phone, status, registration_at) 
            VALUES (?, ?, 'active', NOW())
        ");
        $tempName = "Patient_" . substr($cleanPhone, -4);
        $stmt->execute([$tempName, $phone]);
        
        $patientId = $db->lastInsertId();
        
        // Add contact channel
        $stmt = $db->prepare("
            INSERT INTO contact_channels (patient_id, type, address, is_primary) 
            VALUES (?, ?, ?, 1)
        ");
        $stmt->execute([$patientId, $channel, $phone]);
        
        return $patientId;
        
    } catch (Exception $e) {
        error_log("Find/Create patient error: " . $e->getMessage());
        return null;
    }
}

function save_inbound_message(int $patientId, string $channel, string $from, string $message): void
{
    try {
        $db = db();
        $stmt = $db->prepare("
            INSERT INTO inbound_messages (patient_id, channel, from_address, body, received_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$patientId, $channel, $from, $message]);
    } catch (Exception $e) {
        error_log("Save inbound error: " . $e->getMessage());
    }
}

function generate_ai_response(int $patientId, string $channel, string $message): string
{
    try {
        if (function_exists('ai_generate_reply')) {
            $result = ai_generate_reply($patientId, $channel, $message);
            if ($result['ok'] && !empty($result['reply'])) {
                return $result['reply'];
            }
        }
        
        // Fallback responses
        $msgLower = strtolower(trim($message));
        
        // Get patient language preference
        $db = db();
        $stmt = $db->prepare("SELECT preferred_language FROM patients WHERE id = ?");
        $stmt->execute([$patientId]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
        $lang = ($patient && $patient['preferred_language']) ? $patient['preferred_language'] : 'en';
        
        // Check for help commands
        if (in_array($msgLower, ['help', 'menu', '0'])) {
            return ($lang === 'sw') 
                ? "MENU:\n1) Dalili\n2) Kinga\n3) Miadi\n4) Daktari\nTuma namba kwa maelezo."
                : "MENU:\n1) Symptoms\n2) Prevention\n3) Appointments\n4) Doctor\nSend number for details.";
        }
        
        if ($msgLower === '1') {
            return ($lang === 'sw')
                ? "DALILI: Homa kali, ugumu wa kupumua, maumivu ya kifua. Ikiwa unazo, tafuta matibabu mara moja."
                : "SYMPTOMS: High fever, difficulty breathing, chest pain. If you have these, seek care immediately.";
        }
        
        if ($msgLower === '2') {
            return ($lang === 'sw')
                ? "KINGA: Tumia dawa kwa wakati, hudhuria miadi, pumzika, na ripoti dalili mapema."
                : "PREVENTION: Take medication on time, attend appointments, rest, and report symptoms early.";
        }
        
        if ($msgLower === '3') {
            return ($lang === 'sw')
                ? "MIADI: Wasiliana nasi kwa simu " . HOSPITAL_PHONE . " kuangalia au kupanga miadi."
                : "APPOINTMENTS: Contact us at " . HOSPITAL_PHONE . " to check or schedule appointments.";
        }
        
        if ($msgLower === '4' || $msgLower === 'doctor') {
            return ($lang === 'sw')
                ? "Ombi lako limepokelewa. Daktari atawasiliana nawe hivi karibuni."
                : "Your request has been received. A doctor will contact you shortly.";
        }
        
        // Default response
        return ($lang === 'sw')
            ? "Asante kwa ujumbe wako. Tuko hapa kukusaidia. Tuma HELP kwa chaguo au DOCTOR kuwasiliana na daktari."
            : "Thank you for your message. We're here to help. Reply HELP for options or DOCTOR to contact a doctor.";
            
    } catch (Exception $e) {
        error_log("AI response error: " . $e->getMessage());
        return "Thank you for your message. We'll get back to you shortly. Reply HELP for assistance.";
    }
}

function send_immediate_reply(string $channel, string $to, string $message): void
{
    try {
        if (function_exists('africastalking_send')) {
            africastalking_send($channel, $to, $message);
        }
    } catch (Exception $e) {
        error_log("Send reply error: " . $e->getMessage());
    }
}

function send_patient_message(int $patientId, string $message, string $channel): void
{
    try {
        if (function_exists('send_patient_message')) {
            send_patient_message($patientId, 'system', $message);
        } else {
            // Get patient phone and send directly
            $db = db();
            $stmt = $db->prepare("
                SELECT c.address 
                FROM contact_channels c
                WHERE c.patient_id = ? AND c.type = ?
                LIMIT 1
            ");
            $stmt->execute([$patientId, $channel]);
            $contact = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($contact && function_exists('africastalking_send')) {
                africastalking_send($channel, $contact['address'], $message);
            }
        }
    } catch (Exception $e) {
        error_log("Send patient message error: " . $e->getMessage());
    }
}
?>
