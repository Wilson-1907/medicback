<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/messaging.php';
require_login();

$pdo = db();

$sendFlash = '';
$sendErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_custom') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        $sendErrors[] = 'Invalid session token. Please retry.';
    } else {
        $target = (string) ($_POST['target'] ?? 'one');
        $messageText = trim((string) ($_POST['message_text'] ?? ''));
        if ($messageText === '') {
            $sendErrors[] = 'Message text is required.';
        }

        if ($sendErrors === []) {
            if ($target === 'broadcast') {
                $recipients = $pdo->query(
                    "SELECT DISTINCT p.id
                     FROM patients p
                     INNER JOIN contact_channels c ON c.patient_id = p.id
                     WHERE p.status = 'active' AND c.opted_in = 1"
                )->fetchAll();
                $count = 0;
                foreach ($recipients as $r) {
                    send_patient_message((int) $r['id'], 'system', $messageText);
                    $count++;
                }
                $sendFlash = 'Custom message sent to ' . $count . ' patient(s).';
            } else {
                $targetId = (int) ($_POST['patient_id'] ?? 0);
                if ($targetId < 1) {
                    $sendErrors[] = 'Select a patient to message.';
                } else {
                    send_patient_message($targetId, 'system', $messageText);
                    $sendFlash = 'Custom message sent.';
                }
            }
        }
    }
}

$patientChoices = $pdo->query(
    "SELECT DISTINCT p.id, p.full_name
     FROM patients p
     INNER JOIN contact_channels c ON c.patient_id = p.id
     WHERE p.status = 'active' AND c.opted_in = 1
     ORDER BY p.full_name ASC"
)->fetchAll();

$stats = [
    'outbound_24h' => 0,
    'failed_24h' => 0,
    'inbound_24h' => 0,
    'open_escalations' => 0,
];

$stats['outbound_24h'] = (int) $pdo->query(
    "SELECT COUNT(*) AS c FROM outbound_messages WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)"
)->fetch()['c'];
$stats['failed_24h'] = (int) $pdo->query(
    "SELECT COUNT(*) AS c FROM outbound_messages WHERE status = 'failed' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)"
)->fetch()['c'];
$stats['inbound_24h'] = (int) $pdo->query(
    "SELECT COUNT(*) AS c FROM inbound_messages WHERE received_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)"
)->fetch()['c'];
$stats['open_escalations'] = (int) $pdo->query(
    "SELECT COUNT(*) AS c FROM escalations WHERE status IN ('open','triaged')"
)->fetch()['c'];

$outbound = $pdo->query(
    "SELECT o.*, p.full_name
     FROM outbound_messages o
     INNER JOIN patients p ON p.id = o.patient_id
     ORDER BY o.created_at DESC, o.id DESC
     LIMIT 100"
)->fetchAll();

$inbound = $pdo->query(
    "SELECT i.*, p.full_name
     FROM inbound_messages i
     LEFT JOIN patients p ON p.id = i.patient_id
     ORDER BY i.received_at DESC, i.id DESC
     LIMIT 100"
)->fetchAll();

$escalations = $pdo->query(
    "SELECT e.*, p.full_name
     FROM escalations e
     INNER JOIN patients p ON p.id = e.patient_id
     ORDER BY e.created_at DESC, e.id DESC
     LIMIT 80"
)->fetchAll();

layout_header('Message center');
?>

<div class="card">
  <h1>Message center</h1>
  <p style="color:var(--muted);margin-top:-0.5rem">Monitor SMS/WhatsApp delivery, inbound replies, and escalation requests.</p>
  <div class="actions">
    <a class="btn btn-secondary" href="message_center.php">Refresh</a>
  </div>
</div>

<div class="card">
  <h2>Send a custom message</h2>
  <p style="color:var(--muted);margin-top:-0.5rem">Send a specific message to one patient or broadcast to all active, opted-in patients.</p>
  <?php if ($sendFlash !== ''): ?>
    <div class="alert alert-success"><?= h($sendFlash) ?></div>
  <?php endif; ?>
  <?php if ($sendErrors !== []): ?>
    <div class="alert alert-error">
      <?php foreach ($sendErrors as $e): ?>
        <div><?= h($e) ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <form method="post" action="message_center.php">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="send_custom">
    <div class="field">
      <label>Recipients</label>
      <select name="target" id="customTarget">
        <option value="one">A specific patient</option>
        <option value="broadcast">All active, opted-in patients</option>
      </select>
    </div>
    <div class="field" id="patientPickerField">
      <label>Patient</label>
      <select name="patient_id">
        <option value="">Select patient...</option>
        <?php foreach ($patientChoices as $pc): ?>
          <option value="<?= (int) $pc['id'] ?>"><?= h($pc['full_name']) ?> (#<?= (int) $pc['id'] ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label>Message</label>
      <textarea name="message_text" rows="3" placeholder="Type the message to send..."></textarea>
    </div>
    <div class="actions">
      <button type="submit" class="btn">Send message</button>
    </div>
  </form>
  <script>
    (function () {
      var target = document.getElementById('customTarget');
      var pickerField = document.getElementById('patientPickerField');
      function sync() {
        pickerField.style.display = target.value === 'broadcast' ? 'none' : '';
      }
      target.addEventListener('change', sync);
      sync();
    })();
  </script>
</div>

<div class="grid-2" style="margin-bottom:1.25rem">
  <div class="stat">
    <strong><?= (int) $stats['outbound_24h'] ?></strong>
    <span>Outbound in last 24h</span>
  </div>
  <div class="stat">
    <strong><?= (int) $stats['failed_24h'] ?></strong>
    <span>Failed outbound in last 24h</span>
  </div>
  <div class="stat">
    <strong><?= (int) $stats['inbound_24h'] ?></strong>
    <span>Inbound in last 24h</span>
  </div>
  <div class="stat">
    <strong><?= (int) $stats['open_escalations'] ?></strong>
    <span>Open/triaged escalations</span>
  </div>
</div>

<div class="card">
  <h2>Outbound messages</h2>
  <?php if ($outbound === []): ?>
    <p style="color:var(--muted)">No outbound messages yet.</p>
  <?php else: ?>
    <table class="data">
      <thead>
        <tr>
          <th>Time</th>
          <th>Patient</th>
          <th>Channel</th>
          <th>Type</th>
          <th>Status</th>
          <th>Message</th>
          <th>Error</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($outbound as $m): ?>
          <tr>
            <td><?= h($m['created_at']) ?></td>
            <td><?= h($m['full_name']) ?></td>
            <td><?= h($m['channel']) ?></td>
            <td><?= h($m['message_type']) ?></td>
            <td><?= h($m['status']) ?></td>
            <td><?= nl2br(h((string) $m['body'])) ?></td>
            <td><?= h((string) ($m['error_detail'] ?? '')) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Inbound messages</h2>
  <?php if ($inbound === []): ?>
    <p style="color:var(--muted)">No inbound messages yet.</p>
  <?php else: ?>
    <table class="data">
      <thead>
        <tr>
          <th>Time</th>
          <th>Patient</th>
          <th>Channel</th>
          <th>From</th>
          <th>Message</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($inbound as $m): ?>
          <tr>
            <td><?= h($m['received_at']) ?></td>
            <td><?= h((string) ($m['full_name'] ?? 'Unknown')) ?></td>
            <td><?= h($m['channel']) ?></td>
            <td><?= h($m['from_address']) ?></td>
            <td><?= nl2br(h($m['body'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Escalations</h2>
  <?php if ($escalations === []): ?>
    <p style="color:var(--muted)">No escalations yet.</p>
  <?php else: ?>
    <table class="data">
      <thead>
        <tr>
          <th>Time</th>
          <th>Patient</th>
          <th>Status</th>
          <th>Urgency</th>
          <th>Reason</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($escalations as $e): ?>
          <tr>
            <td><?= h($e['created_at']) ?></td>
            <td><?= h($e['full_name']) ?></td>
            <td><?= h($e['status']) ?></td>
            <td><?= h($e['urgency']) ?></td>
            <td><?= nl2br(h($e['reason'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php
layout_footer();
