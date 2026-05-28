<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function openai_enabled(): bool
{
    return OPENAI_API_KEY !== '';
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

function ai_log_turn(int $conversationId, string $role, string $content, ?string $model = null): void
{
    $st = db()->prepare(
        'INSERT INTO ai_turns (conversation_id, role, content, model)
         VALUES (?,?,?,?)'
    );
    $st->execute([$conversationId, $role, $content, $model]);
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
 * Get language-aware system prompt for AI
 */
function ai_system_prompt(string $lang = 'en'): string
{
    if ($lang === 'sw') {
    return 'Wewe ni msaidizi wa afya kwa ' . HOSPITAL_NAME . '. '
        . 'Jibu maswali ya matibabu kwa usahihi, kwa kina, na kwa lugha rahisi. '
        . 'Wahimize wagonjwa kuuliza maswali mengi — kuhusu dalili, magonjwa, matibabu, maisha ya afya, au wasiwasi wowote wa kiafya. '
        . 'Usisubiri maswali marefu; wasaidie kufunguka kwa kuuliza maswali madogo madogo kuhusu historia, muda wa dalili, na ukali wao. '
        . 'Jibu swali lililoulizwa hasa, usitoe habari za jumla tu. '
        . 'KAMWE usitoe utambuzi wa ugonjwa mpya, KAMWE usibadilishe dawa, KAMWE usipe ushauri wa hatari. '
        . 'Iwapo dalili zinaashiria dharura (kutokwa na damu, kupumua kwa shida, maumivu baya), mwambie kutafuta daktari mara moja. '
        . 'Kumbuka: mgonjwa anaweza kujibu DOCTOR kwa mawasiliano ya moja kwa moja na hospitali.';
}

return 'You are a medical information and support assistant for ' . HOSPITAL_NAME . '. '
    . 'Your role: Answer patient questions about ANY medical topic — symptoms, chronic diseases, first aid, medications (general info), prevention, mental health, lifestyle, and test results interpretation. '
    . 'Encourage the user to ask many questions, even small or repeated ones. If their question is vague, ask brief clarifying questions (e.g., "How long?", "Any fever?", "Location of pain?"). '
    . 'Always answer the specific question asked — do not just give a generic reply. '
    . 'Be specific: mention possible causes only if common and relevant, explain what the patient can do at home safely, and state clearly when they must see a doctor. '
    . 'NEVER diagnose a new disease, NEVER prescribe or change medications, NEVER give dangerous or unverified advice. '
    . 'If symptoms suggest an emergency (chest pain, difficulty breathing, sudden confusion, severe bleeding, head injury), immediately tell patient to seek emergency care. '
    . 'Remind patients they can reply DOCTOR for direct staff contact at the hospital.';
}

/**
 * Returns ['ok'=>bool, 'reply'=>string, 'error'=>?string]
 */
function ai_generate_reply(int $patientId, string $channel, string $patientText, string $lang = 'en'): array
{
    if (!openai_enabled()) {
        return ['ok' => false, 'reply' => '', 'error' => 'OPENAI_API_KEY is empty'];
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
        'model' => OPENAI_MODEL,
        'messages' => $messages,
        'temperature' => 0.7,
        'max_tokens' => 220,
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
        return ['ok' => false, 'reply' => '', 'error' => $err !== '' ? $err : 'Unknown cURL error'];
    }

    $json = json_decode($raw, true);
    if ($code < 200 || $code >= 300) {
        $error = is_array($json) ? json_encode($json) : (string) $raw;
        return ['ok' => false, 'reply' => '', 'error' => 'OpenAI HTTP ' . $code . ': ' . $error];
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

    ai_log_turn($conversationId, 'assistant', $reply, OPENAI_MODEL);
    return ['ok' => true, 'reply' => $reply, 'error' => null];
}
