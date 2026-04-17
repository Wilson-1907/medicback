<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/** Single-hospital pilot: no password gate; identity is for audit fields only. */
function portal_session(): void
{
    if (empty($_SESSION['staff_id'])) {
        $_SESSION['staff_id'] = 0;
        $_SESSION['staff_username'] = 'staff';
        $_SESSION['staff_display'] = 'Pilot hospital';
    }
}

function require_login(): void
{
    portal_session();
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_verify(?string $token): bool
{
    return is_string($token) && isset($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $token);
}

function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}
