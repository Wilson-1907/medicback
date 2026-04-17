<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/layout.php';
require_login();

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
$sql .= ' ORDER BY p.full_name ASC LIMIT 200';

$st = db()->prepare($sql);
$st->execute($args);
$rows = $st->fetchAll();
$removedMsg = trim((string) ($_GET['removed'] ?? ''));

layout_header('Patients');
?>
<div class="card">
  <h1>Patients</h1>
  <?php if ($removedMsg !== ''): ?>
    <div class="alert alert-success"><?= h($removedMsg) ?></div>
  <?php endif; ?>
  <form method="get" action="patients.php" class="row-inline" style="margin-bottom:1rem">
    <div class="field" style="flex:2">
      <label for="q">Search by name, MRN, or ID</label>
      <input id="q" name="q" type="text" value="<?= h($q) ?>" placeholder="e.g. Jane or 12">
    </div>
    <div class="field">
      <button class="btn" type="submit">Search</button>
    </div>
  </form>
  <div class="actions" style="margin-bottom:1rem">
    <a class="btn" href="patient_new.php">+ Register patient</a>
  </div>
  <?php if ($rows === []): ?>
    <p style="color:var(--muted)">No patients match your search.</p>
  <?php else: ?>
    <table class="data">
      <thead>
        <tr>
          <th>ID</th>
          <th>Name</th>
          <th>Channel</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= (int) $r['id'] ?></td>
            <td><?= h($r['full_name']) ?></td>
            <td><?= h($r['primary_channel'] ?? '—') ?></td>
            <td><?= h($r['status']) ?></td>
            <td><a href="patient_view.php?id=<?= (int) $r['id'] ?>">Open</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php
layout_footer();
