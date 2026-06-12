<?php
declare(strict_types=1);

/**
 * Lightweight keep-alive for Render free tier (no DB). Use with cron-job.org every 5 min.
 */
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

echo json_encode([
    'ok' => true,
    'service' => 'medicback',
    'ts' => gmdate('Y-m-d\TH:i:s\Z'),
], JSON_UNESCAPED_SLASHES);
