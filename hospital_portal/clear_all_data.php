<?php
declare(strict_types=1);

/**
 * Wipe all rows from every table in the configured database.
 * Keeps schema intact. Requires CRON_SECRET when set on the server.
 *
 * GET /clear_all_data.php?key=<CRON_SECRET>&confirm=yes
 */
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=UTF-8');

$cronSecret = getenv('CRON_SECRET') ?: '';
if ($cronSecret !== '') {
    $provided = (string) ($_GET['key'] ?? $_SERVER['HTTP_X_CRON_SECRET'] ?? '');
    if (!hash_equals($cronSecret, $provided)) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (($_GET['confirm'] ?? '') !== 'yes') {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'Missing confirm=yes. This deletes all data in every table.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = db();
    $dbName = DB_NAME;

    $tablesStmt = $pdo->query(
        'SELECT TABLE_NAME FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = ' . $pdo->quote($dbName) . '
         ORDER BY TABLE_NAME'
    );
    $tables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);

    $before = [];
    foreach ($tables as $table) {
        $count = (int) $pdo->query('SELECT COUNT(*) FROM `' . str_replace('`', '``', $table) . '`')->fetchColumn();
        $before[$table] = $count;
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($tables as $table) {
        $pdo->exec('TRUNCATE TABLE `' . str_replace('`', '``', $table) . '`');
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    $after = [];
    foreach ($tables as $table) {
        $after[$table] = (int) $pdo->query('SELECT COUNT(*) FROM `' . str_replace('`', '``', $table) . '`')->fetchColumn();
    }

    echo json_encode([
        'ok' => true,
        'database' => $dbName,
        'tables_cleared' => count($tables),
        'before' => $before,
        'after' => $after,
        'timestamp' => date('c'),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('clear_all_data error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('c'),
    ], JSON_UNESCAPED_UNICODE);
}
