<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/hpv_results.php';

/** Facility prefix — full ID: NTHC/{file}/{patient} e.g. NTHC/01/05 */
function client_id_prefix(): string
{
    return defined('CLIENT_ID_PREFIX') ? CLIENT_ID_PREFIX : 'NTHC/';
}

/** Normalize one 2-digit part (file or patient-in-file). */
function normalize_client_id_part(string $part): string
{
    $digits = preg_replace('/\D+/', '', trim($part)) ?? '';
    if ($digits === '' || strlen($digits) > 2) {
        return '';
    }

    return str_pad($digits, 2, '0', STR_PAD_LEFT);
}

function build_client_id_from_parts(string $fileNo, string $patientNo): string
{
    $file = normalize_client_id_part($fileNo);
    $patient = normalize_client_id_part($patientNo);
    if ($file === '' || $patient === '') {
        return '';
    }

    return client_id_prefix() . $file . '/' . $patient;
}

/** @deprecated Legacy single-suffix IDs (NC/NTHC/001/022) */
function normalize_client_id_suffix(string $suffix): string
{
    $suffix = trim($suffix);
    $suffix = preg_replace('/\s+/', '', $suffix) ?? '';
    if ($suffix === '') {
        return '';
    }
    if (!preg_match('/^\d{1,6}$/', $suffix)) {
        return '';
    }

    return $suffix;
}

function build_client_id(string $suffix): string
{
    $suffix = normalize_client_id_suffix($suffix);
    if ($suffix === '') {
        return '';
    }

    return client_id_prefix() . $suffix;
}

function parse_client_id_from_body(array $body): string
{
    $file = trim((string) ($body['client_file_no'] ?? ''));
    $patient = trim((string) ($body['client_patient_no'] ?? ''));
    if ($file !== '' || $patient !== '') {
        return build_client_id_from_parts($file, $patient);
    }

    $suffix = normalize_client_id_suffix((string) ($body['client_no_suffix'] ?? ''));
    if ($suffix !== '') {
        return build_client_id($suffix);
    }

    $full = trim((string) ($body['external_mrn'] ?? ''));
    if ($full !== '') {
        return normalize_client_id_full($full);
    }

    return '';
}

function client_id_exists(string $clientId, ?int $excludePatientId = null): bool
{
    $clientId = trim($clientId);
    if ($clientId === '') {
        return false;
    }
    $sql = 'SELECT 1 FROM patients WHERE external_mrn = ?';
    $args = [$clientId];
    if ($excludePatientId !== null && $excludePatientId > 0) {
        $sql .= ' AND id <> ?';
        $args[] = $excludePatientId;
    }
    $sql .= ' LIMIT 1';
    $st = db()->prepare($sql);
    $st->execute($args);

    return (bool) $st->fetchColumn();
}

function normalize_client_id_full(string $ref): string
{
    $ref = trim($ref);
    if ($ref === '') {
        return '';
    }

    $prefix = client_id_prefix();
    if (str_starts_with($ref, $prefix)) {
        $rest = substr($ref, strlen($prefix));
        if (preg_match('/^(\d{1,2})\/(\d{1,2})$/', $rest, $m)) {
            return build_client_id_from_parts($m[1], $m[2]);
        }

        return $ref;
    }

    if (preg_match('/^(\d{1,2})\/(\d{1,2})$/', $ref, $m)) {
        return build_client_id_from_parts($m[1], $m[2]);
    }

    $digits = preg_replace('/\D+/', '', $ref) ?? '';
    if (strlen($digits) === 4) {
        return build_client_id_from_parts(substr($digits, 0, 2), substr($digits, 2, 2));
    }

    return build_client_id($ref);
}

/** Resolve internal row id from client serial (full ID, 01/05, or 0105). */
function resolve_patient_id_by_client_id(string $ref): ?int
{
    $clientId = normalize_client_id_full($ref);
    if ($clientId === '') {
        return null;
    }
    $st = db()->prepare('SELECT id FROM patients WHERE external_mrn = ? LIMIT 1');
    $st->execute([$clientId]);
    $id = $st->fetchColumn();

    return $id !== false ? (int) $id : null;
}

function ensure_client_id_unique_index(): void
{
    try {
        $pdo = db();
        $st = $pdo->prepare(
            "SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'patients'
               AND INDEX_NAME = 'uniq_patients_external_mrn' LIMIT 1"
        );
        $st->execute();
        if (!$st->fetchColumn() && db_table_has_column('patients', 'external_mrn')) {
            $pdo->exec('ALTER TABLE patients ADD UNIQUE KEY uniq_patients_external_mrn (external_mrn)');
        }
    } catch (Throwable $e) {
        error_log('ensure_client_id_unique_index: ' . $e->getMessage());
    }
}

function validate_client_id_registration(string $clientId): ?string
{
    if ($clientId === '') {
        return 'Client number is required (file number and patient number, e.g. ' . client_id_prefix() . '01/05).';
    }
    if (!str_starts_with($clientId, client_id_prefix())) {
        return 'Client ID must start with ' . client_id_prefix();
    }
    $rest = substr($clientId, strlen(client_id_prefix()));
    if (!preg_match('/^\d{2}\/\d{2}$/', $rest)) {
        return 'Use two 2-digit numbers: ' . client_id_prefix() . 'file/patient (e.g. ' . client_id_prefix() . '01/05).';
    }
    if (client_id_exists($clientId)) {
        return 'This client number is already registered: ' . $clientId;
    }

    return null;
}

/** @return array{full_name: string, client_id: ?string, channel: string}|null */
function find_registered_phone(string $phone, ?string $channel = null): ?array
{
    $phone = trim($phone);
    if ($phone === '') {
        return null;
    }
    $sql = 'SELECT p.full_name, p.external_mrn AS client_id, cc.channel
            FROM contact_channels cc
            INNER JOIN patients p ON p.id = cc.patient_id
            WHERE cc.address = ?';
    $args = [$phone];
    if ($channel !== null && $channel !== '') {
        $sql .= ' AND cc.channel = ?';
        $args[] = $channel;
    }
    $sql .= ' ORDER BY cc.is_primary DESC, cc.id ASC LIMIT 1';
    $st = db()->prepare($sql);
    $st->execute($args);
    $row = $st->fetch();

    return $row !== false ? $row : null;
}

function validate_phone_registration(string $phone, string $channel): ?string
{
    $existing = find_registered_phone($phone);
    if ($existing === null) {
        return null;
    }
    $clientId = trim((string) ($existing['client_id'] ?? ''));
    if ($clientId !== '') {
        return 'This phone number is already registered for client ' . $clientId
            . '. Use a different number or open that patient\'s record.';
    }

    return 'This phone number is already registered. Please use a different number or contact the patient if they already exist.';
}

/** Client ID if another patient already uses this phone (E.164), or null. */
function phone_registered_by_other_patient(string $phone, int $excludePatientId): ?string
{
    $phone = trim($phone);
    if ($phone === '' || $excludePatientId < 1) {
        return null;
    }
    $st = db()->prepare(
        'SELECT p.external_mrn FROM contact_channels cc
         INNER JOIN patients p ON p.id = cc.patient_id
         WHERE cc.address = ? AND cc.patient_id <> ?
         LIMIT 1'
    );
    $st->execute([$phone, $excludePatientId]);
    $mrn = $st->fetchColumn();

    return $mrn !== false && trim((string) $mrn) !== '' ? (string) $mrn : null;
}

/**
 * Update primary contact phone (and optionally channel) for an existing patient.
 *
 * @return array{ok: bool, error?: string, phone?: string, channel?: string, phone_changed?: bool, old_phone?: ?string, messages_replayed?: ?array}
 */
function update_patient_primary_contact(int $patientId, string $phone, ?string $channel = null): array
{
    if ($patientId < 1) {
        return ['ok' => false, 'error' => 'patient_id is required'];
    }

    require_once __DIR__ . '/messaging.php';
    $phone = normalize_outbound_address($phone);
    if ($phone === '') {
        return ['ok' => false, 'error' => 'Enter a valid Kenya mobile number (9 digits after +254).'];
    }

    $st = db()->prepare('SELECT 1 FROM patients WHERE id = ? LIMIT 1');
    $st->execute([$patientId]);
    if (!$st->fetchColumn()) {
        return ['ok' => false, 'error' => 'Patient not found'];
    }

    $otherClient = phone_registered_by_other_patient($phone, $patientId);
    if ($otherClient !== null) {
        return [
            'ok' => false,
            'error' => 'This phone number is already registered for client ' . $otherClient . '.',
        ];
    }

    $channelNorm = null;
    if ($channel !== null && trim($channel) !== '') {
        $channelNorm = strtolower(trim($channel)) === 'whatsapp' ? 'whatsapp' : 'sms';
    }

    $contactSt = db()->prepare(
        'SELECT id, channel, address FROM contact_channels
         WHERE patient_id = ?
         ORDER BY is_primary DESC, id ASC
         LIMIT 1'
    );
    $contactSt->execute([$patientId]);
    $contact = $contactSt->fetch();
    $oldPhone = $contact ? normalize_outbound_address((string) ($contact['address'] ?? '')) : '';
    $oldChannel = $contact ? (string) ($contact['channel'] ?? 'sms') : 'sms';

    try {
        if ($contact) {
            $contactId = (int) $contact['id'];
            $newChannel = $channelNorm ?? (string) ($contact['channel'] ?? 'sms');
            db()->prepare(
                'UPDATE contact_channels SET address = ?, channel = ?, updated_at = NOW(3) WHERE id = ?'
            )->execute([$phone, $newChannel, $contactId]);
            $channelNorm = $newChannel;
        } else {
            $channelNorm = $channelNorm ?? 'sms';
            db()->prepare(
                'INSERT INTO contact_channels (patient_id, channel, address, is_primary, opted_in, opted_in_at)
                 VALUES (?,?,?,?,?,?)'
            )->execute([$patientId, $channelNorm, $phone, 1, 1, date('Y-m-d H:i:s')]);
        }
    } catch (Throwable $e) {
        $mapped = map_registration_db_error($e);
        if ($mapped !== null) {
            return ['ok' => false, 'error' => $mapped['error']];
        }
        throw $e;
    }

    $phoneChanged = $oldPhone !== '' && $oldPhone !== $phone;
    $messagesReplayed = null;
    if ($phoneChanged) {
        require_once __DIR__ . '/patient_message_replay.php';
        $messagesReplayed = replay_patient_messages_after_phone_change($patientId);
    }

    return [
        'ok' => true,
        'phone' => $phone,
        'channel' => $channelNorm ?? 'sms',
        'phone_changed' => $phoneChanged,
        'old_phone' => $oldPhone !== '' ? $oldPhone : null,
        'messages_replayed' => $messagesReplayed,
    ];
}

/** @return array{error: string, status: int}|null */
function map_registration_db_error(Throwable $e): ?array
{
    $msg = $e->getMessage();
    $isDuplicate = str_contains($msg, 'Duplicate') || str_contains($msg, '1062');
    if ($e instanceof PDOException) {
        $info = $e->errorInfo ?? [];
        $isDuplicate = $isDuplicate || (($info[0] ?? '') === '23000');
    }
    if (!$isDuplicate) {
        return null;
    }
    if (str_contains($msg, 'uq_channel_address') || str_contains($msg, 'contact_channels')) {
        return [
            'error' => 'This phone number is already registered. Please use a different number or contact the patient if they already exist.',
            'status' => 422,
        ];
    }
    if (str_contains($msg, 'uniq_patients_external_mrn') || str_contains($msg, 'external_mrn')) {
        return [
            'error' => 'This client number is already registered.',
            'status' => 422,
        ];
    }

    return [
        'error' => 'This phone number or client number is already registered.',
        'status' => 422,
    ];
}

/**
 * Correct a patient's client number (staff correction). Does not change other data.
 *
 * @return array{ok: bool, error?: string, patient_id?: int, full_name?: string, old_client_id?: ?string, client_id?: string}
 */
function update_patient_client_id(int $patientId, string $newClientId): array
{
    if ($patientId < 1) {
        return ['ok' => false, 'error' => 'patient_id is required'];
    }

    $newClientId = normalize_client_id_full($newClientId);
    if ($newClientId === '') {
        return ['ok' => false, 'error' => 'Invalid client number format'];
    }
    if (!str_starts_with($newClientId, client_id_prefix())) {
        return ['ok' => false, 'error' => 'Client ID must start with ' . client_id_prefix()];
    }
    $rest = substr($newClientId, strlen(client_id_prefix()));
    if (!preg_match('/^\d{2}\/\d{2}$/', $rest)) {
        return ['ok' => false, 'error' => 'Use format ' . client_id_prefix() . 'file/patient (e.g. ' . client_id_prefix() . '10/10)'];
    }
    if (client_id_exists($newClientId, $patientId)) {
        return ['ok' => false, 'error' => 'Client number already in use: ' . $newClientId];
    }

    $st = db()->prepare('SELECT id, full_name, external_mrn FROM patients WHERE id = ? LIMIT 1');
    $st->execute([$patientId]);
    $row = $st->fetch();
    if (!$row) {
        return ['ok' => false, 'error' => 'Patient not found'];
    }

    $oldClientId = trim((string) ($row['external_mrn'] ?? ''));
    if ($oldClientId === $newClientId) {
        return [
            'ok' => true,
            'patient_id' => $patientId,
            'full_name' => (string) $row['full_name'],
            'old_client_id' => $oldClientId !== '' ? $oldClientId : null,
            'client_id' => $newClientId,
            'unchanged' => true,
        ];
    }

    try {
        db()->prepare('UPDATE patients SET external_mrn = ? WHERE id = ?')->execute([$newClientId, $patientId]);
    } catch (Throwable $e) {
        $mapped = map_registration_db_error($e);
        if ($mapped !== null) {
            return ['ok' => false, 'error' => $mapped['error']];
        }
        throw $e;
    }

    return [
        'ok' => true,
        'patient_id' => $patientId,
        'full_name' => (string) $row['full_name'],
        'old_client_id' => $oldClientId !== '' ? $oldClientId : null,
        'client_id' => $newClientId,
    ];
}
