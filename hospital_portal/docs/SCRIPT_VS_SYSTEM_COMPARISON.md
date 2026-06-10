# Afya Rafiki — Official script vs system implementation

**Purpose:** Side-by-side comparison of the study script (`WHATSAPP_MESSAGE_TEMPLATES.md`, `AFYA_RAFIKI_RAG_KNOWLEDGE_BASE.md`) and live medicback behaviour.  
**Code:** `messaging.php`, `hpv_results.php`, `patient_screening.php`, `appointment_utils.php`, `mteja_whatsapp.php`  
**Last reviewed:** June 2026  

---

## 1. Master flow

| Order | Official script | System today | Status |
|-------|-----------------|--------------|--------|
| 1 | Register → consent thank-you + registration welcome | `send_afya_enrollment_messages()` — same two messages | ✅ Aligned |
| 1 | No VIA at registration | API forces `via_result = not_done` | ✅ |
| 1 | No SMS opt-in YES/NO | Paper consent only | ✅ |
| 2 | Book appointment → confirmation SMS | `send_appointment_notification()` on add/update | ✅ |
| 2 | HPV record → confirm → notify | `set_patient_hpv_result` / `confirm_patient_hpv_result` | ✅ |
| 2 | HPV+ needs appointment before confirm | Gated in `confirm_patient_hpv_result` | ✅ |
| 3 | After appt day: attended / missed | `mark_appointment_attended` / `mark_appointment_missed` | ✅ |
| 3 | VIA only on **first** attended visit | `getVisitWorkflowState` / `patient_has_confirmed_appointment` | ✅ |
| 4 | Record VIA → immediate result SMS | `record_patient_via_result` → `process_via_recorded_messages` | ✅ |
| 5 | Continued care, FAQ, DOCTOR | `whatsapp_inbound.php`, cron reminders | ⚠️ Partial (see §6) |

---

## 2. Registration (Step 1)

| Item | Script | System | WhatsApp template | Match |
|------|--------|--------|-------------------|-------|
| **When** | On save, if opted in | `api/patients.php` → `send_afya_enrollment_messages` | — | ✅ |
| **Msg 1 — Consent thank-you** | §25 `afya_consent_thanks` | `build_consent_thank_you_message` → type `system` | `afya_consent_thanks` (body match) | ✅ SMS / ✅ WA |
| **Msg 2 — Registration welcome** | Confidential HPV follow-up welcome (not language menu) | `build_registration_welcome_message` → type `registration_welcome` | **Maps to `afya_welcome`** (language 1/2/3) | ❌ Wrong WA template |
| **Language intro** | §1 `afya_welcome` — after HPV+ phone counselling | Only if `!patient_has_confirmed_consent` on HPV+ confirm | `afya_welcome` | ⚠️ Skipped when paper consent recorded at registration |

**Exact registration welcome (system = script):**

- **EN:** *Hello. Welcome to Afya rafiki. We are here to support you after your HPV screening results… Your information will remain confidential.*
- **SW:** *Karibu kwenye Afya rafiki… Taarifa zako zitahifadhiwa kwa siri.*

---

## 3. Appointments (Step 2)

| Item | Script | System | Template | Match |
|------|--------|--------|----------|-------|
| **When — booked** | Staff saves appointment | `api/appointments.php` `action=add` | `afya_appt_booked` | ✅ |
| **When — updated** | Date/time changed | `action=update` / patient_view | `afya_appt_updated` | ✅ |
| **Reminder 7d** | Exact day −7 | Cron `reminders.php` `INTERVAL 7 DAY` | `afya_appt_reminder_7d` | ✅ |
| **Reminder 3d** | Exact day −3 | Cron | `afya_appt_reminder_3d` | ✅ |
| **Reminder 1d** | Evening before (20:00) | Cron `night` slot | `afya_appt_reminder_1d` | ✅ |
| **Missed visit** | Staff marks missed | `mark_appointment_missed` | `afya_missed_appt` | ✅ |
| **Post-attendance thank-you** | §18 `afya_post_visit` | Builder exists; **not sent** on attended | — | ❌ Not wired |
| **Missed survey reply → reschedule** | §17 `afya_missed_reschedule` | Builder exists; **no inbound handler** | — | ❌ Not wired |

---

## 4. HPV lab results (Step 2)

### 4.1 Workflow

| Step | Script | System |
|------|--------|--------|
| Record result | No patient message | `set_patient_hpv_result` — no send | ✅ |
| Confirm & notify | Sends result | `confirm_patient_hpv_result` | ✅ |
| HPV+ gate | Appointment required | Blocks if no proposed/confirmed appt | ✅ |

### 4.2 HPV negative (HIV-stratified)

| HIV | Script template | System builder | When | Match |
|-----|-----------------|----------------|------|-------|
| Positive | `afya_hpv_neg_hivpos` — **3 years** | `build_hpv_negative_result_notification(..., 'positive')` | On confirm | ✅ |
| Negative | `afya_hpv_neg_hivneg` — **5 years** | `build_hpv_negative_result_notification(..., 'negative')` | On confirm | ✅ |
| not_known | Script silent | Treated as **negative** path (5y) in `afya_patient_hiv_status` | On confirm | ⚠️ Clinical default |

### 4.3 HPV positive

| Item | Script | System | Match |
|------|--------|--------|-------|
| **When** | Staff confirm | `confirm_patient_hpv_result` | ✅ |
| **Immediate SMS** | Result + appointment date | `build_hpv_positive_result_notification` | ✅ |
| **Template** | `afya_hpv_positive` | Mteja maps from body | ✅ |
| **Counseling drip** | **16** messages, gentle schedule | **15** messages in `afya_counseling_positive.php`; drip from index 0 | ⚠️ Missing step 16 |
| **Drip timing** | 3h, 5h, then 1 day between | `hpv_delay_before_counseling_index`: +3h, +5h, +1 day | ✅ |
| **Drip message type** | Counseling steps 1–16 | `education_menu` | ⚠️ WA maps to `afya_help_menu` |
| **Language intro** | Optional activation | `welcome` + `build_language_introduction_message` if no prior consent | ⚠️ Usually skipped |

**Counseling index → template (script):**

| Index | Template | Topic | Also sent at VIA? |
|-------|----------|-------|-------------------|
| 0 | `counsel_pos_01` | HPV common, follow-up | Drip only |
| 1–5 | `02`–`06` | Fear, support, persistence | Drip only |
| 6 | `07` | What is VIA | Drip only |
| 7 | `08` | VIA results explained | Drip only |
| 8 | `09` | HPV+ / VIA **negative** | Drip **and** VIA neg record |
| 9 | `10` | HPV+ / VIA **positive** | Drip **and** VIA pos record |
| 10–14 | `11`–`15` | Thermal Ablation, after-care, TOC | Drip only (not treatment-triggered) |
| 15 | `16` | Referral / suspicious prep | **Not in PHP array** |

**Risk:** Drip may deliver steps 8–10 **before** the patient has VIA, then VIA record sends step 8 or 9 **again**.

---

## 5. VIA results (Step 4)

### 5.1 When sent

| Gate | Script | System |
|------|--------|--------|
| Appointment confirmed | Before VIA UI | `patient_has_confirmed_appointment` | ✅ |
| Patient attended | Implicit before test | Staff workflow (not auto-blocked in API) | ⚠️ Honour system |
| Opted in | Yes | `contact_channels.opted_in = 1` | ✅ |

### 5.2 Immediate messages on VIA save

| Outcome | Script (§42–43) | System function | Message type | WA template | Match |
|---------|-------------------|-----------------|--------------|-------------|-------|
| **Negative** | Step 9 — HIV **3y / 5y** repeat HPV | `build_via_negative_result_notification($name, $hivStatus)` | `via_negative` | `afya_counsel_pos_09` | ✅ SMS personalised; WA uses combined HIV text |
| **Positive** | Step 10 — Thermal Ablation pathway | `build_via_positive_result_notification` | `via_positive` | `afya_counsel_pos_10` | ✅ |
| **Positive + cancer** | `afya_via_referral` | `build_referral_initial_message` | `referral` | `afya_via_referral` | ✅ |

**VIA negative SMS (HIV+ example):** includes *Please repeat HPV screening after 3 years* — not the dual HIV sentence.

### 5.3 Scheduled follow-up after VIA

| Script (templates doc §21–23) | RAG doc (outdated) | **System today** | Match |
|--------------------------------|--------------------|------------------|-------|
| §21: 1-year `afya_checkup_via_neg` | §5.4: annual +1y | **Not used** | ❌ Doc stale |
| §22–23: HIV profile 5y/3y from registration | §5.4 | **Replaced** by VIA-date anchor | ⚠️ |
| Step 9: HIV+ **3y**, HIV− **5y** from VIA date | — | `via_neg_hiv_pos_3y` / `via_neg_hiv_neg_5y` | ✅ Script-aligned |

| HIV status | Scheduled | Reason key | Years from VIA date |
|------------|-----------|------------|------------------------|
| Positive | HPV re-screen reminder | `via_neg_hiv_pos_3y` | +3 |
| Negative / not_known | HPV re-screen reminder | `via_neg_hiv_neg_5y` | +5 |

**Check-up SMS:** `build_checkup_reminder_message` — no Mteja mapping → **`afya_fallback`** on WhatsApp.

### 5.4 VIA positive after-care (steps 11–15)

| Script | System |
|--------|--------|
| After Thermal Ablation: after-care, urgent signs, healing, Test of Cure +1y | Only via counseling **drip** if index reaches 10–14; **not** triggered by `treatment_date` on VIA record |
| `build_post_visit_via_positive_ablation` etc. | **Defined, never called** |

---

## 6. Referral pathways

| Path | Script | System | Template |
|------|--------|--------|----------|
| VIA+ cancer flag at VIA save | `afya_via_referral` | `process_via_recorded_messages` | ✅ |
| Manual Nyeri referral (HPV+VIA complete) | `afya_via_referral` + reassurance | `patient_referral.php` — referral + scheduled reassurance | ⚠️ Reassurance has **no template** |
| Referral appt reminder | Implied | `build_referral_appointment_reminder` — **not scheduled** | ❌ |

---

## 7. Inbound / support messages

| Trigger | Script | System | Template |
|---------|--------|--------|----------|
| HELP | `afya_help_menu` | `afya_faq_reply` → menu or FAQ | ✅ / FAQ 28–35 |
| DOCTOR | `afya_doctor_reason_ask` | `doctor_call_requests.php` | ✅ |
| Reason received | `afya_doctor_reason_ack` | `complete_doctor_call_with_patient_reason` | ✅ |
| Escalation | `afya_escalation` | `build_escalation_reply` | ✅ |
| AI reply | `afya_ai_reply` | OpenAI / FAQ | ✅ |
| Fallback | `afya_fallback` | `ai_fallback_reply` | ✅ |
| Unlinked number | `afya_unlinked` | `whatsapp_inbound_send_unlinked` | ✅ |
| Reply 1/2 language | Part of `afya_welcome` flow | Hardcoded English/Swahili ack | ❌ No template |
| Staff custom | `afya_staff_message` | Message center | ✅ |
| Engagement tip | `afya_engagement_tip` | Cron / legacy consent | ✅ |

---

## 8. Mteja template mapping summary

| Message type | Mapped template | Correct? |
|--------------|-----------------|----------|
| `system` (consent) | `afya_consent_thanks` | ✅ |
| `system` (HPV neg/pos) | `afya_hpv_neg_*` / `afya_hpv_positive` | ✅ |
| `registration_welcome` | `afya_welcome` | ❌ Should be `afya_registration_welcome` |
| `welcome` | `afya_welcome` | ✅ |
| `appointment_booked` | `afya_appt_booked` | ✅ |
| `appointment_reminder` | 7d / 3d / 1d | ✅ |
| `via_negative` / `via_positive` | `counsel_pos_09` / `10` | ✅ |
| `referral` | `afya_via_referral` | ✅ |
| `education_menu` (counseling drip) | `afya_help_menu` | ❌ |
| `checkup_reminder` | `afya_fallback` | ❌ |
| `escalation_notice` (missed) | `afya_missed_appt` | ✅ |

See **`newtemplates.md`** for templates to submit and code fixes.

---

## 9. Documentation drift (fix when editing docs)

| Document | Says | Should say |
|----------|------|------------|
| `AFYA_RAFIKI_RAG_KNOWLEDGE_BASE.md` §5.3–5.4 | VIA neg → 1 year; `afya_checkup_via_neg` | HIV 3y/5y from VIA date; `counsel_pos_09` immediate |
| `AFYA_RAFIKI_MESSAGES_AND_FLOW.md` §VIA | Annual check-up +1y | HIV-stratified HPV re-screen +3y/+5y |
| `WHATSAPP_MESSAGE_TEMPLATES.md` §21 | 1-year check-up still listed | Mark deprecated or add HIV VIA-neg variants |
| `build_post_visit_via_negative` | 1-year repeat | Legacy builder; live path uses step 9 + 3y/5y schedule |

---

## 10. Quick reference — when each message fires

```
REGISTRATION (opt-in)
  → consent thank-you          [immediate]
  → registration welcome       [immediate]

BOOK APPOINTMENT
  → appt booked/updated        [immediate]

HPV RECORD (staff)
  → (silence)

HPV CONFIRM (staff)
  → HPV negative               [immediate, HIV 3y vs 5y]
  → HPV positive               [immediate + appt date]
  → language intro (optional)  [immediate if no prior consent]
  → counseling drip 01…15      [+3h, +5h, +1d each]

CRON
  → appt reminders 7d/3d/1d
  → scheduled checkup_reminder [VIA date +3y or +5y]
  → engagement tip (optional)

APPOINTMENT DAY (staff)
  → missed appt survey         [on mark_missed]
  → (no message on attended)

VIA RECORD (staff, after test)
  → VIA negative (HIV-aware)   [immediate]
  → VIA positive               [immediate]
  → VIA+ cancer referral       [immediate]
  → schedule checkup_reminder  [future]

MANUAL NYERI REFERRAL (staff)
  → referral SMS               [immediate]
  → referral reassurance       [+2 minutes]

INBOUND
  → HELP / FAQ / DOCTOR / AI
  → missed reschedule          [NOT WIRED]
  → post-visit thank-you       [NOT WIRED]
```

---

*For new Mteja submissions see `newtemplates.md`. For exact wording see `WHATSAPP_MESSAGE_TEMPLATES.md` and `afya_rafiki_content.php`.*
