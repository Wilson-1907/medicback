<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

try {
    $pdo = db();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET') {
        $q = trim((string) ($_GET['q'] ?? ''));
        $sql = 'SELECT p.id, p.full_name, p.status, p.registration_at,
                (SELECT cc.channel FROM contact_channels cc WHERE cc.patient_id = p.id AND cc.is_primary = 1 LIMIT 1) AS primary_channel
                FROM patients p';
        $args = [];
        if ($q !== '') {
            $sql .= ' WHERE p.full_name LIKE ? OR p.external_mrn LIKE ? OR p.id = ?';
            $like = '%' . $q . '%';
            $args = [$like, $like, ctype_digit($q) ? $q : -1];
        }
        $sql .= ' ORDER BY p.full_name ASC LIMIT 300';
        $st = $pdo->prepare($sql);
        $st->execute($args);
        api_json(['ok' => true, 'items' => $st->fetchAll()]);
    }

    if ($method !== 'POST') {
        api_json(['ok' => false, 'error' => 'Method not allowed'], 405);
    }

    $body = api_body();
    $name = trim((string) ($body['full_name'] ?? ''));
    $dob = trim((string) ($body['date_of_birth'] ?? ''));
    $lang = trim((string) ($body['preferred_language'] ?? 'en')) ?: 'en';
    $mrn = trim((string) ($body['external_mrn'] ?? ''));
    $notes = trim((string) ($body['notes'] ?? ''));
    $phone = api_phone((string) ($body['phone'] ?? ''));
    $channel = ((string) ($body['contact_channel'] ?? 'sms')) === 'whatsapp' ? 'whatsapp' : 'sms';
    $optIn = !empty($body['opt_in']);

    if ($name === '') {
        api_json(['ok' => false, 'error' => 'Full name is required'], 422);
    }
    if ($phone === '' || strlen($phone) < 8) {
        api_json(['ok' => false, 'error' => 'Valid phone number is required'], 422);
    }

    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare(
            'INSERT INTO patients (full_name, date_of_birth, preferred_language, external_mrn, notes, status)
             VALUES (?,?,?,?,?,?)'
        );
        $st->execute([
            $name,
            $dob === '' ? null : $dob,
            $lang,
            $mrn === '' ? null : $mrn,
            $notes === '' ? null : $notes,
            'active',
        ]);
        $pid = (int) $pdo->lastInsertId();

        $ch = $pdo->prepare(
            'INSERT INTO contact_channels (patient_id, channel, address, is_primary, opted_in, opted_in_at)
             VALUES (?,?,?,?,?,?)'
        );
        $ch->execute([$pid, $channel, $phone, 1, $optIn ? 1 : 0, $optIn ? date('Y-m-d H:i:s') : null]);

        $ev = $pdo->prepare(
            'INSERT INTO contact_preference_events (patient_id, channel, action, source)
             VALUES (?,?,?,?)'
        );
        $ev->execute([$pid, $channel, $optIn ? 'opt_in' : 'opt_out', 'frontend_registration']);
        $pdo->commit();

        if ($optIn) {
            send_patient_message($pid, 'welcome', build_welcome_message($name));
        }
        api_json(['ok' => true, 'patient_id' => $pid], 201);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        api_json(['ok' => false, 'error' => $e->getMessage()], 500);
    }
} catch (Throwable $e) {
    api_json(['ok' => false, 'error' => $e->getMessage()], 500);
}
