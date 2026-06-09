<?php
declare(strict_types=1);

/**
 * Inbound WhatsApp webhook — Meta Cloud format (Mteja forwards this to medicback).
 * URL: https://medicback.onrender.com/webhook_whatsapp.php
 */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/whatsapp_inbound.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

/** Webhook verification (Meta GET) */
if ($method === 'GET') {
    $mode = (string) ($_GET['hub_mode'] ?? $_GET['hub.mode'] ?? '');
    $token = (string) ($_GET['hub_verify_token'] ?? $_GET['hub.verify_token'] ?? '');
    $challenge = (string) ($_GET['hub_challenge'] ?? $_GET['hub.challenge'] ?? '');
    $expected = defined('WHATSAPP_VERIFY_TOKEN') ? WHATSAPP_VERIFY_TOKEN : '';

    if ($mode === 'subscribe' && $expected !== '' && hash_equals($expected, $token)) {
        header('Content-Type: text/plain');
        echo $challenge;
        exit;
    }
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

header('Content-Type: text/plain');
echo 'OK';
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} elseif (ob_get_level()) {
    ob_end_flush();
}
flush();

$raw = file_get_contents('php://input');
if (!is_string($raw)) {
    $raw = '';
}

whatsapp_inbound_handle_request($raw, $_GET);
