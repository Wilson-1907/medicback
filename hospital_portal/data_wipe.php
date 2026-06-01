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

    $tablesStmt = $pdo->prepare(
        "SELECT TABLE_NAME FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = ?
           AND TABLE_TYPE = 'BASE TABLE'
         ORDER BY TABLE_NAME"
    );
    $tablesStmt->execute([$dbName]);
    $tables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);

    $before = [];
    foreach ($tables as $table) {
        $safe = str_replace('`', '``', $table);
        $before[$table] = (int) $pdo->query('SELECT COUNT(*) FROM `' . $safe . '`')->fetchColumn();
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($tables as $table) {
        $safe = str_replace('`', '``', $table);
        try {
            $pdo->exec('TRUNCATE TABLE `' . $safe . '`');
        } catch (Throwable $e) {
            error_log('wipe: TRUNCATE failed for ' . $table . ', using DELETE: ' . $e->getMessage());
            $pdo->exec('DELETE FROM `' . $safe . '`');
        }
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

function wipe_data_password_expected(): string
{
    if (!defined('WIPE_DATA_PASSWORD')) {
        return 'Adminpass';
    }
    $expected = trim((string) WIPE_DATA_PASSWORD);
    return $expected !== '' ? $expected : 'Adminpass';
}

function wipe_data_password_configured(): bool
{
    return wipe_data_password_expected() !== '';
}

function wipe_data_password_valid(string $provided): bool
{
    $provided = trim($provided);
    if ($provided === '') {
        return false;
    }
    return hash_equals(wipe_data_password_expected(), $provided);
}
