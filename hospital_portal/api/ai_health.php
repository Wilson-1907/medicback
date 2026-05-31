<?php
declare(strict_types=1);

/**
 * Safe Groq AI connectivity check (does not expose the full API key).
 * GET /api/ai_health.php
 */
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../openai_assistant.php';

try {
    $key = GROQ_API_KEY;
    $payload = [
        'ok' => true,
        'provider' => 'groq',
        'ai_configured' => $key !== '',
        'key_length' => strlen($key),
        'key_prefix' => $key !== '' ? substr($key, 0, 8) . '...' : '',
        'model' => GROQ_MODEL,
        'base_url' => GROQ_BASE_URL,
        'curl_enabled' => function_exists('curl_init'),
    ];

    if ($key === '') {
        $payload['test'] = [
            'ok' => false,
            'error' => 'GROQ_API_KEY is empty on the server. Set it in Render → Environment → GROQ_API_KEY, then redeploy.',
        ];
        api_json($payload);
    }

    $test = ai_generate_reply(202, 'sms', 'Reply with one short friendly sentence only.', 'en');
    $payload['test'] = [
        'ok' => $test['ok'],
        'error' => $test['error'],
        'reply_preview' => $test['reply'] !== '' ? substr($test['reply'], 0, 120) : '',
    ];
    api_json($payload);
} catch (Throwable $e) {
    api_json(['ok' => false, 'error' => $e->getMessage()], 500);
}
