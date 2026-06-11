<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../messaging.php';
require_once __DIR__ . '/../scheduled_messages.php';

ensure_outbound_message_types();
maybe_flush_due_scheduled_messages();

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function api_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function api_body(): array
{
    $raw = file_get_contents('php://input');
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }
    $json = json_decode($raw, true);
    return is_array($json) ? $json : [];
}

function api_phone(string $raw): string
{
    $t = trim($raw);
    if ($t === '') {
        return '';
    }
    $digits = preg_replace('/\D+/', '', $t) ?? '';
    if ($digits === '') {
        return '';
    }
    if (str_starts_with($digits, '254')) {
        return '+' . $digits;
    }
    if (str_starts_with($digits, '0')) {
        $digits = substr($digits, 1);
    }
    if (strlen($digits) === 9) {
        return '+254' . $digits;
    }
    if ($t[0] === '+') {
        return '+' . $digits;
    }
    return '+' . $digits;
}

function api_dt(string $html): string
{
    return str_replace('T', ' ', trim($html));
}
