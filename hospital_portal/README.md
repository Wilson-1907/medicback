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
   - `OPENAI_API_KEY`
   - `OPENAI_MODEL` (`gpt-4o-mini` default)

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

- Incoming messages:
  - `/webhook_africastalking.php`
- Delivery reports:
  - `/webhook_delivery_report.php`

Supported inbound patient keywords:

- `HELP` / `MENU` / `0` -> education menu
- `1` -> PHV warning signs
- `2` -> prevention tips
- `DOCTOR` / `4` -> escalation to hospital team
- Messages containing `PHV` -> direct PHV explanation
- `HI` / `HELLO` -> guided PHV prompt

## 5.1) Reminder scheduler endpoint

Run this endpoint on a schedule (recommended every 30-60 minutes):

- `/cron_run_reminders.php`

In Render, create a Cron Job that calls this URL regularly.

## 6) AI behavior

When `OPENAI_API_KEY` is configured:

- Inbound messages are logged in `ai_turns`
- AI sends caring, hopeful, safety-guarded responses
- Critical wording is still directed toward urgent care and doctor escalation

When OpenAI key is empty, fallback supportive replies are used.

## 7) Deployment split

- Render backend config: `../deploy/render/`
- Vercel frontend config: `../deploy/vercel/`

Use Render URL for all Africa's Talking callbacks (do not point callbacks to Vercel static frontend).
