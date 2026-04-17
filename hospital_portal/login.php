<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

portal_session();
header('Location: index.php', true, 302);
exit;
