<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function ai_enabled(): bool
{
    return GROQ_API_KEY !== '';
}

/** @deprecated Use ai_enabled() */
function openai_enabled(): bool
{
    return ai_enabled();
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
         VALUES (?, ?, ?)'
    );
    $insert->execute([$patientId, $channel, json_encode(['source' => 'africastalking-webhook', 'provider' => 'groq'])]);
    return (int) db()->lastInsertId();
}

function ai_log_turn(int $conversationId, string $role, string $content, ?string $model = null): void
{
    $st = db()->prepare(
        'INSERT INTO ai_turns (conversation_id, role, content, model)
         VALUES (?,?,?,?)'
    );
    $st->execute([$conversationId, $role, $content, $model]);
}

/**
 * Returns latest turns as chat-completions format.
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
 * Detect language/style from the patient's message text.
 * Returns: sw | en | sheng | mixed
 */
function ai_detect_message_language(string $text, string $fallback = 'en'): string
{
    $text = mb_strtolower(trim($text));
    if ($text === '') {
        return in_array($fallback, ['sw', 'en'], true) ? $fallback : 'en';
    }

    $tokens = preg_split('/\s+/u', preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text) ?? '') ?: [];
    $tokens = array_values(array_filter($tokens, static fn ($t) => mb_strlen($t) > 1));
    if ($tokens === []) {
        return in_array($fallback, ['sw', 'en'], true) ? $fallback : 'en';
    }

    $swWords = [
        'habari', 'jambo', 'asante', 'sana', 'nina', 'nime', 'sijui', 'daktari', 'homa', 'pole', 'niko',
        'mimi', 'wewe', 'tafadhali', 'sawa', 'ndio', 'hapana', 'leo', 'kesho', 'maumivu', 'afya', 'chanjo',
        'hospitali', 'msaada', 'kwani', 'bado', 'kidogo', 'mambo', 'vizuri', 'una', 'yako', 'najisikia',
        'naskia', 'mguu', 'kichwa', 'tumbo', 'dawa', 'mgonjwa', 'mama', 'baba', 'mtoto', 'simu', 'sijui',
        'naweza', 'tunaweza', 'je', 'lakini', 'pia', 'sasa', 'hivi', 'hapa', 'wapi', 'nini', 'kwa', 'na',
    ];
    $shengWords = [
        'niaje', 'msee', 'poa', 'fiti', 'sai', 'buda', 'bana', 'maze', 'arafu', 'vipi', 'nde', 'bro',
        'mami', 'dem', 'form', 'score', 'kuwa', 'mbogi', 'sonko', 'mflow', 'tufanye', 'mrenga', 'mhenga',
        'naskia', 'sasa', 'msee', 'mambo', 'poa', 'safi', 'chanuka', 'msee', 'budaa', 'msee', 'oya',
    ];
    $enWords = [
        'the', 'and', 'is', 'are', 'have', 'what', 'how', 'when', 'where', 'please', 'thank', 'thanks',
        'you', 'help', 'doctor', 'pain', 'feel', 'feeling', 'today', 'hello', 'hi', 'hey', 'my', 'can',
        'could', 'would', 'need', 'want', 'got', 'getting', 'about', 'with', 'for', 'this', 'that', 'very',
    ];

    $sw = $sheng = $en = 0;
    foreach ($tokens as $token) {
        if (in_array($token, $swWords, true)) {
            $sw++;
        }
        if (in_array($token, $shengWords, true)) {
            $sheng++;
        }
        if (in_array($token, $enWords, true)) {
            $en++;
        }
    }

    if ($sheng >= 1 && ($sw >= 1 || $en >= 1)) {
        return 'sheng';
    }
    if ($sheng >= 2) {
        return 'sheng';
    }
    if ($sw >= 1 && $en >= 1) {
        return 'mixed';
    }
    if ($sw >= 2 || ($sw >= 1 && count($tokens) <= 4)) {
        return 'sw';
    }
    if ($en >= 1) {
        return 'en';
    }
    if ($sw >= 1) {
        return 'sw';
    }

    return in_array($fallback, ['sw', 'en'], true) ? $fallback : 'en';
}

/**
 * Language-matching instructions injected into every AI system prompt.
 */
function ai_language_instructions(string $detectedLang): string
{
    $hints = [
        'sw' => 'The patient wrote in Kiswahili. Reply entirely in natural, warm Kiswahili.',
        'en' => 'The patient wrote in English. Reply in clear, friendly English (simple words if they used simple English).',
        'sheng' => 'The patient is using Sheng or Kenyan street slang. Reply in the same Sheng style — informal, relatable, mix Swahili and English naturally like a local would. Examples: "poa", "sawa", "niaje" vibe.',
        'mixed' => 'The patient is code-switching (mixing Swahili and English). Reply in the same mixed style — do NOT force pure English or pure Swahili.',
    ];
    $hint = $hints[$detectedLang] ?? $hints['mixed'];

    return 'CRITICAL — LANGUAGE (follow this first): ' . $hint
        . ' Always mirror the language, tone, slang, and formality of the patient\'s LATEST message. '
        . 'If they switch to Swahili, English, Sheng, or broken/mixed text, switch with them immediately. '
        . 'Do not correct their grammar. Keep replies short enough for SMS/WhatsApp. '
        . 'Never reply in a different language unless the patient explicitly asks.';
}

/**
 * Get language-aware system prompt for AI
 */
function ai_system_prompt(string $lang = 'en', ?string $latestUserMessage = null): string
{
    if ($latestUserMessage !== null && trim($latestUserMessage) !== '') {
        $lang = ai_detect_message_language($latestUserMessage, $lang);
    }

    $languageBlock = ai_language_instructions($lang);

    $core = 'You are a warm medical and hospital support assistant for ' . HOSPITAL_NAME . ' (HPV patient engagement program). '
        . 'MAIN RULE: Answer ANY health or hospital question the patient asks. No question is wrong or out of scope. '
        . 'Topics: HPV, cervical cancer prevention, vaccination, malaria, diabetes, fever, pregnancy, pain, mental health, nutrition, appointments, hospital services. '
        . 'Answer in order: direct answer → simple actionable steps → warm follow-up question. '
        . 'NEVER diagnose new conditions, change medications, or give dangerous advice. '
        . 'Gently encourage visiting ' . HOSPITAL_NAME . ' for examination when needed. '
        . 'Emergency symptoms (chest pain, severe bleeding, can\'t breathe): tell them to seek urgent care now. '
        . 'End every reply with a friendly engaging question to keep the conversation going. '
        . 'When helpful, add a brief HPV or wellness tip.';

    return $languageBlock . "\n\n" . $core;
}

/**
 * Fallback SMS when Groq is unavailable — matches detected language.
 */
function ai_fallback_reply(string $detectedLang): string
{
    if ($detectedLang === 'sw') {
        return 'Asante kwa ujumbe wako. Tupo hapa kwako. Kwa swali lolote la kiafya, tembelea '
            . HOSPITAL_NAME . '. Jibu DOCTOR kuongea na daktari au HELP kwa mwongozo zaidi.';
    }
    if ($detectedLang === 'sheng' || $detectedLang === 'mixed') {
        return 'Poa, asante kwa kutuchat! Tuko hapa kukusupport. Kwa msaada wa afya tembelea '
            . HOSPITAL_NAME . '. Reply DOCTOR kuongea na daktari ama HELP kwa options zaidi.';
    }
    return 'Thank you for your message. We are here for you. For any medical question, please visit '
        . HOSPITAL_NAME . '. Reply DOCTOR to speak with a doctor or HELP for more options.';
}

/**
 * Single-turn Groq call without conversation logging (for API probes).
 * Returns ['ok'=>bool, 'reply'=>string, 'error'=>?string]
 */
function ai_quick_reply(string $patientText, string $lang = 'en'): array
{
    if (!ai_enabled()) {
        return ['ok' => false, 'reply' => '', 'error' => 'GROQ_API_KEY is empty'];
    }
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'reply' => '', 'error' => 'PHP cURL extension is not enabled'];
    }

    $detectedLang = ai_detect_message_language($patientText, $lang);

    $payload = [
        'model' => GROQ_MODEL,
        'messages' => [
            ['role' => 'system', 'content' => ai_system_prompt($lang, $patientText)],
            ['role' => 'user', 'content' => $patientText],
        ],
        'temperature' => 0.7,
        'max_tokens' => 220,
    ];

    $ch = curl_init(GROQ_BASE_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . GROQ_API_KEY,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 45,
        CURLOPT_CONNECTTIMEOUT => 15,
    ]);

    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($raw === false) {
        return ['ok' => false, 'reply' => '', 'error' => $err !== '' ? $err : 'Unknown cURL error'];
    }

    $json = json_decode($raw, true);
    if ($code < 200 || $code >= 300) {
        $error = is_array($json) ? json_encode($json) : (string) $raw;
        return ['ok' => false, 'reply' => '', 'error' => 'Groq HTTP ' . $code . ': ' . $error];
    }

    $reply = trim((string) ($json['choices'][0]['message']['content'] ?? ''));
    if ($reply === '') {
        return ['ok' => false, 'reply' => '', 'error' => 'Groq returned empty content'];
    }
    return ['ok' => true, 'reply' => $reply, 'error' => null];
}

/**
 * Short warm engagement SMS for 3-day patient check-ins (HPV program).
 * Returns ['ok'=>bool, 'reply'=>string, 'error'=>?string]
 */
function ai_engagement_reply(string $patientName, string $lang = 'en'): array
{
    if (!ai_enabled()) {
        return ['ok' => false, 'reply' => '', 'error' => 'GROQ_API_KEY is empty'];
    }

    $topics = [
        'HPV vaccination or screening encouragement',
        'a simple daily wellness habit',
        'cervical health awareness',
        'staying positive on their care journey',
    ];
    $topic = $topics[array_rand($topics)];
    $greeting = $patientName !== '' ? $patientName : ($lang === 'sw' ? 'rafiki' : 'friend');

    $system = $lang === 'sw'
        ? 'Wewe ni msaidizi wa afya wa programu ya HPV kwa ' . HOSPITAL_NAME . '. Andika ujumbe mfupi wa SMS (herufi 280 tu). Mtie moyo, toa kidokezo kimoja cha afya, mwisho uliza swali la kumhusisha. Usitumie HTML.'
        : 'You are a warm HPV care assistant for ' . HOSPITAL_NAME . '. Write a short SMS (max 280 chars). Be encouraging, include one health tip, end with an engaging question. No HTML.';

    $user = $lang === 'sw'
        ? "Andika ujumbe wa kumtia moyo mgonjwa {$greeting} kuhusu: {$topic}."
        : "Write an encouraging message for patient {$greeting} about: {$topic}.";

    $payload = [
        'model' => GROQ_MODEL,
        'messages' => [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ],
        'temperature' => 0.85,
        'max_tokens' => 180,
    ];

    $ch = curl_init(GROQ_BASE_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . GROQ_API_KEY,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 30,
    ]);
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($raw === false || $code < 200 || $code >= 300) {
        return ['ok' => false, 'reply' => '', 'error' => 'Groq engagement HTTP ' . $code];
    }
    $json = json_decode($raw, true);
    $reply = trim((string) ($json['choices'][0]['message']['content'] ?? ''));
    if ($reply === '') {
        return ['ok' => false, 'reply' => '', 'error' => 'Empty engagement reply'];
    }
    if (strlen($reply) > 320) {
        $reply = substr($reply, 0, 317) . '...';
    }
    return ['ok' => true, 'reply' => $reply, 'error' => null];
}

/**
 * Returns ['ok'=>bool, 'reply'=>string, 'error'=>?string]
 */
function ai_generate_reply(int $patientId, string $channel, string $patientText, string $lang = 'en'): array
{
    if (!ai_enabled()) {
        return ['ok' => false, 'reply' => '', 'error' => 'GROQ_API_KEY is empty'];
    }
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'reply' => '', 'error' => 'PHP cURL extension is not enabled'];
    }

    $detectedLang = ai_detect_message_language($patientText, $lang);

    $conversationId = ai_get_or_create_conversation($patientId, $channel);
    ai_log_turn($conversationId, 'user', $patientText, null);

    $messages = [['role' => 'system', 'content' => ai_system_prompt($lang, $patientText)]];
    foreach (ai_recent_messages($conversationId, 12) as $m) {
        $messages[] = $m;
    }

    $payload = [
        'model' => GROQ_MODEL,
        'messages' => $messages,
        'temperature' => 0.7,
        'max_tokens' => 220,
    ];

    $ch = curl_init(GROQ_BASE_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . GROQ_API_KEY,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 45,
        CURLOPT_CONNECTTIMEOUT => 15,
    ]);

    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($raw === false) {
        return ['ok' => false, 'reply' => '', 'error' => $err !== '' ? $err : 'Unknown cURL error'];
    }

    $json = json_decode($raw, true);
    if ($code < 200 || $code >= 300) {
        $error = is_array($json) ? json_encode($json) : (string) $raw;
        return ['ok' => false, 'reply' => '', 'error' => 'Groq HTTP ' . $code . ': ' . $error];
    }

    $reply = '';
    if (isset($json['choices'][0]['message']['content'])) {
        $reply = trim((string) $json['choices'][0]['message']['content']);
    }
    if ($reply === '') {
        $reply = ai_fallback_reply($detectedLang);
    }

    ai_log_turn($conversationId, 'assistant', $reply, GROQ_MODEL);
    return ['ok' => true, 'reply' => $reply, 'error' => null];
}
