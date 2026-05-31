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
        $errors[] = 'Invalid session. Please refresh and try again.';
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
            $errors[] = 'Please enter a valid phone number with country code (e.g., +254712345678).';
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
                    try {
                        send_patient_message($pid, 'welcome', build_welcome_message($name, $lang));
                        send_patient_message($pid, 'education_menu', build_engagement_menu_message($lang));
                    } catch (Throwable $msgErr) {
                        error_log('Warning: Failed to send welcome messages: ' . $msgErr->getMessage());
                        // Don't fail registration if messaging fails
                    }
                }
                header('Location: patient_view.php?id=' . $pid . '&saved=1');
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Patient registration error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
                if (str_contains($e->getMessage(), 'Duplicate')) {
                    $errors[] = 'This phone number is already registered. Please use a different number or contact the patient if they already exist.';
                } else {
                    $errors[] = 'Registration failed. Please check your information and try again.';
                }
            }
        }
    }
}

$csrf = csrf_token();
layout_header('Register patient');
?>
<div class="card" style="max-width:640px">
  <h1>Register new patient</h1>
  <p style="color:var(--muted);margin-top:-0.5rem">Complete the form below to register a patient in the system.</p>

  <?php foreach ($errors as $e): ?>
    <div class="alert alert-error">
      <strong>⚠ Error:</strong> <?= h($e) ?>
    </div>
  <?php endforeach; ?>

  <form method="post" action="patient_new.php" id="patientForm">
    <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">

    <div class="field">
      <label for="full_name">Full name *</label>
      <input id="full_name" name="full_name" type="text" required value="<?= h($_POST['full_name'] ?? '') ?>" placeholder="e.g., Jane Doe">
    </div>

    <div class="row-inline">
      <div class="field">
        <label for="date_of_birth">Date of birth</label>
        <input id="date_of_birth" name="date_of_birth" type="date" value="<?= h($_POST['date_of_birth'] ?? '') ?>">
      </div>
      <div class="field">
        <label for="preferred_language">Preferred language *</label>
        <select id="preferred_language" name="preferred_language">
          <?php
            $cur = $_POST['preferred_language'] ?? 'en';
            foreach (['en' => 'English', 'sw' => 'Kiswahili', 'fr' => 'Français'] as $code => $label) {
                $sel = $cur === $code ? ' selected' : '';
                echo '<option value="' . h($code) . '"' . $sel . '>' . h($label) . '</option>';
            }
          ?>
        </select>
        <div class="field-hint">Messages will be sent in this language</div>
      </div>
    </div>

    <div class="field">
      <label for="external_mrn">Hospital MRN (optional)</label>
      <input id="external_mrn" name="external_mrn" type="text" value="<?= h($_POST['external_mrn'] ?? '') ?>" placeholder="Medical record number">
    </div>

    <div class="field">
      <label for="phone">Mobile phone number *</label>
      <input id="phone" name="phone" type="tel" required placeholder="+254712345678" value="<?= h($_POST['phone'] ?? '') ?>">
      <div class="field-hint">Include country code (e.g., +254 for Kenya). Used for SMS and WhatsApp messages.</div>
    </div>

    <div class="field">
      <label>Contact method *</label>
      <?php $ch = ($_POST['contact_channel'] ?? 'sms') === 'whatsapp' ? 'whatsapp' : 'sms'; ?>
      <select name="contact_channel">
        <option value="sms"<?= $ch === 'sms' ? ' selected' : '' ?>>SMS</option>
        <option value="whatsapp"<?= $ch === 'whatsapp' ? ' selected' : '' ?>>WhatsApp</option>
      </select>
    </div>

    <div class="field">
      <label>
        <input type="checkbox" name="opt_in" value="1" <?= !empty($_POST['opt_in']) ? ' checked' : '' ?>>
        <strong>Patient consents to receive messages</strong>
      </label>
      <div class="field-hint">Patient agrees to receive health education, appointment reminders, and updates via the selected channel in their preferred language.</div>
    </div>

    <div class="field">
      <label for="notes">Internal notes (optional)</label>
      <textarea id="notes" name="notes" placeholder="Add any additional information about the patient..."><?= h($_POST['notes'] ?? '') ?></textarea>
    </div>

    <div style="display: flex; gap: 0.75rem; margin-top: 2rem;">
      <button class="btn" type="submit" id="submitBtn">Save patient</button>
      <a class="btn btn-secondary" href="patients.php">Cancel</a>
    </div>
  </form>
</div>

<!-- Loading Modal -->
<div id="loadingOverlay" class="loading-overlay">
  <div class="loading-content">
    <div class="loading-spinner"></div>
    <p>Registering patient...</p>
  </div>
</div>

<style>
  .loading-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 9999;
    align-items: center;
    justify-content: center;
  }

  .loading-overlay.active {
    display: flex;
  }

  .loading-content {
    background: white;
    border-radius: 12px;
    padding: 2rem;
    text-align: center;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
  }

  .loading-content p {
    margin: 1rem 0 0 0;
    font-size: 1.05rem;
    color: #333;
    font-weight: 500;
  }

  .loading-spinner {
    width: 48px;
    height: 48px;
    border: 4px solid #f3f3f3;
    border-top: 4px solid #0d6efd;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto;
  }

  @keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
  }
</style>

<script>
  document.getElementById('patientForm').addEventListener('submit', function(e) {
    const overlay = document.getElementById('loadingOverlay');
    overlay.classList.add('active');
  });
</script>

<?php
layout_footer();
