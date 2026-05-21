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

function ai_system_prompt(): string
{
    return 'You are a caring PHV patient support assistant for ' . HOSPITAL_NAME . '. '
        . 'Tone must be warm, reassuring, hopeful, and practical. '
        . 'Give short actionable guidance and encouragement. '
        . 'Do NOT diagnose new diseases, do NOT prescribe medication changes, and do NOT provide unsafe advice. '
        . 'If symptoms may be severe or emergency-like, tell patient to seek urgent care immediately and contact the hospital. '
        . 'When relevant, remind patients they can reply DOCTOR for direct staff contact.';
}

/**
 * Returns ['ok'=>bool, 'reply'=>string, 'error'=>?string]
 */
function ai_generate_reply(int $patientId, string $channel, string $patientText): array
{
    if (!openai_enabled()) {
        return ['ok' => false, 'reply' => '', 'error' => 'OPENAI_API_KEY is empty'];
    }
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'reply' => '', 'error' => 'PHP cURL extension is not enabled'];
    }

    $conversationId = ai_get_or_create_conversation($patientId, $channel);
    ai_log_turn($conversationId, 'user', $patientText, null);

    $messages = [['role' => 'system', 'content' => ai_system_prompt()]];
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
        $reply = 'Thank you for reaching out. We are here for you. Reply DOCTOR for direct hospital support.';
    }

    ai_log_turn($conversationId, 'assistant', $reply, OPENAI_MODEL);
    return ['ok' => true, 'reply' => $reply, 'error' => null];
}
