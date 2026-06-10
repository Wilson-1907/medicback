# PHV Hospital Portal (PHP + MySQL)

Backend and staff UI for the PHV engagement pilot.

## 1) Environment setup

1. Copy `.env.example` to `.env` (already available in this repo).
2. Set database values.
3. Choose Africa's Talking mode:
   - `AFRICASTALKING_MODE=sandbox` for test
   - `AFRICASTALKING_MODE=production` for live
4. Fill the matching env group:
   - Sandbox: `AFRICASTALKING_SANDBOX_*`
   - Production: `AFRICASTALKING_PROD_*`
5. Set OpenAI:
   - `GROQ_API_KEY` — from [console.groq.com](https://console.groq.com)
   - `GROQ_MODEL` (`llama-3.3-70b-versatile` default; fast alternative: `llama-3.1-8b-instant`)

## 2) Database

1. Import `phv_pilot_schema.sql`.
2. Run migration:
   - `sql/2026_04_17_enforce_appointment_reason.sql`
   - `sql/2026_04_17_add_appointment_reminder_columns.sql`

## 3) Run locally

- XAMPP Apache (place project in `htdocs`) or
- PHP built-in server:
  - `php -S 127.0.0.1:8000 -t hospital_portal`

## 4) Core workflows

- Patient registration and contact preference capture
- Automated welcome messaging
- Appointment add/reschedule with required reason
- Automatic patient notification on add/change
- Diagnosis/result logging
- Message center (`message_center.php`) for outbound/inbound/escalation tracking
- Appointment reminders are scheduled for 7 days, 3 days, and the previous night

## 4.1) API endpoints for external frontend

- `GET /api/dashboard.php`
- `GET /api/patients.php?q=`
- `POST /api/patients.php`
- `POST /api/appointments.php` (`action=add|reschedule`)
- `GET /api/message_center.php`

## 5) Webhooks

- WhatsApp (Mteja / Meta Cloud): `/webhook_whatsapp.php` — see `docs/MTEJA_WHATSAPP_GO_LIVE.md`
- SMS / Africa's Talking: `/webhook_africastalking.php`
- Delivery reports: `/webhook_delivery_report.php`

**Afya Rafiki** patient SMS/WhatsApp flow (official script):

1. **Registration** — paper consent; one welcome WhatsApp/SMS (language intro) when opted in; HPV result sent after staff confirm
2. Staff records **HPV positive or negative**, then **Confirm & notify**
3. **HPV negative** — one SMS (3-year return if HIV+, 5-year if HIV−)
4. **HPV positive** — welcome + positive result SMS with appointment date, then **16** counseling messages on a drip schedule (**3 hours**, **5 hours**, then **1 day** between each)

Run migration: `sql/2026_05_31_hpv_result_workflow.sql`

4. HPV counseling sequence (after result confirmed)
5. Appointment reminders (**7-day**, **3-day**, and **night-before**) via `/cron_run_reminders.php`
6. FAQ menu via `HELP` (`1`–`3` HPV questions, `4` appointments, `5`/`DOCTOR` provider)
7. Auto-escalation for urgent symptoms, distress, missed visits, complex clinical questions
8. Groq AI for open conversation (matches Swahili, English, Sheng, mixed)

Env: `AFYA_RAFIKI_NAME`, `CLINIC_SITE_NAME` (default: Nyeri Town Health Center)

## 5.1) Reminder scheduler endpoint

Run every **30–60 minutes** (see `deploy/render/CRON_REMINDERS.md`):

- `GET /cron_run_reminders.php?key=<CRON_SECRET>`

Sends Afya Rafiki messages at **7 days**, **3 days**, and **night before** (8 PM) each appointment.
Only patients who confirmed consent (`YES` / `NDIO`) receive reminders.

## 6) AI behavior

When `GROQ_API_KEY` is configured:

- Inbound messages are logged in `ai_turns`
- AI sends caring, hopeful, safety-guarded responses
- Critical wording is still directed toward urgent care and doctor escalation

When `GROQ_API_KEY` is empty, fallback supportive replies are used.

## 7) Deployment split

- Render backend config: `../deploy/render/`
- Vercel frontend config: `../deploy/vercel/`

Use Render URL for all Africa's Talking callbacks (do not point callbacks to Vercel static frontend).
