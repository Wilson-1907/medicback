<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/messaging.php';
require_login();

$id = (int) ($_GET['id'] ?? 0);
if ($id < 1) {
    header('Location: patients.php');
    exit;
}

/** HTML datetime-local → MySQL datetime */
function dt_mysql(string $html): string
{
    return str_replace('T', ' ', $html);
}

/** MySQL datetime → HTML datetime-local */
function dt_html(?string $mysql): string
{
    if ($mysql === null || $mysql === '') {
        return '';
    }
    return substr(str_replace(' ', 'T', $mysql), 0, 16);
}

$pdo = db();
$patientNameForMsgs = 'Patient';
$nameQuery = $pdo->prepare('SELECT full_name FROM patients WHERE id = ? LIMIT 1');
$nameQuery->execute([$id]);
$nameRow = $nameQuery->fetch();
if ($nameRow && !empty($nameRow['full_name'])) {
    $patientNameForMsgs = (string) $nameRow['full_name'];
}
$errors = [];
$flash = isset($_GET['saved']) ? 'Patient saved. Add an appointment below.' : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        $errors[] = 'Invalid session. Refresh and try again.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        try {
            if ($action === 'new_appt') {
                $start = trim((string) ($_POST['scheduled_start'] ?? ''));
                $reason = trim((string) ($_POST['appointment_reason'] ?? ''));
                if ($start === '') {
                    $errors[] = 'Choose date and time for the appointment.';
                } elseif ($reason === '') {
                    $errors[] = 'Appointment reason is required.';
                } else {
                    $startSql = dt_mysql($start);
                    $end = trim((string) ($_POST['scheduled_end'] ?? ''));
                    $endVal = $end === '' ? null : dt_mysql($end);
                    $department = $_POST['department'] !== '' ? trim((string) $_POST['department']) : null;
                    $providerName = $_POST['provider_name'] !== '' ? trim((string) $_POST['provider_name']) : null;
                    $location = $_POST['location'] !== '' ? trim((string) $_POST['location']) : null;

                    $pdo->beginTransaction();
                    $st = $pdo->prepare(
                        'INSERT INTO appointments (patient_id, department, provider_name, scheduled_start, scheduled_end, location, status)
                         VALUES (?,?,?,?,?,?,?)'
                    );
                    $st->execute([
                        $id,
                        $department,
                        $providerName,
                        $startSql,
                        $endVal,
                        $location,
                        'proposed',
                    ]);
                    $apptId = (int) $pdo->lastInsertId();
                    $hist = $pdo->prepare(
                        'INSERT INTO appointment_reschedule_events
                         (appointment_id, old_start, old_end, new_start, new_end, reason, initiated_by)
                         VALUES (?,?,?,?,?,?,?)'
                    );
                    $hist->execute([$apptId, $startSql, $endVal, $startSql, $endVal, $reason, 'staff']);
                    $pdo->commit();

                    send_appointment_bundle_messages($id, $patientNameForMsgs, [
                        'scheduled_start' => $startSql,
                        'scheduled_end' => $endVal,
                        'department' => $department,
                        'provider_name' => $providerName,
                        'location' => $location,
                    ], $reason, false);
                    $flash = 'Appointment added.';
                }
            } elseif ($action === 'confirm_appt') {
                $aid = (int) ($_POST['appointment_id'] ?? 0);
                if ($aid > 0) {
                    $u = $pdo->prepare(
                        "UPDATE appointments SET status = 'confirmed', confirmation_at = NOW(3) WHERE id = ? AND patient_id = ?"
                    );
                    $u->execute([$aid, $id]);
                    send_patient_message($id, 'education_menu', build_engagement_menu_message());
                    $flash = 'Appointment marked confirmed.';
                }
            } elseif ($action === 'reschedule_appt') {
                $aid = (int) ($_POST['appointment_id'] ?? 0);
                $newStart = trim((string) ($_POST['new_scheduled_start'] ?? ''));
                $newReason = trim((string) ($_POST['reschedule_reason'] ?? ''));
                if ($aid < 1 || $newStart === '') {
                    $errors[] = 'Choose appointment and new date/time.';
                } elseif ($newReason === '') {
                    $errors[] = 'Reschedule reason is required.';
                } else {
                    $currentQ = $pdo->prepare(
                        'SELECT id, scheduled_start, scheduled_end, department, provider_name, location
                         FROM appointments
                         WHERE id = ? AND patient_id = ?
                         LIMIT 1'
                    );
                    $currentQ->execute([$aid, $id]);
                    $current = $currentQ->fetch();
                    if (!$current) {
                        $errors[] = 'Appointment not found for this patient.';
                    } else {
                        $newStartSql = dt_mysql($newStart);
                        $newEnd = trim((string) ($_POST['new_scheduled_end'] ?? ''));
                        $newEndVal = $newEnd === '' ? null : dt_mysql($newEnd);
                        $pdo->beginTransaction();
                        $up = $pdo->prepare(
                            'UPDATE appointments
                             SET scheduled_start = ?, scheduled_end = ?, updated_at = NOW(3)
                             WHERE id = ? AND patient_id = ?'
                        );
                        $up->execute([$newStartSql, $newEndVal, $aid, $id]);
                        $hist = $pdo->prepare(
                            'INSERT INTO appointment_reschedule_events
                             (appointment_id, old_start, old_end, new_start, new_end, reason, initiated_by)
                             VALUES (?,?,?,?,?,?,?)'
                        );
                        $hist->execute([
                            $aid,
                            $current['scheduled_start'],
                            $current['scheduled_end'],
                            $newStartSql,
                            $newEndVal,
                            $newReason,
                            'staff',
                        ]);
                        $pdo->commit();

                        send_appointment_bundle_messages($id, $patientNameForMsgs, [
                            'scheduled_start' => $newStartSql,
                            'scheduled_end' => $newEndVal,
                            'department' => $current['department'],
                            'provider_name' => $current['provider_name'],
                            'location' => $current['location'],
                        ], $newReason, true);
                        $flash = 'Appointment rescheduled and patient notified.';
                    }
                }
            } elseif ($action === 'remove_patient') {
                $removeReason = trim((string) ($_POST['remove_reason'] ?? ''));
                if ($removeReason === '') {
                    $errors[] = 'Removal reason is required.';
                } else {
                    $notesQ = $pdo->prepare('SELECT notes FROM patients WHERE id = ? LIMIT 1');
                    $notesQ->execute([$id]);
                    $notesRow = $notesQ->fetch();
                    $existingNotes = $notesRow ? (string) ($notesRow['notes'] ?? '') : '';
                    $auditLine = '[' . date('Y-m-d H:i:s') . '] Removed from active system by admin. Reason: ' . $removeReason;
                    $newNotes = trim($existingNotes) === '' ? $auditLine : $existingNotes . "\n" . $auditLine;
                    $up = $pdo->prepare('UPDATE patients SET status = ?, notes = ? WHERE id = ?');
                    $up->execute(['withdrawn', $newNotes, $id]);
                    header('Location: patients.php?removed=' . urlencode('Patient #' . $id . ' removed from active system.'));
                    exit;
                }
            } elseif ($action === 'new_dx') {
                $label = trim((string) ($_POST['diagnosis_label'] ?? ''));
                if ($label === '') {
                    $errors[] = 'Diagnosis label is required.';
                } else {
                    $apptId = (int) ($_POST['appointment_id'] ?? 0);
                    $apptVal = $apptId > 0 ? $apptId : null;
                    $st = $pdo->prepare(
                        'INSERT INTO diagnosis_results (patient_id, appointment_id, coded_diagnosis, diagnosis_label, severity, result_summary, recorded_by)
                         VALUES (?,?,?,?,?,?,?)'
                    );
                    $sev = (string) ($_POST['severity'] ?? 'unknown');
                    if (!in_array($sev, ['unknown', 'mild', 'moderate', 'severe'], true)) {
                        $sev = 'unknown';
                    }
                    $st->execute([
                        $id,
                        $apptVal,
                        $_POST['coded_diagnosis'] !== '' ? trim((string) $_POST['coded_diagnosis']) : null,
                        $label,
                        $sev,
                        $_POST['result_summary'] !== '' ? trim((string) $_POST['result_summary']) : null,
                        (string) ($_SESSION['staff_username'] ?? 'staff'),
                    ]);
                    $flash = 'Diagnosis result recorded.';
                }
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = $e->getMessage();
        }
    }
}

$st = $pdo->prepare('SELECT * FROM patients WHERE id = ? LIMIT 1');
$st->execute([$id]);
$patient = $st->fetch();
if (!$patient) {
    header('Location: patients.php');
    exit;
}

$contacts = $pdo->prepare('SELECT * FROM contact_channels WHERE patient_id = ? ORDER BY is_primary DESC, id ASC');
$contacts->execute([$id]);
$channels = $contacts->fetchAll();

$appts = $pdo->prepare(
    'SELECT a.*,
            (SELECT e.reason
             FROM appointment_reschedule_events e
             WHERE e.appointment_id = a.id
             ORDER BY e.created_at DESC, e.id DESC
             LIMIT 1) AS latest_reason
     FROM appointments a
     WHERE a.patient_id = ?
     ORDER BY a.scheduled_start DESC
     LIMIT 50'
);
$appts->execute([$id]);
$appointments = $appts->fetchAll();

$dx = $pdo->prepare(
    'SELECT d.*, a.scheduled_start AS appt_time
     FROM diagnosis_results d
     LEFT JOIN appointments a ON a.id = d.appointment_id
     WHERE d.patient_id = ?
     ORDER BY d.recorded_at DESC LIMIT 30'
);
$dx->execute([$id]);
$diagnoses = $dx->fetchAll();

$csrf = csrf_token();
layout_header($patient['full_name']);
?>
<?php if ($flash !== ''): ?>
  <div class="alert alert-success"><?= h($flash) ?></div>
<?php endif; ?>
<?php foreach ($errors as $e): ?>
  <div class="alert alert-error"><?= h($e) ?></div>
<?php endforeach; ?>

<div class="card">
  <div class="actions" style="margin-bottom:1rem">
    <a class="btn btn-secondary" href="patients.php">← All patients</a>
  </div>
  <h1><?= h($patient['full_name']) ?></h1>
  <p style="color:var(--muted);margin:0">
    ID <?= (int) $patient['id'] ?>
    <?php if (!empty($patient['external_mrn'])): ?> · MRN <?= h($patient['external_mrn']) ?><?php endif; ?>
    · <?= h($patient['status']) ?>
  </p>
  <table class="data" style="margin-top:1rem;max-width:520px">
    <tbody>
      <tr><th style="width:140px">Date of birth</th><td><?= $patient['date_of_birth'] ? h($patient['date_of_birth']) : '—' ?></td></tr>
      <tr><th>Language</th><td><?= h($patient['preferred_language']) ?></td></tr>
      <tr><th>Registered</th><td><?= h($patient['registration_at']) ?></td></tr>
      <?php if (!empty($patient['notes'])): ?>
        <tr><th>Notes</th><td><?= nl2br(h($patient['notes'])) ?></td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<div class="card">
  <h2>Contact & preferences</h2>
  <?php if ($channels === []): ?>
    <p style="color:var(--muted)">No contact on file.</p>
  <?php else: ?>
    <table class="data">
      <thead>
        <tr><th>Channel</th><th>Address</th><th>Primary</th><th>Opted in</th></tr>
      </thead>
      <tbody>
        <?php foreach ($channels as $c): ?>
          <tr>
            <td><?= h($c['channel']) ?></td>
            <td><?= h($c['address']) ?></td>
            <td><?= (int) $c['is_primary'] ? 'Yes' : 'No' ?></td>
            <td><?= (int) $c['opted_in'] ? 'Yes' : 'No' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<div class="grid-2">
  <div class="card">
    <h2>Add appointment</h2>
    <form method="post" action="patient_view.php?id=<?= $id ?>">
      <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
      <input type="hidden" name="action" value="new_appt">
      <div class="field">
        <label for="scheduled_start">Date & time</label>
        <input id="scheduled_start" name="scheduled_start" type="datetime-local" required>
      </div>
      <div class="field">
        <label for="scheduled_end">End (optional)</label>
        <input id="scheduled_end" name="scheduled_end" type="datetime-local">
      </div>
      <div class="field">
        <label for="department">Department / clinic</label>
        <input id="department" name="department" type="text" placeholder="e.g. PHV clinic">
      </div>
      <div class="field">
        <label for="provider_name">Provider</label>
        <input id="provider_name" name="provider_name" type="text">
      </div>
      <div class="field">
        <label for="location">Location</label>
        <input id="location" name="location" type="text" placeholder="Building / room">
      </div>
      <div class="field">
        <label for="appointment_reason">Reason (required)</label>
        <textarea id="appointment_reason" name="appointment_reason" required placeholder="Why this appointment is being planned"></textarea>
      </div>
      <button class="btn" type="submit">Save appointment</button>
    </form>
  </div>

  <div class="card">
    <h2>Record diagnosis result</h2>
    <form method="post" action="patient_view.php?id=<?= $id ?>">
      <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
      <input type="hidden" name="action" value="new_dx">
      <div class="field">
        <label for="diagnosis_label">Diagnosis / result label</label>
        <input id="diagnosis_label" name="diagnosis_label" type="text" required>
      </div>
      <div class="field">
        <label for="coded_diagnosis">Code (optional)</label>
        <input id="coded_diagnosis" name="coded_diagnosis" type="text" placeholder="ICD-10">
      </div>
      <div class="field">
        <label for="severity">Severity</label>
        <select id="severity" name="severity">
          <?php foreach (['unknown', 'mild', 'moderate', 'severe'] as $sev): ?>
            <option value="<?= h($sev) ?>"><?= h($sev) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="appointment_id">Link to appointment (optional)</label>
        <select id="appointment_id" name="appointment_id">
          <option value="0">— None —</option>
          <?php foreach ($appointments as $a): ?>
            <option value="<?= (int) $a['id'] ?>"><?= h($a['scheduled_start']) ?> (<?= h($a['status']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="result_summary">Summary for care team (optional)</label>
        <textarea id="result_summary" name="result_summary"></textarea>
      </div>
      <button class="btn" type="submit">Save result</button>
    </form>
  </div>
</div>

<div class="grid-2">
  <div class="card">
    <h2>Reschedule appointment</h2>
    <?php if ($appointments === []): ?>
      <p style="color:var(--muted)">No appointment available to reschedule yet.</p>
    <?php else: ?>
      <form method="post" action="patient_view.php?id=<?= $id ?>">
        <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
        <input type="hidden" name="action" value="reschedule_appt">
        <div class="field">
          <label for="appointment_id_reschedule">Select appointment</label>
          <select id="appointment_id_reschedule" name="appointment_id" required>
            <?php foreach ($appointments as $a): ?>
              <option value="<?= (int) $a['id'] ?>">
                <?= h($a['scheduled_start']) ?> (<?= h($a['status']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="new_scheduled_start">New date & time</label>
          <input id="new_scheduled_start" name="new_scheduled_start" type="datetime-local" required>
        </div>
        <div class="field">
          <label for="new_scheduled_end">New end (optional)</label>
          <input id="new_scheduled_end" name="new_scheduled_end" type="datetime-local">
        </div>
        <div class="field">
          <label for="reschedule_reason">Reason for change (required)</label>
          <textarea id="reschedule_reason" name="reschedule_reason" required placeholder="Explain why appointment date changed"></textarea>
        </div>
        <button class="btn" type="submit">Save new date & notify patient</button>
      </form>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>Remove from active system</h2>
    <p style="color:var(--muted)">Marks patient as withdrawn. Appointment and diagnosis history is kept for audit.</p>
    <form method="post" action="patient_view.php?id=<?= $id ?>">
      <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
      <input type="hidden" name="action" value="remove_patient">
      <div class="field">
        <label for="remove_reason">Reason (required)</label>
        <textarea id="remove_reason" name="remove_reason" required placeholder="Why patient should be removed from active workflow"></textarea>
      </div>
      <button class="btn" type="submit" style="background:#b42318">Remove patient</button>
    </form>
  </div>
</div>

<div class="card">
  <h2>Appointments</h2>
  <?php if ($appointments === []): ?>
    <p style="color:var(--muted)">No appointments yet. Use the form above.</p>
  <?php else: ?>
    <table class="data">
      <thead>
        <tr>
          <th>When</th>
          <th>Department</th>
          <th>Provider</th>
          <th>Reason</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($appointments as $a): ?>
          <tr>
            <td><?= h($a['scheduled_start']) ?></td>
            <td><?= h($a['department'] ?? '—') ?></td>
            <td><?= h($a['provider_name'] ?? '—') ?></td>
            <td><?= h($a['latest_reason'] ?? '—') ?></td>
            <td><?= h($a['status']) ?></td>
            <td>
              <?php if ($a['status'] === 'proposed'): ?>
                <form method="post" action="patient_view.php?id=<?= $id ?>" style="display:inline">
                  <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
                  <input type="hidden" name="action" value="confirm_appt">
                  <input type="hidden" name="appointment_id" value="<?= (int) $a['id'] ?>">
                  <button class="btn" type="submit" style="padding:0.35rem 0.65rem;font-size:0.85rem">Confirm</button>
                </form>
              <?php else: ?>
                —
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Diagnosis results</h2>
  <?php if ($diagnoses === []): ?>
    <p style="color:var(--muted)">No results logged.</p>
  <?php else: ?>
    <table class="data">
      <thead>
        <tr>
          <th>Recorded</th>
          <th>Label</th>
          <th>Severity</th>
          <th>Appointment</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($diagnoses as $d): ?>
          <tr>
            <td><?= h($d['recorded_at']) ?></td>
            <td><?= h($d['diagnosis_label']) ?></td>
            <td><?= h($d['severity']) ?></td>
            <td><?= $d['appt_time'] ? h($d['appt_time']) : '—' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php
layout_footer();
