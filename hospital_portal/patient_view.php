<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/messaging.php';
require_once __DIR__ . '/appointment_utils.php';
require_once __DIR__ . '/patient_age.php';
require_once __DIR__ . '/hpv_results.php';
require_once __DIR__ . '/patient_screening.php';
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
$patientLangForMsgs = 'en';
$nameQuery = $pdo->prepare('SELECT full_name, preferred_language FROM patients WHERE id = ? LIMIT 1');
$nameQuery->execute([$id]);
$nameRow = $nameQuery->fetch();
if ($nameRow && !empty($nameRow['full_name'])) {
    $patientNameForMsgs = (string) $nameRow['full_name'];
}
if ($nameRow && strtolower((string) ($nameRow['preferred_language'] ?? 'en')) === 'sw') {
    $patientLangForMsgs = 'sw';
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

                    if (appointment_slot_taken($id, $startSql)) {
                        $errors[] = 'This patient already has an appointment at that date and time.';
                    } else {
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

                    send_patient_message(
                        $id,
                        'appointment_booked',
                        build_appointment_change_message($patientNameForMsgs, [
                            'scheduled_start' => $startSql,
                            'scheduled_end' => $endVal,
                            'department' => $department,
                            'provider_name' => $providerName,
                            'location' => $location,
                        ], $reason, false, $patientLangForMsgs)
                    );
                    $flash = 'Appointment added.';
                    }
                }
            } elseif ($action === 'confirm_appt') {
                $aid = (int) ($_POST['appointment_id'] ?? 0);
                if ($aid > 0) {
                    $u = $pdo->prepare(
                        "UPDATE appointments SET status = 'confirmed', confirmation_at = NOW(3) WHERE id = ? AND patient_id = ?"
                    );
                    $u->execute([$aid, $id]);
                    $flash = 'Appointment marked confirmed.';
                }
            } elseif ($action === 'mark_attended') {
                $out = mark_appointment_attended((int) ($_POST['appointment_id'] ?? 0), (string) ($_SESSION['staff_username'] ?? 'staff'));
                if (!empty($out['ok'])) {
                    $flash = !empty($out['record_via_next'])
                        ? 'Marked as attended. Record the VIA result below.'
                        : 'Marked as attended.';
                } else {
                    $errors[] = $out['error'] ?? 'Failed to mark attended';
                }
            } elseif ($action === 'mark_missed') {
                $out = mark_appointment_missed((int) ($_POST['appointment_id'] ?? 0), (string) ($_SESSION['staff_username'] ?? 'staff'));
                if (!empty($out['ok'])) {
                    $flash = !empty($out['missed_message_sent'])
                        ? 'Marked as missed. Patient notified by SMS/WhatsApp.'
                        : 'Marked as missed.';
                } else {
                    $errors[] = $out['error'] ?? 'Failed to mark missed';
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
                        if (appointment_slot_taken($id, $newStartSql, $aid)) {
                            $errors[] = 'This patient already has another appointment at that date and time.';
                        } else {
                        $pdo->beginTransaction();
                        $up = $pdo->prepare(
                            'UPDATE appointments
                             SET scheduled_start = ?, scheduled_end = ?, reminder_7d_sent_at = NULL, reminder_3d_sent_at = NULL, reminder_night_sent_at = NULL, updated_at = NOW(3)
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

                        send_patient_message(
                            $id,
                            'appointment_rescheduled',
                            build_appointment_change_message($patientNameForMsgs, [
                                'scheduled_start' => $newStartSql,
                                'scheduled_end' => $newEndVal,
                                'department' => $current['department'],
                                'provider_name' => $current['provider_name'],
                                'location' => $current['location'],
                            ], $newReason, true, $patientLangForMsgs)
                        );
                        $flash = 'Appointment rescheduled and patient notified.';
                        }
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
            } elseif ($action === 'hpv_set_positive') {
                $out = set_patient_hpv_result($id, 'positive', (string) ($_SESSION['staff_username'] ?? 'staff'));
                if (!empty($out['ok'])) {
                    header('Location: patient_view.php?id=' . $id . '&book_appt=1#add-appointment');
                    exit;
                }
                $flash = $out['error'] ?? 'Failed to record HPV positive';
                $errors[] = $flash;
            } elseif ($action === 'hpv_set_negative') {
                $out = set_patient_hpv_result($id, 'negative', (string) ($_SESSION['staff_username'] ?? 'staff'));
                $flash = !empty($out['ok']) ? 'HPV result recorded as NEGATIVE (not yet sent to patient).' : ($out['error'] ?? 'Failed');
                if (empty($out['ok'])) {
                    $errors[] = $flash;
                }
            } elseif ($action === 'hpv_confirm') {
                $out = confirm_patient_hpv_result($id, (string) ($_SESSION['staff_username'] ?? 'staff'));
                $flash = !empty($out['ok'])
                    ? 'Result confirmed and guidance messages sent to patient.'
                    : ($out['error'] ?? 'Failed');
                if (empty($out['ok'])) {
                    $errors[] = $flash;
                }
            } elseif ($action === 'record_via') {
                $viaResult = (string) ($_POST['via_result'] ?? '');
                $viaDate = trim((string) ($_POST['via_date'] ?? ''));
                $hasCancer = !empty($_POST['has_cancer']);
                $treatmentDate = trim((string) ($_POST['treatment_date'] ?? ''));
                $out = record_patient_via_result(
                    $id,
                    $viaResult,
                    $viaDate,
                    $hasCancer,
                    $treatmentDate === '' ? null : $treatmentDate,
                    (string) ($_SESSION['staff_username'] ?? 'staff')
                );
                if (!empty($out['ok'])) {
                    $flash = 'VIA result recorded.';
                    if (!empty($out['referral_sent'])) {
                        $flash .= ' Referral SMS sent.';
                    }
                } else {
                    $errors[] = $out['error'] ?? 'Failed to record VIA result.';
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
      <tr><th style="width:140px">Age</th><td><?php $dispAge = patient_display_age($patient); echo $dispAge !== null ? h((string) $dispAge) : '—'; ?></td></tr>
      <tr><th>Date of birth</th><td><?= $patient['date_of_birth'] ? h($patient['date_of_birth']) : '—' ?></td></tr>
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
    <h2 id="add-appointment">Add appointment</h2>
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
        <input id="department" name="department" type="text" placeholder="e.g. HPV clinic">
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

  <?php if (hpv_workflow_ready()): ?>
  <?php
    $hpvResult = strtolower((string) ($patient['hpv_screening_result'] ?? 'pending'));
    $hpvRecorded = !empty($patient['hpv_result_recorded_at']);
    $hpvConfirmed = !empty($patient['hpv_result_confirmed_at']);
    $hpvHasResult = in_array($hpvResult, ['positive', 'negative'], true);
    $hpvNeedsAppt = $hpvResult === 'positive' && !patient_has_upcoming_appointment($id, $appointments);
  ?>
  <div class="card" style="border-left:4px solid var(--accent);">
    <h2>HPV screening result (Afya Rafiki)</h2>
    <?php if ($hpvConfirmed && $hpvHasResult): ?>
      <p><strong>Status:</strong> <?= h(strtoupper($hpvResult)) ?>
        — <span style="color:var(--success)">Sent to patient <?= h((string) $patient['hpv_result_confirmed_at']) ?></span>
      </p>
    <?php elseif ($hpvRecorded && $hpvHasResult): ?>
      <p><strong><?= h(strtoupper($hpvResult)) ?></strong> recorded on <?= h((string) $patient['hpv_result_recorded_at']) ?>.</p>
      <p class="field-hint">Awaiting confirm — notify the patient when ready.</p>
      <?php if ($hpvNeedsAppt): ?>
        <p class="field-hint" style="color:var(--warning)">Book a follow-up appointment first — the HPV positive message needs the date.</p>
      <?php else: ?>
        <form method="post" style="margin-top:12px" action="patient_view.php?id=<?= $id ?>" onsubmit="return confirm('Send confirmed result and start guidance messages to this patient?');">
          <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
          <input type="hidden" name="action" value="hpv_confirm">
          <button class="btn" type="submit" style="background:#198754;color:#fff">Confirm &amp; notify patient</button>
        </form>
      <?php endif; ?>
    <?php else: ?>
      <p class="field-hint">Record the lab result after review. You can confirm and notify the patient once recorded.</p>
      <form method="post" style="display:inline" action="patient_view.php?id=<?= $id ?>">
        <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
        <input type="hidden" name="action" value="hpv_set_positive">
        <button class="btn" type="submit">Record POSITIVE</button>
      </form>
      <form method="post" style="display:inline;margin-left:8px" action="patient_view.php?id=<?= $id ?>">
        <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
        <input type="hidden" name="action" value="hpv_set_negative">
        <button class="btn btn-secondary" type="submit">Record NEGATIVE</button>
      </form>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php if (patient_screening_ready() && patient_has_confirmed_appointment($id, $appointments)): ?>
  <?php
    $viaRecorded = in_array(strtolower((string) ($patient['via_result'] ?? '')), ['positive', 'negative'], true);
  ?>
  <div class="card" style="border-left:4px solid #6f42c1;">
    <h2>VIA result (after test)</h2>
    <?php if ($viaRecorded): ?>
      <p><strong>Result:</strong> <?= h(strtoupper((string) $patient['via_result'])) ?>
        · <strong>Date:</strong> <?= h((string) $patient['via_date']) ?>
        <?php if ((int) ($patient['has_cancer'] ?? 0) === 1): ?> · <span style="color:var(--warning)">Cancer — referral sent</span><?php endif; ?>
      </p>
      <?php if (!empty($patient['next_checkup_at'])): ?>
        <p style="color:var(--muted)">Next check-up: <?= h($patient['next_checkup_at']) ?></p>
      <?php endif; ?>
    <?php else: ?>
      <p class="field-hint">Record VIA after the patient has been tested. Follow-up SMS is sent when opted in.</p>
      <form method="post" action="patient_view.php?id=<?= $id ?>">
        <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
        <input type="hidden" name="action" value="record_via">
        <div class="field">
          <label for="via_result">Result</label>
          <select id="via_result" name="via_result" required>
            <option value="">—</option>
            <option value="negative">Negative</option>
            <option value="positive">Positive</option>
          </select>
        </div>
        <div class="field">
          <label for="via_date">Date of VIA test</label>
          <input id="via_date" name="via_date" type="date" required>
        </div>
        <div class="field">
          <label><input type="checkbox" name="has_cancer" value="1"> Patient has cancer — send referral to Nyeri County Referral Hospital</label>
        </div>
        <div class="field">
          <label for="treatment_date">Treatment date (optional)</label>
          <input id="treatment_date" name="treatment_date" type="date">
        </div>
        <button class="btn" type="submit">Save VIA result &amp; notify</button>
      </form>
    <?php endif; ?>
  </div>
  <?php endif; ?>

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

  <?php
    $pendingAttendanceAppt = null;
    foreach ($appointments as $a) {
        if (appointment_needs_attendance_check($a)) {
            $pendingAttendanceAppt = $a;
            break;
        }
    }
    $completedForVia = null;
  if (patient_screening_ready() && $pendingAttendanceAppt === null) {
      foreach ($appointments as $a) {
          if (strtolower((string) $a['status']) === 'completed') {
              $vr = strtolower((string) ($patient['via_result'] ?? 'not_done'));
              if (
                  !in_array($vr, ['negative', 'positive'], true)
                  && appointment_is_patients_first((int) $a['id'], $id)
              ) {
                  $completedForVia = $a;
              }
              break;
          }
      }
  }
  ?>
  <?php
    $viaAlreadyRecorded = in_array(
        strtolower((string) ($patient['via_result'] ?? '')),
        ['negative', 'positive'],
        true
    );
  ?>
  <?php if ($pendingAttendanceAppt !== null && !$viaAlreadyRecorded): ?>
  <div class="card" style="border-left:4px solid var(--accent);">
    <h2>Clinic visit — confirm attendance</h2>
    <p class="field-hint">Appointment: <?= h($pendingAttendanceAppt['scheduled_start']) ?>. Did the patient attend?</p>
    <form method="post" style="display:inline" action="patient_view.php?id=<?= $id ?>">
      <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
      <input type="hidden" name="action" value="mark_attended">
      <input type="hidden" name="appointment_id" value="<?= (int) $pendingAttendanceAppt['id'] ?>">
      <button class="btn" type="submit">Patient attended</button>
    </form>
    <form method="post" style="display:inline;margin-left:8px" action="patient_view.php?id=<?= $id ?>"
          onsubmit="return confirm('Mark as missed and notify the patient?');">
      <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
      <input type="hidden" name="action" value="mark_missed">
      <input type="hidden" name="appointment_id" value="<?= (int) $pendingAttendanceAppt['id'] ?>">
      <button class="btn" type="submit" style="background:#b42318">Did not attend</button>
    </form>
  </div>
  <?php elseif ($completedForVia !== null && patient_has_confirmed_appointment($id, $appointments)): ?>
  <div class="card" style="border-left:4px solid #6f42c1;">
    <h2>Record VIA from clinic visit</h2>
    <p class="field-hint">Patient attended on <?= h($completedForVia['scheduled_start']) ?>. Record VIA result in the VIA section below.</p>
  </div>
  <?php endif; ?>

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
              <?php elseif (appointment_needs_attendance_check($a)): ?>
                <form method="post" action="patient_view.php?id=<?= $id ?>" style="display:inline">
                  <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
                  <input type="hidden" name="action" value="mark_attended">
                  <input type="hidden" name="appointment_id" value="<?= (int) $a['id'] ?>">
                  <button class="btn" type="submit" style="padding:0.35rem 0.65rem;font-size:0.85rem">Attended</button>
                </form>
                <form method="post" action="patient_view.php?id=<?= $id ?>" style="display:inline;margin-left:4px"
                      onsubmit="return confirm('Mark as missed and notify the patient?');">
                  <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
                  <input type="hidden" name="action" value="mark_missed">
                  <input type="hidden" name="appointment_id" value="<?= (int) $a['id'] ?>">
                  <button class="btn" type="submit" style="padding:0.35rem 0.65rem;font-size:0.85rem;background:#b42318">Missed</button>
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
<?php if (!empty($_GET['book_appt'])): ?>
<script>
document.getElementById('add-appointment')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
document.getElementById('scheduled_start')?.focus();
</script>
<?php endif; ?>
<?php
layout_footer();
