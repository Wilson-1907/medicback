<?php
declare(strict_types=1);

function layout_header(string $title): void
{
    $full = h($title) . ' — ' . APP_NAME;
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $full ?></title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <header class="topbar">
    <div class="topbar-inner">
      <div class="topbar-title"><?= h(APP_NAME) ?></div>
      <nav class="topbar-nav">
        <a href="index.php">Dashboard</a>
        <a href="patients.php">Patients</a>
        <a href="patient_new.php">Register patient</a>
        <a href="message_center.php">Message center</a>
      </nav>
    </div>
  </header>
  <main class="wrap">
<?php
}

function layout_footer(): void
{
    ?>
  </main>
</body>
</html>
<?php
}
