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
    $candidates = [];
    $fromGetenv = getenv($key);
    if ($fromGetenv !== false && $fromGetenv !== null) {
        $candidates[] = $fromGetenv;
    }
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
        $candidates[] = $_ENV[$key];
    }
    if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
        $candidates[] = $_SERVER[$key];
    }

    foreach ($candidates as $value) {
        $value = normalize_env_secret((string) $value, $key);
        if ($value !== '') {
            return $value;
        }
    }
    return $default;
}

/** Strip quotes/whitespace; fix accidental "KEY=value" paste into the value field. */
function normalize_env_secret(string $value, string $key): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    $prefix = $key . '=';
    if (str_starts_with($value, $prefix)) {
        $value = substr($value, strlen($prefix));
    }
    if ((str_starts_with($value, '"') && str_ends_with($value, '"'))
        || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
        $value = substr($value, 1, -1);
    }
    return trim($value);
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

define('APP_NAME', env_value('APP_NAME', 'HPV Hospital Console'));
define('HOSPITAL_NAME', env_value('HOSPITAL_NAME', 'Nyeri Town Health Center'));
define('AFYA_RAFIKI_NAME', env_value('AFYA_RAFIKI_NAME', 'Afya Rafiki'));
define('CLINIC_SITE_NAME', env_value('CLINIC_SITE_NAME', 'Nyeri Town Health Center'));
define('WIPE_DATA_PASSWORD', env_value('WIPE_DATA_PASSWORD', 'Adminpass'));
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

/** WhatsApp: africastalking | cloud (Meta Graph) | mteja (Mteja template broadcast API) */
define('WHATSAPP_PROVIDER', strtolower(env_value('WHATSAPP_PROVIDER', 'africastalking')));
define('WHATSAPP_ACCESS_TOKEN', env_value('WHATSAPP_ACCESS_TOKEN', ''));
define('WHATSAPP_PHONE_NUMBER_ID', env_value('WHATSAPP_PHONE_NUMBER_ID', ''));
define('WHATSAPP_BUSINESS_ACCOUNT_ID', env_value('WHATSAPP_BUSINESS_ACCOUNT_ID', ''));
define('WHATSAPP_VERIFY_TOKEN', env_value('WHATSAPP_VERIFY_TOKEN', ''));
define('WHATSAPP_GRAPH_VERSION', env_value('WHATSAPP_GRAPH_VERSION', 'v21.0'));
define('MTEJA_APP_ID', env_value('MTEJA_APP_ID', ''));
define('MTEJA_API_KEY', env_value('MTEJA_API_KEY', ''));
define('MTEJA_VIRTUAL_NUMBER', env_value('MTEJA_VIRTUAL_NUMBER', ''));
define('MTEJA_API_URL', env_value('MTEJA_API_URL', 'https://api.sentry.mteja.io/api/whatsapp-template'));
define('MTEJA_LANG_CODE_EN', env_value('MTEJA_LANG_CODE_EN', 'en'));
define('MTEJA_LANG_CODE_SW', env_value('MTEJA_LANG_CODE_SW', 'sw'));
/** Optional override if Mteja template name differs (e.g. MTEJA_TEMPLATE_AFYA_WELCOME_EN=welcome_en) */
define('MTEJA_TEMPLATE_AFYA_WELCOME_EN', env_value('MTEJA_TEMPLATE_AFYA_WELCOME_EN', ''));
define('MTEJA_TEMPLATE_AFYA_WELCOME_SW', env_value('MTEJA_TEMPLATE_AFYA_WELCOME_SW', ''));

define('GROQ_API_KEY', env_value('GROQ_API_KEY', ''));
define('GROQ_MODEL', env_value('GROQ_MODEL', 'llama-3.3-70b-versatile'));
define('GROQ_BASE_URL', env_value('GROQ_BASE_URL', 'https://api.groq.com/openai/v1/chat/completions'));
