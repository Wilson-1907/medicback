<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/hpv_results.php';

/** Facility client ID prefix (nurse enters digits after this). */
function client_id_prefix(): string
{
    return defined('CLIENT_ID_PREFIX') ? CLIENT_ID_PREFIX : 'NC/NTHC/001/';
}

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
    $suffix = normalize_client_id_suffix((string) ($body['client_no_suffix'] ?? ''));
    if ($suffix !== '') {
        return build_client_id($suffix);
    }
    $full = trim((string) ($body['external_mrn'] ?? ''));
    if ($full !== '' && str_starts_with($full, client_id_prefix())) {
        return $full;
    }
    return build_client_id($full);
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
    if (str_starts_with($ref, client_id_prefix())) {
        return $ref;
    }
    return build_client_id($ref);
}

/** Resolve internal row id from client serial (full ID or suffix digits only). */
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
        return 'Client number is required (enter the unique digits after ' . client_id_prefix() . ').';
    }
    if (!str_starts_with($clientId, client_id_prefix())) {
        return 'Client ID must start with ' . client_id_prefix();
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
