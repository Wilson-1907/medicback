# Afya Rafiki — RAG Knowledge Base

**Purpose:** Retrieval document for AI assistants, Mteja chatbots, and staff support tools.  
**Programme:** Nyeri Town Health Center — HPV & cervical health follow-up  
**Service name:** Afya Rafiki  
**Facility:** Nyeri Town Health Center (NTHC)  
**Referral hospital:** Nyeri County Referral Hospital  
**Channels:** SMS (Africa's Talking) and WhatsApp (Mteja)  
**Languages:** English (`en`) and Kiswahili (`sw`)  
**Backend:** `https://medicback.onrender.com`  
**Last updated:** June 2026  

---

## 1. Complete patient message flow (official order)

This is the **correct clinical and messaging sequence**. Each step happens in order; later steps do not happen until the earlier ones are complete.

```
STEP 1 — REGISTRATION (hospital console)
    Patient signs paper consent at the desk.
    Nurse registers: name, DOB, phone, language, client ID, HIV status,
    HPV history, residence. VIA is NOT recorded here.
         ↓
    Message 1: Thank you for agreeing to receive messages (consent thank-you)
    Message 2: Welcome to Afya Rafiki (confidentiality, HPV follow-up support)
         ↓
STEP 2 — APPOINTMENTS & HPV LAB RESULTS
    Staff book clinic appointments → patient gets appointment confirmation automatically.
    Lab reviews HPV sample → staff Record result → (if positive: book appt first) → Confirm & notify.
         ↓
    HPV negative → one result SMS (3-year return if HIV+, 5-year if HIV−)
    HPV positive → result SMS with appointment date + 16 gentle counseling messages over time
    Appointment reminders: 7 days, 3 days, 1 day before each visit
         ↓
STEP 3 — APPOINTMENT DAY PASSES → NURSE CONFIRMS ATTENDANCE
    On the patient page, after the appointment date/time, nurse must confirm:
         ↓
    PATIENT ATTENDED → mark "Patient attended" → status completed
         → Nurse records VIA result on same patient page (Step 4)
         ↓
    PATIENT DID NOT ATTEND → mark "Did not attend" → status no_show
         → Missed-appointment SMS sent automatically (reply 1–7 with reason)
         → Patient can reschedule via clinic or reply to message
         ↓
STEP 4 — VIA TEST & NURSE RECORDS RESULT (only if patient attended)
    Nurse uploads: positive or negative + date of test.
    System sends result message to patient immediately (if opted in).
         ↓
    VIA NEGATIVE → negative result message + annual check-up reminder scheduled (+1 year)
    VIA POSITIVE (standard) → positive result message (Thermal Ablation pathway explained)
    VIA POSITIVE + cancer/suspicious → specialist referral SMS to Nyeri County Referral Hospital
         ↓
STEP 5 — FLOW CONTINUES
    VIA negative path: routine follow-up, 1-year HPV/VIA check-up reminders
    VIA positive path: treatment (e.g. Thermal Ablation), after-care messages, Test of Cure, healing advice
    Referral path: specialist appointment at Nyeri County Referral Hospital
    Patient replies → FAQ, AI help, DOCTOR escalation
```

**Key rule:** VIA is always recorded **after the test**, never at registration.

---

## 2. What Afya Rafiki is

Afya Rafiki is a confidential follow-up messaging service for women after HPV screening at Nyeri Town Health Center.

**Afya Rafiki does:** enrollment messages, HPV result notifications, appointment confirmations and reminders, VIA result notifications after the test, referral messages, scheduled check-up reminders, FAQ and AI replies, staff escalation.

**Afya Rafiki does not:** diagnose, prescribe, replace clinic visits, or ask SMS opt-in (paper consent at registration).

**Tone:** Warm, respectful, simple, hopeful.

**Client ID:** `NC/NTHC/001/` + lab register digits (e.g. `NC/NTHC/001/022`).

**WhatsApp:** `+254142830423`

---

## 3. Step 1 — Registration messages

### 3.1 Fields at registration

| Field | Required | Notes |
|-------|----------|-------|
| Full name, DOB, phone (+254), language, client ID | Yes | |
| Contact channel (SMS/WhatsApp), paper consent | Yes | |
| HIV status, HPV done before, prior HPV result, residence | Yes | |
| VIA result | **No** | Stored as `not_done` until Step 4 |

### 3.2 Messages sent (opted-in patients only)

| Order | Type | Content summary |
|-------|------|-----------------|
| 1 | Consent thank-you | Thanks patient for agreeing to receive messages |
| 2 | Registration welcome | Welcome to Afya Rafiki; confidential HPV follow-up support |

**Not at registration:** HPV results, VIA results, appointment details, language 1/2/3 menu.

### 3.3 API

`POST /api/patients.php` — VIA fields in body are ignored; server forces `via_result = not_done`.

---

## 4. Step 2 — Appointments and HPV results

### 4.1 Appointments

When staff save an appointment (`POST /api/appointments.php`, `action=add`):
- **Appointment booked** confirmation is sent **automatically** to the patient.
- Reminders follow at 7 days, 3 days, and 1 day before.

Staff can book from the patient page inline form or the Appointments tab.

### 4.2 After the appointment — nurse confirms attendance

When the appointment date/time has passed, the patient record shows **Patient attended** / **Did not attend** buttons.

| Nurse action | System | Patient message |
|--------------|--------|-----------------|
| **Patient attended** (first appointment) | Status → `completed` | None (next: record VIA) |
| **Patient attended** (2nd+ appointment) | Status → `completed` | None — VIA is only done once, at the first visit |
| **Did not attend** | Status → `no_show` | Missed-appointment SMS (`afya_missed_appt`) — asks reason (1–7) |

API: `POST /api/appointments.php` with `action=mark_attended` or `action=mark_missed` and `appointment_id`.

**If attended (first appointment):** nurse scrolls to **VIA result** card and records positive/negative after the test.

**If attended (follow-up appointment):** attendance only — no VIA step.

**If missed:** patient receives the missed message; staff may book a new appointment when patient is ready to return.

### 4.3 HPV lab workflow

1. **Record** positive or negative (no message yet)
2. **Book appointment** — required before confirming HPV **positive**
3. **Confirm & notify** — sends result to patient

| Result | Message |
|--------|---------|
| HPV negative, HIV+ | Return in 3 years (`afya_hpv_neg_hivpos`) |
| HPV negative, HIV− | Return in 5 years (`afya_hpv_neg_hivneg`) |
| HPV positive | Result + appointment date + 16 counseling messages (drip schedule) |

### 4.4 API

`POST /api/hpv_result.php` — `action=set_result` | `confirm_result`  
`POST /api/appointments.php` — `action=mark_attended` | `mark_missed`

---

## 5. Step 4 — VIA test and nurse upload (after attended visit)

### 5.1 What is VIA?

Visual Inspection with Acetic acid — a quick clinic exam where a provider inspects the cervix after applying acetic acid solution. Results are given at the visit.

### 5.2 When nurse records VIA

After the patient has been tested, nurse opens **patient record → VIA result card** and saves:
- Result: **positive** or **negative**
- **Date of VIA test** (required)
- If positive: **cancer/suspicious** checkbox → referral pathway
- Optional treatment date

### 5.3 Messages sent immediately on VIA save

| VIA outcome | Immediate message | WhatsApp template |
|-------------|-------------------|-------------------|
| **Negative** | HPV+ / VIA negative pathway — no treatment needed now; return in 1 year | `afya_counsel_pos_09` |
| **Positive** (standard) | HPV+ / VIA positive — changes seen; Thermal Ablation may be recommended | `afya_counsel_pos_10` |
| **Positive + cancer** | Referral to Nyeri County Referral Hospital | `afya_via_referral` |

### 5.4 Scheduled follow-up after VIA

| Condition | Scheduled reminder |
|-----------|-------------------|
| VIA negative | Annual check-up (+1 year from VIA date) — `afya_checkup_via_neg` |
| HIV+ / HPV− profile | 5-year HPV check-up |
| HIV+ / HPV+ profile | 3-year HPV check-up |

### 5.5 API

`POST /api/via_result.php` — `patient_id`, `via_result`, `via_date`, `has_cancer`, `treatment_date`

---

## 6. Step 5 — Continued care pathways

### VIA negative pathway
- Patient receives negative VIA result message
- No treatment at this time
- Return for follow-up / repeat HPV in ~1 year
- Continue routine check-ups as advised

### VIA positive pathway (no cancer flag)
- Patient receives positive VIA result message
- Healthcare provider may recommend **Thermal Ablation**
- Subsequent messages (when triggered by staff/clinical events): after-care, urgent return signs, healing advice, Test of Cure after 1 year
- Counseling steps 11–16 in official script cover Thermal Ablation and recovery

### VIA positive + suspicious/cancer pathway
- **Referral SMS** to Nyeri County Referral Hospital
- Specialist review and further tests — does not always mean cancer
- Referral appointment reminders when scheduled

### Ongoing patient support
- Appointment reminders for all booked visits
- FAQ (HELP menu) and AI replies on WhatsApp
- Reply **DOCTOR** for health worker callback
- Missed appointment triage (reply 1–7 with reason)

---

## 7. Message type catalog

| Type | Step | When |
|------|------|------|
| `system` | 1 | Consent thank-you |
| `registration_welcome` | 1 | Welcome message |
| `appointment_booked` | 2 | Appointment saved |
| `appointment_reminder` | 2 | 7d / 3d / 1d before visit |
| `hpv_negative` / HPV confirm | 2 | Staff confirms negative lab result |
| `hpv_positive` + `counseling` | 2 | Staff confirms positive lab result |
| `via_negative` | 4 | Nurse records VIA negative |
| `via_positive` | 4 | Nurse records VIA positive |
| `referral` | 4 | VIA positive + cancer flag |
| `checkup_reminder` | 4–5 | Scheduled future check-ups |
| `ai_reply` | 5 | Inbound AI response |
| `escalation_notice` | 5 | DOCTOR / triage |

Full template bodies: `docs/WHATSAPP_MESSAGE_TEMPLATES.md`

---

## 8. Staff console actions (quick reference)

| Task | Where | Result |
|------|-------|--------|
| Register patient | Register tab | Thank-you + welcome SMS |
| Book appointment | Patient page or Appointments tab | Confirmation SMS |
| Record HPV | Patient → HPV card | No message until confirm |
| Confirm HPV | Patient → HPV card | Result SMS + counseling if positive |
| Confirm attendance | Patient → Appointments (after date) | Attended → record VIA; Missed → missed SMS |
| Record VIA | Patient → VIA card (after attended visit) | VIA result SMS + schedules |
| View messages | Messages tab | Outbound log and escalations |

---

## 9. Common staff Q&A

**Q: What is the full message order for a new patient?**  
A: (1) Thank you for agreeing, (2) Welcome, then appointments/HPV results, then after the visit nurse confirms attendance — if missed, missed SMS; if attended, nurse records VIA and result message is sent.

**Q: When does the nurse confirm if the patient came?**  
A: After the appointment date/time passes. On the patient page, use **Patient attended** or **Did not attend**. Missed patients get an automatic SMS asking why they could not come.

**Q: When is VIA recorded?**  
A: Only after the patient has had the VIA test — on the patient page, not at registration.

**Q: What happens when nurse saves VIA negative?**  
A: Patient gets the VIA negative result message immediately; annual check-up reminder is scheduled.

**Q: What happens when nurse saves VIA positive?**  
A: Patient gets the VIA positive result message (Thermal Ablation pathway). If cancer/suspicious is checked, referral SMS to Nyeri County Referral Hospital instead.

**Q: Does booking an appointment message the patient?**  
A: Yes — automatically on save.

**Q: Does the patient reply YES to opt in?**  
A: No — paper consent at registration.

---

## 10. Common patient Q&A

**Q: What is Afya Rafiki?**  
A: Confidential follow-up from Nyeri Town Health Center after HPV screening. Reminders and health information. Reply DOCTOR for a health worker.

**Q: I got two messages when I registered — why?**  
A: First thanks you for agreeing to messages; second welcomes you to Afya Rafiki and explains confidential follow-up support.

**Q: When will I get my VIA result by message?**  
A: After you attend your clinic appointment and have the VIA test, when staff record your result in the system.

**Q: I missed my appointment — what happens?**  
A: The clinic will send you a message asking why you could not attend. Reply with the number 1–7 that best describes your situation, or contact the clinic to reschedule.

**Q: My VIA was positive — do I have cancer?**  
A: Not necessarily. Positive VIA means changes were seen that may need treatment. Your provider will explain next steps, which may include Thermal Ablation or referral for more tests.

**Q: What is Thermal Ablation?**  
A: A simple outpatient treatment using heat to remove abnormal cervical cells and help prevent cancer.

---

## 11. API quick reference

| Endpoint | Purpose |
|----------|---------|
| `POST /api/patients.php` | Register (Step 1) |
| `POST /api/appointments.php` | Book; `mark_attended` / `mark_missed` (Step 3) |
| `POST /api/hpv_result.php` | Record/confirm HPV (Step 2) |
| `POST /api/via_result.php` | Record VIA after test (Step 4) |
| `GET /api/message_center.php` | Message log |
| `/webhook_whatsapp.php` | Inbound WhatsApp |

---

## 12. RAG chunking guide

| Chunk | Topic |
|-------|-------|
| §1 | **Master flow** — registration → HPV/appts → VIA test → VIA upload → continued care |
| §3 | Registration fields and two enrollment messages |
| §4 | Appointments and HPV confirm |
| §5–6 | VIA record, immediate messages, pathways |
| §7 | Message type catalog |
| §9–10 | Staff and patient FAQ |

For exact message wording, retrieve `WHATSAPP_MESSAGE_TEMPLATES.md` or `afya_rafiki_content.php` / `afya_counseling_positive.php`.

---

*Implementation: `messaging.php`, `patient_screening.php`, `hpv_results.php`, `api/via_result.php`, `deploy/vercel/app.js`. Nyeri Town Health Center / Afya Rafiki, June 2026.*
