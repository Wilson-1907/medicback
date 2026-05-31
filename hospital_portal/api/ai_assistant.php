<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

header('Content-Type: application/json');

try {
    $pdo = db();
    $data = json_decode(file_get_contents('php://input'), true);
    
    $message = $data['message'] ?? '';
    $patientId = $data['patient_id'] ?? null;
    $channel = $data['channel'] ?? 'sms';
    $fromNumber = $data['from_number'] ?? '';
    
    if (empty($message)) {
        api_json(['ok' => false, 'error' => 'Message is required'], 400);
    }
    
    // Detect language of the incoming message
    $detectedLanguage = detectLanguage($message);
    
    // Get AI response in detected language
    $aiResponse = getAIResponse($message, $detectedLanguage);
    
    // Store the AI response
    if ($patientId) {
        $stmt = $pdo->prepare(
            "INSERT INTO outbound_messages (patient_id, channel, message_type, body, status, created_at) 
             VALUES (?, ?, 'ai_assistant', ?, 'sent', NOW())"
        );
        $stmt->execute([$patientId, $channel, $aiResponse]);
        
        // Log the interaction
        logAIConversation($pdo, $patientId, $message, $aiResponse, $detectedLanguage);
    }
    
    api_json([
        'ok' => true,
        'response' => $aiResponse,
        'detected_language' => $detectedLanguage,
        'original_message' => $message
    ]);
    
} catch (Throwable $e) {
    error_log("AI Assistant Error: " . $e->getMessage());
    api_json([
        'ok' => false, 
        'error' => $e->getMessage(),
        'response' => getFallbackResponse($detectedLanguage ?? 'en')
    ], 500);
}

// Language detection function
function detectLanguage($text) {
    // Common Swahili words and patterns
    $swahiliPatterns = [
        '/habari/i', '/jambo/i', '/sasa/i', '/nzuri/i', '/sijambo/i',
        '/asante/i', '/tafadhali/i', '/samahani/i', '/karibu/i',
        '/ndiyo/i', '/hapana/i', '/sawa/i', '/pole/i', '/kwaheri/i',
        '/lala/i', '/amka/i', '/chakula/i', '/maji/i', '/nyumba/i',
        '/kazi/i', '/shule/i', '/daktari/i', '/hospitali/i', '/ugonjwa/i',
        '/dawa/i', '/afya/i', '/mgonjwa/i', '/miadi/i', '/naweza/i',
        '/jinsi/i', '/gani/i', '/nini/i', '/wapi/i', '/lini/i',
        '/kwanini/i', '/je/i', '/unaweza/i', '/tunaweza/i'
    ];
    
    $text_lower = strtolower($text);
    $swahiliScore = 0;
    
    foreach ($swahiliPatterns as $pattern) {
        if (preg_match($pattern, $text_lower)) {
            $swahiliScore++;
        }
    }
    
    // If contains Swahili patterns, return Swahili
    if ($swahiliScore > 0) {
        return 'sw';
    }
    
    // Default to English
    return 'en';
}

// Get AI response using OpenAI or fallback logic
function getAIResponse($message, $language) {
    // Try to use OpenAI API if configured
    $openaiKey = getenv('OPENAI_API_KEY');
    
    if ($openaiKey && function_exists('curl_init')) {
        return getOpenAIResponse($message, $language, $openaiKey);
    }
    
    // Fallback to rule-based responses
    return getRuleBasedResponse($message, $language);
}

// OpenAI integration
function getOpenAIResponse($message, $language, $apiKey) {
    $systemPrompt = ($language === 'sw') 
        ? "Wewe ni msaidizi wa afya wa hospitali ya Nyeri Level 4. Jibu maswali kuhusu afya, miadi, dalili, na huduma za hospitali. Jibu kwa Kiswahili kwa urahisi na kitaalamu."
        : "You are a health assistant for Nyeri Level 4 Hospital. Answer questions about health, appointments, symptoms, and hospital services. Be helpful, professional, and concise.";
    
    $data = [
        'model' => 'gpt-3.5-turbo',
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $message]
        ],
        'temperature' => 0.7,
        'max_tokens' => 500,
        'presence_penalty' => 0.6
    ];
    
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $result = json_decode($response, true);
        return $result['choices'][0]['message']['content'] ?? getFallbackResponse($language);
    }
    
    error_log("OpenAI API error: HTTP $httpCode - $response");
    return getFallbackResponse($language);
}

// Rule-based responses (fallback when OpenAI not available)
function getRuleBasedResponse($message, $language) {
    $message_lower = strtolower($message);
    
    // Swahili responses
    if ($language === 'sw') {
        // Appointment related
        if (strpos($message_lower, 'miadi') !== false || strpos($message_lower, 'appointment') !== false) {
            return "Miadi yako imepangwa. Tafadhali wasiliana nasi kwa nambari +254700000000 au tembelea hospitali yetu kwa huduma zaidi. Je, ungependa kupanga miadi mpya?";
        }
        
        // Symptoms
        if (strpos($message_lower, 'dalili') !== false || strpos($message_lower, 'symptoms') !== false) {
            return "Dalili za tahadhari ni pamoja na: homa kali, ugumu wa kupumua, maumivu ya kifua, au kuzirai. Ikiwa una dalili hizi, tafuta matibabu mara moja. Je, una dalili gani hasa?";
        }
        
        // Prevention
        if (strpos($message_lower, 'kinga') !== false || strpos($message_lower, 'prevent') !== false) {
            return "Kwa kinga bora: Osha mikono mara kwa mara, pata chanjo, kula vyakula bora, fanya mazoezi, na fuata ushauri wa daktari wako. Je, ungependa maelezo zaidi?";
        }
        
        // Greeting
        if (strpos($message_lower, 'habari') !== false || strpos($message_lower, 'jambo') !== false || strpos($message_lower, 'sasa') !== false) {
            return "Habari! Karibu Hospitali ya Nyeri Level 4. Ninafurahi kukusaidia. Unaweza kuniuliza kuhusu miadi, dalili, kinga, au huduma zetu. Je, nikusaidieje leo?";
        }
        
        // Thank you
        if (strpos($message_lower, 'asante') !== false) {
            return "Asante kwa kuwasiliana nasi! Karibu tena. Ukiwa na maswali zaidi, tafadhali uliza. Tuko hapa kukusaidia.";
        }
        
        // Default response
        return "Asante kwa ujumbe wako. Je, unaweza kuelezea zaidi ili nikusaidie vizuri? Unaweza kuniuliza kuhusu: miadi, dalili za tahadhari, kinga, au huduma za hospitali.";
    }
    
    // English responses
    else {
        // Appointment related
        if (strpos($message_lower, 'appointment') !== false || strpos($message_lower, 'book') !== false || strpos($message_lower, 'schedule') !== false) {
            return "Your appointment has been scheduled. Please contact us at +254700000000 or visit our hospital for more services. Would you like to schedule a new appointment?";
        }
        
        // Symptoms
        if (strpos($message_lower, 'symptom') !== false || strpos($message_lower, 'pain') !== false || strpos($message_lower, 'fever') !== false) {
            return "Warning symptoms include: high fever, difficulty breathing, chest pain, or fainting. If you have these symptoms, seek medical attention immediately. What specific symptoms are you experiencing?";
        }
        
        // Prevention
        if (strpos($message_lower, 'prevent') !== false || strpos($message_lower, 'avoid') !== false || strpos($message_lower, 'protect') !== false) {
            return "For best prevention: Wash hands regularly, get vaccinated, eat healthy, exercise, and follow your doctor's advice. Would you like more details?";
        }
        
        // Greeting/Help
        if (strpos($message_lower, 'hello') !== false || strpos($message_lower, 'hi') !== false || strpos($message_lower, 'hey') !== false) {
            return "Hello! Welcome to Nyeri Level 4 Hospital. I'm here to help you. You can ask me about appointments, symptoms, prevention tips, or our services. How can I assist you today?";
        }
        
        // Thank you
        if (strpos($message_lower, 'thank') !== false) {
            return "You're welcome! Feel free to reach out if you have any more questions. We're here to help with your healthcare needs.";
        }
        
        // Default response
        return "Thank you for your message. Could you please provide more details so I can better assist you? You can ask me about: appointments, warning symptoms, prevention tips, or hospital services.";
    }
}

// Fallback response if AI fails
function getFallbackResponse($language) {
    if ($language === 'sw') {
        return "Samahani, kuna hitilafu ya kiufundi. Tafadhali jaribu tena baadaye au wasiliana nasi kwa simu. Tunawasiliana na timu yetu kukusaidia haraka iwezekanavyo.";
    }
    return "Sorry, there's a technical issue. Please try again later or contact us by phone. We're reaching out to our team to assist you as soon as possible.";
}

// Log AI conversations for training/analytics
function logAIConversation($pdo, $patientId, $userMessage, $aiResponse, $language) {
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO ai_conversations (patient_id, user_message, ai_response, language, created_at) 
             VALUES (?, ?, ?, ?, NOW())"
        );
        $stmt->execute([$patientId, $userMessage, $aiResponse, $language]);
    } catch (Exception $e) {
        error_log("Failed to log AI conversation: " . $e->getMessage());
    }
}
?>
