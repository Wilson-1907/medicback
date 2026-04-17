<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/messaging.php';
require_login();

$errors = [];
$success = '';

/** Normalize to E.164-ish digits after + */
function normalize_phone(string $raw): string
{
    $t = trim($raw);
    if ($t === '') {
        return '';
    }
    if ($t[0] === '+') {
        return '+' . preg_replace('/\D+/', '', substr($t, 1));
    }
    return '+' . preg_replace('/\D+/', '', $t);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        $errors[] = 'Invalid session. Refresh and try again.';
    } else {
        $name = trim((string) ($_POST['full_name'] ?? ''));
        $dob = trim((string) ($_POST['date_of_birth'] ?? ''));
        $lang = trim((string) ($_POST['preferred_language'] ?? 'en')) ?: 'en';
        $mrn = trim((string) ($_POST['external_mrn'] ?? ''));
        $notes = trim((string) ($_POST['notes'] ?? ''));
        $phone = normalize_phone((string) ($_POST['phone'] ?? ''));
        $channel = ($_POST['contact_channel'] ?? 'sms') === 'whatsapp' ? 'whatsapp' : 'sms';
        $optIn = isset($_POST['opt_in']);

        if ($name === '') {
            $errors[] = 'Full name is required.';
        }
        if ($phone === '' || strlen($phone) < 8) {
            $errors[] = 'Enter a valid phone number (include country code, e.g. +254712345678).';
        }

        if ($errors === []) {
            $dobVal = $dob === '' ? null : $dob;
            $mrnVal = $mrn === '' ? null : $mrn;
            $pdo = db();
            try {
                $pdo->beginTransaction();
                $st = $pdo->prepare(
                    'INSERT INTO patients (full_name, date_of_birth, preferred_language, external_mrn, notes, status)
                     VALUES (?,?,?,?,?,?)'
                );
                $st->execute([$name, $dobVal, $lang, $mrnVal, $notes === '' ? null : $notes, 'active']);
                $pid = (int) $pdo->lastInsertId();

                $opted = $optIn ? 1 : 0;
                $optAt = $optIn ? date('Y-m-d H:i:s') : null;
                $ch = $pdo->prepare(
                    'INSERT INTO contact_channels (patient_id, channel, address, is_primary, opted_in, opted_in_at)
                     VALUES (?,?,?,?,?,?)'
                );
                $ch->execute([$pid, $channel, $phone, 1, $opted, $optAt]);

                $ev = $pdo->prepare(
                    'INSERT INTO contact_preference_events (patient_id, channel, action, source)
                     VALUES (?,?,?,?)'
                );
                $ev->execute([$pid, $channel, $optIn ? 'opt_in' : 'opt_out', 'hospital_registration']);

                $pdo->commit();
                if ($optIn) {
                    send_patient_message($pid, 'welcome', build_welcome_message($name));
                }
                header('Location: patient_view.php?id=' . $pid . '&saved=1');
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if (str_contains($e->getMessage(), 'Duplicate')) {
                    $errors[] = 'That phone number is already registered for this channel. Use a different number or open the existing patient.';
                } else {
                    $errors[] = 'Could not save: ' . $e->getMessage();
                }
            }
        }
    }
}

$csrf = csrf_token();
layout_header('Register patient');
?>
<div class="card" style="max-width:640px">
  <h1>Register patient</h1>
  <p style="color:var(--muted);margin-top:-0.5rem">One short form: identity, contact, and messaging consent.</p>

  <?php foreach ($errors as $e): ?>
    <div class="alert alert-error"><?= h($e) ?></div>
  <?php endforeach; ?>

  <form method="post" action="patient_new.php">
    <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">

    <div class="field">
      <label for="full_name">Full name</label>
      <input id="full_name" name="full_name" type="text" required value="<?= h($_POST['full_name'] ?? '') ?>">
    </div>

    <div class="row-inline">
      <div class="field">
        <label for="date_of_birth">Date of birth</label>
        <input id="date_of_birth" name="date_of_birth" type="date" value="<?= h($_POST['date_of_birth'] ?? '') ?>">
      </div>
      <div class="field">
        <label for="preferred_language">Language</label>
        <select id="preferred_language" name="preferred_language">
          <?php
            $cur = $_POST['preferred_language'] ?? 'en';
            foreach (['en' => 'English', 'sw' => 'Kiswahili', 'fr' => 'Français'] as $code => $label) {
                $sel = $cur === $code ? ' selected' : '';
                echo '<option value="' . h($code) . '"' . $sel . '>' . h($label) . '</option>';
            }
          ?>
        </select>
      </div>
    </div>

    <div class="field">
      <label for="external_mrn">Hospital MRN (optional)</label>
      <input id="external_mrn" name="external_mrn" type="text" value="<?= h($_POST['external_mrn'] ?? '') ?>">
    </div>

    <div class="field">
      <label for="phone">Mobile for SMS / WhatsApp</label>
      <input id="phone" name="phone" type="tel" required placeholder="+254712345678" value="<?= h($_POST['phone'] ?? '') ?>">
      <div class="field-hint">Include country code. This is used for automated messages (when you connect Africa’s Talking).</div>
    </div>

    <div class="field">
      <label>Preferred channel</label>
      <?php $ch = ($_POST['contact_channel'] ?? 'sms') === 'whatsapp' ? 'whatsapp' : 'sms'; ?>
      <select name="contact_channel">
        <option value="sms"<?= $ch === 'sms' ? ' selected' : '' ?>>SMS</option>
        <option value="whatsapp"<?= $ch === 'whatsapp' ? ' selected' : '' ?>>WhatsApp</option>
      </select>
    </div>

    <div class="field">
      <label>
        <input type="checkbox" name="opt_in" value="1" <?= !empty($_POST['opt_in']) ? ' checked' : '' ?>>
        Patient consents to receive health education and appointment messages on this channel
      </label>
    </div>

    <div class="field">
      <label for="notes">Internal notes (optional)</label>
      <textarea id="notes" name="notes"><?= h($_POST['notes'] ?? '') ?></textarea>
    </div>

    <button class="btn" type="submit">Save patient</button>
    <a class="btn btn-secondary" href="patients.php" style="margin-left:0.5rem">Cancel</a>
  </form>
</div>
<?php
layout_footer();
