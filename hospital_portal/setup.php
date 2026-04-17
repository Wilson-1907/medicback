<?php
declare(strict_types=1);

/**
 * One-time setup: creates staff_users and default login.
 * Open: setup.php?key=phv-pilot-install
 * Then DELETE this file from the server.
 */
require_once __DIR__ . '/config.php';

$key = $_GET['key'] ?? '';
if ($key !== 'phv-pilot-install') {
    http_response_code(404);
    echo 'Not found.';
    exit;
}

header('Content-Type: text/html; charset=UTF-8');

$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Throwable $e) {
    echo '<p>Database connection failed. Import <code>phv_pilot_schema.sql</code> and check <code>config.php</code>.</p>';
    echo '<pre>' . h($e->getMessage()) . '</pre>';
    exit;
}

$pdo->exec("
CREATE TABLE IF NOT EXISTS staff_users (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  username        VARCHAR(64) NOT NULL,
  password_hash   VARCHAR(255) NOT NULL,
  display_name    VARCHAR(128) NOT NULL DEFAULT 'Staff',
  created_at      DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  UNIQUE KEY uq_staff_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$count = (int) $pdo->query('SELECT COUNT(*) AS c FROM staff_users')->fetch()['c'];
if ($count === 0) {
    $hash = password_hash('changeme', PASSWORD_DEFAULT);
    $st = $pdo->prepare('INSERT INTO staff_users (username, password_hash, display_name) VALUES (?,?,?)');
    $st->execute(['admin', $hash, 'Pilot Admin']);
    $msg = 'Created user <strong>admin</strong> with password <strong>changeme</strong>. Change it after first login (re-hash in DB) or add a profile screen later.';
} else {
    $msg = 'Staff users already exist; no default user was inserted.';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>PHV setup</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-page">
  <div class="login-card">
    <h1>Setup complete</h1>
    <p><?= $msg ?></p>
    <p><strong>Delete <code>setup.php</code> now.</strong></p>
    <p><a href="login.php">Go to login</a></p>
  </div>
</body>
</html>
