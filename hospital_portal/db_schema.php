<?php
declare(strict_types=1);

function db_table_has_column(string $table, string $column): bool
{
    $st = db()->prepare(
        "SELECT 1 FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
         LIMIT 1"
    );
    $st->execute([$table, $column]);

    return (bool) $st->fetchColumn();
}

function db_table_exists(string $table): bool
{
    $st = db()->prepare(
        "SELECT 1 FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
         LIMIT 1"
    );
    $st->execute([$table]);

    return (bool) $st->fetchColumn();
}
