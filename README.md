# PHV Engagement System

Single-hospital PHV engagement platform with:

- Patient registration and contact preferences
- Appointment scheduling and rescheduling (with required reason)
- Diagnosis logging
- Africa's Talking SMS/WhatsApp automation
- OpenAI caring conversational replies
- Message center for outbound/inbound/escalation monitoring

## Project Structure

- `hospital_portal/` -> PHP backend + staff UI + webhook endpoints
- `deploy/render/` -> Render deployment config for backend
- `deploy/vercel/` -> Vercel frontend placeholder deployment

## Local Run (XAMPP/PHP)

1. Import DB schema:
   - `phv_pilot_schema.sql`
2. Run appointment reason migration:
   - `hospital_portal/sql/2026_04_17_enforce_appointment_reason.sql`
3. Configure env:
   - `hospital_portal/.env`
4. Start server:
   - `php -S 127.0.0.1:8000 -t hospital_portal`

## Environment Modes (Africa's Talking)

Use one switch in `.env`:

- `AFRICASTALKING_MODE=sandbox` for testing
- `AFRICASTALKING_MODE=production` for live traffic

Fill values accordingly:

- Sandbox fields: `AFRICASTALKING_SANDBOX_*`
- Production fields: `AFRICASTALKING_PROD_*`

For production go-live, keep sandbox values unused and fill only `AFRICASTALKING_PROD_*`.

## Webhook Endpoints

- Incoming: `/webhook_africastalking.php`
- Delivery report: `/webhook_delivery_report.php`

## OpenAI

Set:

- `OPENAI_API_KEY`
- `OPENAI_MODEL` (default `gpt-4o-mini`)

If key is empty, system falls back to predefined supportive responses.

## Deploy

### Render (backend + webhooks)

- Use `deploy/render/render.yaml`
- Dockerfile: `deploy/render/Dockerfile`
- Copy env values from `deploy/render/.env.production.example` for live setup

### Vercel (frontend)

- Deploy `deploy/vercel/` as separate static frontend
- Keep all webhook callbacks on Render backend URL

## Security

- Never commit real API keys
- Rotate any key exposed during testing
- Use HTTPS URLs for all production callbacks
