<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * Truncate every table in the active database. Schema is preserved.
 *
 * @return array{ok: bool, database: string, tables_cleared: int, before: array<string, int>, after: array<string, int>}
 */
function wipe_all_database_tables(): array
{
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
        $safe = str_replace('`', '``', $table);
        $before[$table] = (int) $pdo->query('SELECT COUNT(*) FROM `' . $safe . '`')->fetchColumn();
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($tables as $table) {
        $safe = str_replace('`', '``', $table);
        $pdo->exec('TRUNCATE TABLE `' . $safe . '`');
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    $after = [];
    foreach ($tables as $table) {
        $safe = str_replace('`', '``', $table);
        $after[$table] = (int) $pdo->query('SELECT COUNT(*) FROM `' . $safe . '`')->fetchColumn();
    }

    return [
        'ok' => true,
        'database' => $dbName,
        'tables_cleared' => count($tables),
        'before' => $before,
        'after' => $after,
    ];
}

function wipe_data_password_valid(string $provided): bool
{
    $expected = defined('WIPE_DATA_PASSWORD') ? WIPE_DATA_PASSWORD : 'Adminpass';
    if ($expected === '') {
        return false;
    }
    return hash_equals($expected, $provided);
}
