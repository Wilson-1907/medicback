<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/messaging.php';

function reminder_dispatch_query(string $column, string $whenExpr): array
{
    $pdo = db();
    $sql = "SELECT a.id, a.patient_id, p.full_name, a.scheduled_start, a.scheduled_end, a.department, a.provider_name, a.location,
                   (SELECT e.reason FROM appointment_reschedule_events e WHERE e.appointment_id = a.id ORDER BY e.created_at DESC, e.id DESC LIMIT 1) AS latest_reason
            FROM appointments a
            INNER JOIN patients p ON p.id = a.patient_id
            WHERE a.status IN ('proposed','confirmed')
              AND a.{$column} IS NULL
              AND NOW() >= {$whenExpr}
            ORDER BY a.scheduled_start ASC
            LIMIT 200";
    return $pdo->query($sql)->fetchAll();
}

function mark_reminder_sent(int $appointmentId, string $column): void
{
    $st = db()->prepare("UPDATE appointments SET {$column} = NOW(3) WHERE id = ?");
    $st->execute([$appointmentId]);
}

function process_due_appointment_reminders(): array
{
    $sent = ['7d' => 0, '3d' => 0, 'night' => 0];

    $types = [
        '7d' => ['column' => 'reminder_7d_sent_at', 'when' => 'DATE_SUB(a.scheduled_start, INTERVAL 7 DAY)'],
        '3d' => ['column' => 'reminder_3d_sent_at', 'when' => 'DATE_SUB(a.scheduled_start, INTERVAL 3 DAY)'],
        'night' => ['column' => 'reminder_night_sent_at', 'when' => "TIMESTAMP(DATE_SUB(DATE(a.scheduled_start), INTERVAL 1 DAY), '20:00:00')"],
    ];

    foreach ($types as $key => $cfg) {
        $rows = reminder_dispatch_query($cfg['column'], $cfg['when']);
        foreach ($rows as $r) {
            $ordinal = $key === '7d' ? 1 : ($key === '3d' ? 2 : 3);
            $msg = build_appointment_reminder_message((string) $r['full_name'], [
                'scheduled_start' => $r['scheduled_start'],
                'scheduled_end' => $r['scheduled_end'],
                'department' => $r['department'],
                'provider_name' => $r['provider_name'],
                'location' => $r['location'],
            ], (string) ($r['latest_reason'] ?? ''), $ordinal, 3);
            send_patient_message((int) $r['patient_id'], 'appointment_reminder', $msg);
            mark_reminder_sent((int) $r['id'], $cfg['column']);
            $sent[$key]++;
        }
    }

    return $sent;
}
