<?php
declare(strict_types=1);

/**
 * Apply SQL migrations to the database configured in hospital_portal/.env
 * or environment variables (DB_HOST, DB_PORT, DB_NAME, etc.).
 *
 * Usage:
 *   php hospital_portal/tools/apply_migrations.php
 *   php hospital_portal/tools/apply_migrations.php 2026_06_10_appointment_attendance.sql
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../appointment_utils.php';

$sqlDir = realpath(__DIR__ . '/../sql');
if ($sqlDir === false) {
    fwrite(STDERR, "sql directory not found.\n");
    exit(1);
}

$only = $argv[1] ?? '';
$files = [];
if ($only !== '') {
    $path = $sqlDir . DIRECTORY_SEPARATOR . basename($only);
    if (!is_file($path)) {
        fwrite(STDERR, "Migration not found: {$only}\n");
        exit(1);
    }
    $files = [$path];
} else {
    $files = glob($sqlDir . DIRECTORY_SEPARATOR . '*.sql') ?: [];
    sort($files);
    $files = array_values(array_filter(
        $files,
        static fn (string $f): bool => !str_contains(basename($f), 'truncate')
    ));
}

$pdo = db();
$schema = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
echo "Connected to {$schema} on " . DB_HOST . ':' . DB_PORT . PHP_EOL;

$ok = 0;
$fail = 0;

foreach ($files as $file) {
    $name = basename($file);
    $sql = file_get_contents($file);
    if (!is_string($sql) || trim($sql) === '') {
        echo "SKIP empty: {$name}\n";
        continue;
    }

    echo "Applying {$name}... ";
    try {
        foreach (split_sql_statements($sql) as $statement) {
            if ($statement === '') {
                continue;
            }
            $pdo->exec($statement);
        }
        echo "OK\n";
        $ok++;
    } catch (Throwable $e) {
        echo "FAIL\n  " . $e->getMessage() . "\n";
        $fail++;
    }
}

echo PHP_EOL . "Running ensure_appointment_attendance_schema()... ";
if (ensure_appointment_attendance_schema()) {
    echo "OK\n";
} else {
    echo "FAIL (see PHP error log)\n";
    $fail++;
}

$st = $pdo->query(
    "SELECT COLUMN_NAME FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'appointments'
       AND COLUMN_NAME IN ('attendance_recorded_at','status')
     ORDER BY COLUMN_NAME"
);
$cols = $st ? $st->fetchAll(PDO::FETCH_COLUMN) : [];
echo 'appointments columns: ' . implode(', ', $cols) . PHP_EOL;

exit($fail > 0 ? 1 : 0);

/** @return list<string> */
function split_sql_statements(string $sql): array
{
    $sql = preg_replace('/^\s*USE\s+\w+\s*;/mi', '', $sql) ?? $sql;
    $parts = preg_split('/;\s*\n/', $sql) ?: [];
    $out = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '' || str_starts_with($part, '--')) {
            continue;
        }
        $out[] = $part;
    }

    return $out;
}
