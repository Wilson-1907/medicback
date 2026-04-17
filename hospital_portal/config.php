<?php
declare(strict_types=1);

/**
 * Copy this folder to XAMPP htdocs (e.g. C:\xampp\htdocs\phv_hospital).
 * Adjust DB credentials to match your MySQL user.
 */
function load_env_file(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }
        $key = trim($parts[0]);
        $val = trim($parts[1]);
        if ($key === '') {
            continue;
        }
        if (str_starts_with($val, '"') && str_ends_with($val, '"')) {
            $val = trim($val, '"');
        } elseif (str_starts_with($val, "'") && str_ends_with($val, "'")) {
            $val = trim($val, "'");
        }
        if (getenv($key) === false) {
            putenv($key . '=' . $val);
        }
        $_ENV[$key] = $val;
    }
}

function env_value(string $key, string $default = ''): string
{
    $value = getenv($key);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }
    return (string) $value;
}

function env_by_mode(string $mode, string $sandboxKey, string $prodKey, string $legacyKey, string $default = ''): string
{
    $mode = strtolower(trim($mode));
    if ($mode === 'production') {
        $value = env_value($prodKey, '');
        if ($value !== '') {
            return $value;
        }
    } else {
        $value = env_value($sandboxKey, '');
        if ($value !== '') {
            return $value;
        }
    }
    return env_value($legacyKey, $default);
}

load_env_file(__DIR__ . '/.env');

define('DB_HOST', env_value('DB_HOST', '127.0.0.1'));
define('DB_PORT', env_value('DB_PORT', '3306'));
define('DB_NAME', env_value('DB_NAME', 'phv_pilot'));
define('DB_USER', env_value('DB_USER', 'root'));
define('DB_PASS', env_value('DB_PASS', ''));
define('DB_CHARSET', env_value('DB_CHARSET', 'utf8mb4'));
define('DB_SSL_MODE', strtolower(env_value('DB_SSL_MODE', 'preferred'))); // disable|preferred|required
define('DB_SSL_CA', env_value('DB_SSL_CA', ''));

define('APP_NAME', env_value('APP_NAME', 'PHV Hospital Console'));
define('HOSPITAL_NAME', env_value('HOSPITAL_NAME', 'Your Hospital'));
define('AFRICASTALKING_MODE', env_value('AFRICASTALKING_MODE', 'sandbox'));
define('AFRICASTALKING_USERNAME', env_by_mode(
    AFRICASTALKING_MODE,
    'AFRICASTALKING_SANDBOX_USERNAME',
    'AFRICASTALKING_PROD_USERNAME',
    'AFRICASTALKING_USERNAME',
    'sandbox'
));
define('AFRICASTALKING_API_KEY', env_by_mode(
    AFRICASTALKING_MODE,
    'AFRICASTALKING_SANDBOX_API_KEY',
    'AFRICASTALKING_PROD_API_KEY',
    'AFRICASTALKING_API_KEY',
    ''
));
define('AFRICASTALKING_SMS_FROM', env_by_mode(
    AFRICASTALKING_MODE,
    'AFRICASTALKING_SANDBOX_SMS_FROM',
    'AFRICASTALKING_PROD_SMS_FROM',
    'AFRICASTALKING_SMS_FROM',
    ''
));
define('AFRICASTALKING_WHATSAPP_FROM', env_by_mode(
    AFRICASTALKING_MODE,
    'AFRICASTALKING_SANDBOX_WHATSAPP_FROM',
    'AFRICASTALKING_PROD_WHATSAPP_FROM',
    'AFRICASTALKING_WHATSAPP_FROM',
    ''
));
define('AFRICASTALKING_SMS_URL', env_by_mode(
    AFRICASTALKING_MODE,
    'AFRICASTALKING_SANDBOX_SMS_URL',
    'AFRICASTALKING_PROD_SMS_URL',
    'AFRICASTALKING_SMS_URL',
    'https://api.africastalking.com/version1/messaging'
));
define('AFRICASTALKING_WHATSAPP_URL', env_by_mode(
    AFRICASTALKING_MODE,
    'AFRICASTALKING_SANDBOX_WHATSAPP_URL',
    'AFRICASTALKING_PROD_WHATSAPP_URL',
    'AFRICASTALKING_WHATSAPP_URL',
    'https://api.africastalking.com/version1/whatsapp/message'
));

define('OPENAI_API_KEY', env_value('OPENAI_API_KEY', ''));
define('OPENAI_MODEL', env_value('OPENAI_MODEL', 'gpt-4o-mini'));
define('OPENAI_BASE_URL', env_value('OPENAI_BASE_URL', 'https://api.openai.com/v1/chat/completions'));
