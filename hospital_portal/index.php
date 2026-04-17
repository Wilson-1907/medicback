<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/layout.php';
require_login();

$p = db();

$stats = [
    'patients' => (int) $p->query('SELECT COUNT(*) AS c FROM patients')->fetch()['c'],
    'appointments_upcoming' => (int) $p->query(
        "SELECT COUNT(*) AS c FROM appointments WHERE scheduled_start >= NOW() AND status IN ('proposed','confirmed')"
    )->fetch()['c'],
    'today' => (int) $p->query(
        "SELECT COUNT(*) AS c FROM appointments WHERE DATE(scheduled_start) = CURDATE() AND status IN ('proposed','confirmed')"
    )->fetch()['c'],
];

$recent = $p->query(
    'SELECT p.id, p.full_name, p.status, p.registration_at
     FROM patients p
     ORDER BY p.registration_at DESC
     LIMIT 8'
)->fetchAll();

layout_header('Dashboard');
?>
<div class="grid-2" style="margin-bottom:1.25rem">
  <div class="stat">
    <strong><?= (int) $stats['patients'] ?></strong>
    <span>Patients registered</span>
  </div>
  <div class="stat">
    <strong><?= (int) $stats['today'] ?></strong>
    <span>Appointments today</span>
  </div>
  <div class="stat">
    <strong><?= (int) $stats['appointments_upcoming'] ?></strong>
    <span>Upcoming (all future)</span>
  </div>
</div>

<div class="card">
  <h1>Quick actions</h1>
  <div class="actions">
    <a class="btn" href="patient_new.php">Register a new patient</a>
    <a class="btn btn-secondary" href="patients.php">View all patients</a>
  </div>
</div>

<div class="card">
  <h2>Recently registered</h2>
  <?php if ($recent === []): ?>
    <p style="color:var(--muted)">No patients yet. Use <strong>Register patient</strong> to add the first record.</p>
  <?php else: ?>
    <table class="data">
      <thead>
        <tr>
          <th>Name</th>
          <th>Status</th>
          <th>Registered</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recent as $r): ?>
          <tr>
            <td><?= h($r['full_name']) ?></td>
            <td><?= h($r['status']) ?></td>
            <td><?= h($r['registration_at']) ?></td>
            <td><a href="patient_view.php?id=<?= (int) $r['id'] ?>">Open</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php
layout_footer();
