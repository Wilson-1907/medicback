<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function db_host_port(): array
{
    $host = DB_HOST;
    $port = DB_PORT;
    if (str_contains(DB_HOST, ':')) {
        [$h, $p] = explode(':', DB_HOST, 2);
        if ($h !== '') {
            $host = $h;
        }
        if ($p !== '') {
            $port = $p;
        }
    }
    return [$host, $port];
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    [$host, $port] = db_host_port();
    $dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT            => 12,
    ];

    if (DB_SSL_MODE !== 'disable' && defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
        // Many managed MySQL providers require TLS; keep verify off unless CA path is supplied.
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = DB_SSL_CA !== '';
    }
    if (DB_SSL_CA !== '' && defined('PDO::MYSQL_ATTR_SSL_CA')) {
        $options[PDO::MYSQL_ATTR_SSL_CA] = DB_SSL_CA;
    }

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        throw new PDOException(
            $e->getMessage()
            . ' (Check DB_HOST/DB_PORT/DB_USER/DB_PASS and ensure your database allows external connections from Render.)',
            (int) $e->getCode(),
            $e
        );
    }
    return $pdo;
}
