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
 * Get language-aware system prompt for AI
 */
function ai_system_prompt(string $lang = 'en'): string
{
    if (strtolower($lang) === 'sw') {
        return 'Wewe ni msaidizi wa afya kwa ' . HOSPITAL_NAME . '. '
            . 'Lazima ujibu kwa Kiswahili pekee. '
            . 'KANUNI MKUU: Jibu SWALI LOLOTE linaloulizwa na mgonjwa. '
            . 'Hakuna swali la afya lisilo sahihi. Hakuna swali "nje ya mada". '
            . 'Magonjwa yoyote: HPV, malaria, kisukari, homa, mimba, kuumwa kichwa, maumivu ya tumbo, afya ya akili, lishe, kinga, dawa za kawaida. '
            . 'Pia maswali kuhusu hospitali: saa za kufunguliwa, bei, taratibu, rufaa, daktari wa nani. '
            . 'Ikiwa swali halina ugonjwa mahususi (k.m. "how can I prevent myself"), elezea kinga kwa ujumla kisha uliza: "Unataka kuzuia ugonjwa gani hasa?" '
            . 'Ikiwa unahitaji maelezo zaidi ili kujibu vizuri, uliza maswali mafupi ya kufafanua. '
            . 'Jibu kwa mpangilio: kwanza jibu moja kwa moja, kisha hatua za kuchukua, kisha maswali ya ziada. '
            . 'USItambue magonjwa mapya. USIbadilishe dawa. USITOE ushauri hatari. '
            . 'Kila wakati sisitiza kwa upole kwamba mgonjwa atembelee ' . HOSPITAL_NAME . ' kwa uchunguzi na matibabu sahihi. '
            . 'Dalili za dharura: mwambie kutafuta daktari mara moja. '
            . 'Mwisho wa kila jibu, uliza swali la kumhusisha kama "Unajisikiaje leo?" au "Je, una swali lingine?" '
            . 'Wakati inafaa, toa kidokezo kifupi cha HPV au afya ili kumtia moyo mgonjwa.';
    }

    return 'You are a broad medical and hospital support assistant for ' . HOSPITAL_NAME . '. '
        . 'MAIN RULE: Answer ANY question the patient asks — medical or hospital-related. '
        . 'No question is out of scope. No question is wrong. '
        . 'Topics include but are not limited to: HPV (Human Papillomavirus), cervical cancer prevention, HPV vaccination, malaria, diabetes, fever, pregnancy, headaches, stomach pain, injuries, mental health, nutrition, prevention, common medications, first aid, lab results interpretation, vaccine schedules, hygiene, symptoms of ANY disease. '
        . 'Also hospital questions: opening hours, costs, referral process, how to see a doctor, appointment booking, which department for which problem. '
        . 'If the question is vague (e.g., "how can I prevent myself"), explain general prevention (hygiene, safe sex, vaccination, avoid infection sources), then ask: "Which disease do you want to prevent specifically?" '
        . 'If you need more details to give a good answer, ask 1–2 short clarifying questions (e.g., "How long?", "Any fever?", "Any other symptoms?"). '
        . 'Answer in order: first direct answer, then actionable steps, then ask for more questions. '
        . 'NEVER diagnose a new disease. NEVER change or prescribe medications. NEVER give dangerous advice. '
        . 'Always gently insist that the patient visits ' . HOSPITAL_NAME . ' for proper examination and treatment. '
        . 'If symptoms suggest emergency (chest pain, difficulty breathing, severe bleeding, sudden confusion, suicidal thoughts), say: "Seek urgent care immediately." '
        . 'At the end of every answer, ask a warm engaging question like "How are you feeling today?" or "Do you have another question?" '
        . 'When appropriate, share a brief HPV or wellness tip to keep the patient informed and encouraged.';
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

    $payload = [
        'model' => GROQ_MODEL,
        'messages' => [
            ['role' => 'system', 'content' => ai_system_prompt($lang)],
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

    $conversationId = ai_get_or_create_conversation($patientId, $channel);
    ai_log_turn($conversationId, 'user', $patientText, null);

    $messages = [['role' => 'system', 'content' => ai_system_prompt($lang)]];
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
        if ($lang === 'sw') {
            $reply = 'Asante kwa kuitikia. Tupo hapa kwako. Jibu DOCTOR kwa msaada wa hospitali.';
        } else {
            $reply = 'Thank you for reaching out. We are here for you. Reply DOCTOR for direct hospital support.';
        }
    }

    ai_log_turn($conversationId, 'assistant', $reply, GROQ_MODEL);
    return ['ok' => true, 'reply' => $reply, 'error' => null];
}
