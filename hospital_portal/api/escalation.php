<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../doctor_call_requests.php';

function escalation_row_with_meta(array $row): array
{
    $row['awaiting_doctor_reason'] = is_awaiting_doctor_reason_row([
        'status' => $row['doctor_call_status'] ?? '',
        'reason' => $row['doctor_call_reason'] ?? '',
    ]);
    $row['patient_stated_reason'] = patient_stated_call_reason(
        ['reason' => $row['doctor_call_reason'] ?? ''],
        $row['reason'] ?? ''
    );
    return $row;
}

function fetch_escalation_by_id(int $id): ?array
{
    $pdo = db();
    $st = $pdo->prepare(
        "SELECT e.id, e.patient_id, p.external_mrn AS client_id, e.created_at, e.status, e.urgency, e.reason, e.updated_at, p.full_name,
                (SELECT cc.address FROM contact_channels cc
                 WHERE cc.patient_id = p.id AND cc.is_primary = 1 LIMIT 1) AS phone,
                (SELECT cc.channel FROM contact_channels cc
                 WHERE cc.patient_id = p.id AND cc.is_primary = 1 LIMIT 1) AS channel,
                dcr.status AS doctor_call_status,
                dcr.requested_at AS doctor_call_requested_at,
                dcr.updated_at AS doctor_call_updated_at,
                dcr.reason AS doctor_call_reason,
                (SELECT i.body FROM inbound_messages i
                 WHERE i.patient_id = p.id ORDER BY i.received_at DESC, i.id DESC LIMIT 1) AS last_inbound_body,
                (SELECT i.received_at FROM inbound_messages i
                 WHERE i.patient_id = p.id ORDER BY i.received_at DESC, i.id DESC LIMIT 1) AS last_inbound_at
         FROM escalations e
         INNER JOIN patients p ON p.id = e.patient_id
         LEFT JOIN doctor_call_requests dcr ON dcr.patient_id = e.patient_id
         WHERE e.id = ?
         LIMIT 1"
    );
    $st->execute([$id]);
    $row = $st->fetch();
    return $row ? escalation_row_with_meta($row) : null;
}

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'POST') {
        $body = api_body();
        $action = (string) ($body['action'] ?? '');

        if ($action === 'mark_called') {
            $escalationId = (int) ($body['escalation_id'] ?? 0);
            $patientId = (int) ($body['patient_id'] ?? 0);
            if ($escalationId < 1 && $patientId < 1) {
                api_json(['ok' => false, 'error' => 'escalation_id or patient_id is required'], 422);
            }
            $out = mark_specialist_request_contacted(
                $patientId,
                $escalationId > 0 ? $escalationId : null
            );
            if (empty($out['ok'])) {
                api_json($out, 422);
            }
            $out['message'] = 'Marked as called. Removed from open escalations.';
            api_json($out);
        }

        api_json(['ok' => false, 'error' => 'Unknown action. Use mark_called'], 422);
    }

    if ($method !== 'GET') {
        api_json(['ok' => false, 'error' => 'GET or POST required'], 405);
    }

    $id = (int) ($_GET['id'] ?? 0);
    if ($id < 1) {
        api_json(['ok' => false, 'error' => 'id is required'], 422);
    }

    $row = fetch_escalation_by_id($id);
    if (!$row) {
        api_json(['ok' => false, 'error' => 'Escalation not found'], 404);
    }

    api_json(['ok' => true, 'escalation' => $row]);
} catch (Throwable $e) {
    api_json(['ok' => false, 'error' => $e->getMessage()], 500);
}
