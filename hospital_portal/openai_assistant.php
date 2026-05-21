<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function openai_enabled(): bool
{
    return OPENAI_API_KEY !== '';
}

/**
 * Detect language of user message (English or Swahili)
 */
function detect_language(string $text): string
{
    $text = strtolower(trim($text));
    
    // Common Swahili words and patterns
    $swahili_patterns = [
        'habari', 'jambo', 'sasa', 'nzuri', 'sijambo', 'asante', 'tafadhali',
        'samahani', 'karibu', 'ndiyo', 'hapana', 'sawa', 'pole', 'kwaheri',
        'lala', 'amka', 'chakula', 'maji', 'nyumba', 'kazi', 'shule',
        'daktari', 'hospitali', 'ugonjwa', 'dawa', 'afya', 'mgonjwa',
        'miadi', 'naweza', 'jinsi', 'gani', 'nini', 'wapi', 'lini',
        'kwanini', 'je', 'unaweza', 'tunaweza', 'tiba', 'matibabu',
        'dalili', 'kinga', 'chanjo', 'homa', 'maumivu', 'kikohozi',
        'homoni', 'kisukari', 'shinikizo', 'moyo', 'mapafu', 'utumbo'
    ];
    
    $score = 0;
    foreach ($swahili_patterns as $pattern) {
        if (strpos($text, $pattern) !== false) {
            $score++;
        }
    }
    
    // Check for Swahili greetings
    $swahili_greetings = ['habari', 'jambo', 'sasa', 'mambo', 'vipi', 'poa', 'safi'];
    foreach ($swahili_greetings as $greeting) {
        if (strpos($text, $greeting) !== false && strlen($text) < 30) {
            return 'sw';
        }
    }
    
    // If message contains Swahili patterns or is short with Swahili characteristics
    if ($score > 0 || (strlen($text) < 30 && $score >= 1)) {
        return 'sw';
    }
    
    return 'en';
}

/**
 * Get patient's preferred language from database
 */
function get_patient_language(int $patientId): string
{
    try {
        $st = db()->prepare('SELECT preferred_language FROM patients WHERE id = ?');
        $st->execute([$patientId]);
        $row = $st->fetch();
        if ($row && !empty($row['preferred_language'])) {
            return $row['preferred_language'];
        }
    } catch (Exception $e) {
        error_log("Failed to get patient language: " . $e->getMessage());
    }
    return 'en'; // Default to English
}

/**
 * Update patient's preferred language
 */
function update_patient_language(int $patientId, string $language): void
{
    try {
        $st = db()->prepare('UPDATE patients SET preferred_language = ? WHERE id = ?');
        $st->execute([$language, $patientId]);
        error_log("Updated patient {$patientId} language to {$language}");
    } catch (Exception $e) {
        error_log("Failed to update patient language: " . $e->getMessage());
    }
}

/**
 * Get language-specific system prompt
 */
function ai_system_prompt(string $language = 'en'): string
{
    if ($language === 'sw') {
        return 'Wewe ni msaidizi wa wagonjwa wa PHV katika ' . HOSPITAL_NAME . '. 
        Tabia yako: joto, mwenye huruma, mwenye matumaini, na mwenye ushauri wa vitendo.
        
        Kanuni:
        - Toa mwongozo mfupi unaoweza kutekelezeka na moyo wa kutia moyo
        - USItambue magonjwa mapya, USIagize mabadiliko ya dawa, USITOE ushauri hatari
        - Dalili zikionekana kuwa mbaya au za dharura, mwambie mgonjwa atafute matibabu ya haraka na awasiliane na hospitali
        - Wakati unaofaa, wakumbushe wagonjwa wanaweza kujibu DOCTOR kuwasiliana na wafanyakazi wa hospitali moja kwa moja
        - Jibu kwa Kiswahili cha kueleweka na rahisi (kama mtumiaji anaandika Kiswahili)
        - Weka majibu mafupi (sentensi 2-4) na yenye manufaa';
    }
    
    return 'You are a caring PHV patient support assistant for ' . HOSPITAL_NAME . '. 
    Tone must be warm, reassuring, hopeful, and practical.
    
    Guidelines:
    - Give short actionable guidance and encouragement
    - Do NOT diagnose new diseases, do NOT prescribe medication changes, and do NOT provide unsafe advice
    - If symptoms may be severe or emergency-like, tell patient to seek urgent care immediately and contact the hospital
    - When relevant, remind patients they can reply DOCTOR for direct staff contact
    - Keep responses brief (2-4 sentences) and helpful';
}

function ai_generate_reply(int $patientId, string $channel, string $patientText): array
{
    // Add debug logging
    error_log("AI generate reply called for patient {$patientId}, channel: {$channel}, text: {$patientText}");
    
    // Detect language from the patient's message
    $detectedLanguage = detect_language($patientText);
    error_log("Detected language: {$detectedLanguage}");
    
    
}

/**
 * Get fallback response when OpenAI is unavailable
 */
function get_fallback_response(string $message, string $language, int $patientId): string
{
    $message_lower = strtolower(trim($message));
    
    if ($language === 'sw') {
        // Appointment related
        if (strpos($message_lower, 'miadi') !== false || strpos($message_lower, 'appointment') !== false) {
            return "Miadi yako imepangwa. Tafadhali wasiliana nasi kwa simu au tembelea hospitali yetu. Je, ungependa kupanga miadi mpya?";
        }
        
        // Symptoms
        if (strpos($message_lower, 'dalili') !== false || strpos($message_lower, 'homa') !== false || strpos($message_lower, 'maumivu') !== false) {
            return "Dalili za tahadhari: homa kali (>39°C), ugumu wa kupumua, maumivu ya kifua, au kuzirai. Ikiwa una dalili hizi, tafuta matibabu mara moja. Una dalili gani hasa?";
        }
        
        // Prevention
        if (strpos($message_lower, 'kinga') !== false || strpos($message_lower, 'chanjo') !== false || strpos($message_lower, 'prevent') !== false) {
            return "Kinga bora: Tumia dawa kama ilivyoagizwa, hudhuria miadi yote, kunywa maji mengi, pumzika, na ripoti dalili mapema. Je, ungependa maelezo zaidi?";
        }
        
        // Greetings
        if (in_array($message_lower, ['habari', 'jambo', 'sasa', 'mambo', 'vipi', 'hi', 'hello', 'hey'])) {
            return "Habari! Karibu " . HOSPITAL_NAME . ". Ninafurahi kukusaidia. Unaweza kuniuliza kuhusu miadi, dalili za tahadhari, kinga, au kuwasiliana na daktari. Tuma HELP kwa chaguo zaidi.";
        }
        
        // Doctor contact
        if (strpos($message_lower, 'doctor') !== false || strpos($message_lower, 'daktari') !== false) {
            return "Ombi lako la kuwasiliana na daktari limepokelewa. Timu yetu itawasiliana nawe hivi karibuni. Kama ni dharura, tafadhali wasiliana nasi kwa simu au tembelea hospitali.";
        }
        
        // Thank you
        if (strpos($message_lower, 'asante') !== false) {
            return "Asante kwa kuwasiliana nasi! Karibu tena. Ukiwa na maswali zaidi, tafadhali uliza au tuma HELP. Tuko hapa kwa ajili yako 24/7.";
        }
        
        // Default response
        return "Asante kwa ujumbe wako. Tunajaribu kukusaidia. Unaweza kuniuliza kuhusu:\n1) Miadi\n2) Dalili za tahadhari\n3) Kinga na ushauri\n4) Kuwasiliana na daktari\n\nTuma HELP kwa chaguo zaidi.";
    }
    
    // English fallback responses
    else {
        // Appointment related
        if (strpos($message_lower, 'appointment') !== false || strpos($message_lower, 'book') !== false) {
            return "Your appointment has been scheduled. Please contact us by phone or visit our hospital. Would you like to schedule a new appointment?";
        }
        
        // Symptoms
        if (strpos($message_lower, 'symptom') !== false || strpos($message_lower, 'fever') !== false || strpos($message_lower, 'pain') !== false) {
            return "Warning symptoms: high fever (>39°C), difficulty breathing, chest pain, or fainting. If you have these symptoms, seek medical attention immediately. What specific symptoms are you experiencing?";
        }
        
        // Prevention
        if (strpos($message_lower, 'prevent') !== false || strpos($message_lower, 'vaccine') !== false) {
            return "Best prevention: Take prescribed medication, attend all appointments, stay hydrated, rest, and report symptoms early. Would you like more details?";
        }
        
        // Greetings
        if (in_array($message_lower, ['hi', 'hello', 'hey'])) {
            return "Hello! Welcome to " . HOSPITAL_NAME . ". I'm here to help you. You can ask me about appointments, warning symptoms, prevention tips, or contact a doctor. Reply HELP for more options.";
        }
        
        // Doctor contact
        if (strpos($message_lower, 'doctor') !== false) {
            return "Your request to contact a doctor has been received. Our team will reach out to you shortly. If this is an emergency, please call us or visit the hospital immediately.";
        }
        
        // Thank you
        if (strpos($message_lower, 'thank') !== false) {
            return "You're welcome! Feel free to reach out if you have any more questions. We're here for you 24/7. Reply HELP for more options.";
        }
        
        // Default response
        return "Thank you for your message. We're here to help. You can ask me about:\n1) Appointments\n2) Warning symptoms\n3) Prevention tips\n4) Contact doctor\n\nReply HELP for more options.";
    }
}

function ai_get_or_create_conversation(int $patientId, string $channel): int
{
    $lookup = db()->prepare(
        'SELECT id
         FROM ai_conversations
         WHERE patient_id = ? AND channel = ? AND closed_at IS NULL
         ORDER BY id DESC
         LIMIT 1'
    );
    $lookup->execute([$patientId, $channel]);
    $row = $lookup->fetch();
    if ($row) {
        return (int) $row['id'];
    }

    $insert = db()->prepare(
        'INSERT INTO ai_conversations (patient_id, channel, context_json)
         VALUES (?, ?, JSON_OBJECT("source", "africastalking-webhook"))'
    );
    $insert->execute([$patientId, $channel]);
    return (int) db()->lastInsertId();
}

function ai_log_turn(int $conversationId, string $role, string $content, ?string $model = null, ?string $language = null): void
{
    $st = db()->prepare(
        'INSERT INTO ai_turns (conversation_id, role, content, model, language)
         VALUES (?,?,?,?,?)'
    );
    $st->execute([$conversationId, $role, $content, $model, $language]);
}

/**
 * Returns latest turns as OpenAI chat format.
 */
function ai_recent_messages(int $conversationId, int $limit = 10): array
{
    $st = db()->prepare(
        'SELECT role, content
         FROM ai_turns
         WHERE conversation_id = ?
         ORDER BY id DESC
         LIMIT ?'
    );
    $st->bindValue(1, $conversationId, PDO::PARAM_INT);
    $st->bindValue(2, $limit, PDO::PARAM_INT);
    $st->execute();
    $rows = $st->fetchAll();
    $rows = array_reverse($rows);

    $messages = [];
    foreach ($rows as $row) {
        $messages[] = [
            'role' => (string) $row['role'],
            'content' => (string) $row['content'],
        ];
    }
    return $messages;
}

/**
 * Returns ['ok'=>bool, 'reply'=>string, 'error'=>?string, 'language'=>string]
 */
function ai_generate_reply(int $patientId, string $channel, string $patientText): array
{
    // Detect language from the patient's message
    $detectedLanguage = detect_language($patientText);
    
    // Get patient's stored language preference
    $storedLanguage = get_patient_language($patientId);
    
    // Use detected language if available, otherwise use stored preference
    $responseLanguage = $detectedLanguage !== 'en' ? $detectedLanguage : $storedLanguage;
    
    // Update patient's language preference if different
    if ($detectedLanguage !== 'en' && $storedLanguage !== $detectedLanguage) {
        update_patient_language($patientId, $detectedLanguage);
    }
    
    error_log("AI: Patient {$patientId} - Detected: {$detectedLanguage}, Response: {$responseLanguage}, Message: {$patientText}");
    
    // Try OpenAI if enabled
    if (openai_enabled() && function_exists('curl_init')) {
        $result = ai_generate_openai_reply($patientId, $channel, $patientText, $responseLanguage);
        if ($result['ok']) {
            return $result;
        }
        error_log("OpenAI failed: " . ($result['error'] ?? 'Unknown error'));
    }
    
    // Fallback to rule-based responses
    $fallbackReply = get_fallback_response($patientText, $responseLanguage, $patientId);
    
    // Log the fallback response
    $conversationId = ai_get_or_create_conversation($patientId, $channel);
    ai_log_turn($conversationId, 'user', $patientText, null, $detectedLanguage);
    ai_log_turn($conversationId, 'assistant', $fallbackReply, 'fallback', $responseLanguage);
    
    return [
        'ok' => true, 
        'reply' => $fallbackReply, 
        'error' => null,
        'language' => $responseLanguage,
        'fallback' => true
    ];
}

/**
 * Generate reply using OpenAI API with language support
 */
function ai_generate_openai_reply(int $patientId, string $channel, string $patientText, string $language): array
{
    $conversationId = ai_get_or_create_conversation($patientId, $channel);
    
    // Log user message with detected language
    ai_log_turn($conversationId, 'user', $patientText, null, $language);
    
    // Build messages array with language-specific system prompt
    $messages = [['role' => 'system', 'content' => ai_system_prompt($language)]];
    
    // Add conversation history (last 10 messages)
    $history = ai_recent_messages($conversationId, 10);
    foreach ($history as $m) {
        $messages[] = $m;
    }
    
    // Add current message if not already in history
    $lastMessage = end($messages);
    if (!$lastMessage || $lastMessage['content'] !== $patientText) {
        $messages[] = ['role' => 'user', 'content' => $patientText];
    }
    
    // Prepare OpenAI payload
    $payload = [
        'model' => OPENAI_MODEL,
        'messages' => $messages,
        'temperature' => 0.7,
        'max_tokens' => 300,
        'presence_penalty' => 0.6
    ];
    
    $ch = curl_init(OPENAI_BASE_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . OPENAI_API_KEY,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 20,
    ]);
    
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    
    if ($raw === false) {
        return ['ok' => false, 'reply' => '', 'error' => $err !== '' ? $err : 'Unknown cURL error', 'language' => $language];
    }
    
    $json = json_decode($raw, true);
    if ($code < 200 || $code >= 300) {
        $error = is_array($json) ? json_encode($json) : (string) $raw;
        return ['ok' => false, 'reply' => '', 'error' => 'OpenAI HTTP ' . $code . ': ' . $error, 'language' => $language];
    }
    
    $reply = '';
    if (isset($json['choices'][0]['message']['content'])) {
        $reply = trim((string) $json['choices'][0]['message']['content']);
    }
    
    // Ensure response is not empty
    if ($reply === '') {
        $reply = ($language === 'sw') 
            ? 'Asante kwa kuwasiliana nasi. Tunajaribu kukusaidia. Tuma DOCTOR kuwasiliana na daktari.'
            : 'Thank you for reaching out. We are here for you. Reply DOCTOR for direct hospital support.';
    }
    
    // Log assistant response with language
    ai_log_turn($conversationId, 'assistant', $reply, OPENAI_MODEL, $language);
    
    return [
        'ok' => true, 
        'reply' => $reply, 
        'error' => null,
        'language' => $language,
        'fallback' => false
    ];
}

/**
 * Process AI response for webhook (convenience function)
 */
function ai_process_webhook_message(int $patientId, string $channel, string $message): array
{
    $result = ai_generate_reply($patientId, $channel, $message);
    
    // Log the interaction for analytics
    try {
        $db = db();
        $st = $db->prepare(
            'INSERT INTO ai_interactions (patient_id, user_message, ai_response, language, channel, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())'
        );
        $st->execute([$patientId, $message, $result['reply'], $result['language'] ?? 'en', $channel]);
    } catch (Exception $e) {
        error_log("Failed to log AI interaction: " . $e->getMessage());
    }
    
    return $result;
}
?>
