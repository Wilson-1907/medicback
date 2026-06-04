# Render Backend Deployment

This folder defines deployment for the PHP backend (`hospital_portal`) on Render.

## What Render Hosts

- Staff web app (`index.php`, `patients.php`, `patient_view.php`)
- Messaging webhooks:
  - `/webhook_whatsapp.php` — WhatsApp via Meta Cloud API (typical with **Mteja**)
  - `/webhook_africastalking.php` — SMS / optional AT WhatsApp
  - `/webhook_delivery_report.php` — delivery callbacks
- Go-live guide: `hospital_portal/docs/MTEJA_WHATSAPP_GO_LIVE.md`
- OpenAI-backed patient reply logic

## Deploy Steps

1. Push repository to GitHub.
2. Create new Render Web Service from this repo.
3. Render config file: `deploy/render/render.yaml`.
4. Runtime uses Dockerfile: `deploy/render/Dockerfile`.
5. Add environment variables using one of:
   - `deploy/render/.env.sandbox.example`
   - `deploy/render/.env.production.example`

## Production Switch

To go live with real Africa's Talking credentials:

- Set `AFRICASTALKING_MODE=production`
- Fill only `AFRICASTALKING_PROD_*` values
- Keep sandbox values empty or unchanged

## Callback URLs on Render

Once deployed to `https://YOUR-RENDER-APP.onrender.com`:

- WhatsApp (Mteja / Meta):
  - `https://YOUR-RENDER-APP.onrender.com/webhook_whatsapp.php`
- SMS / AT:
  - `https://YOUR-RENDER-APP.onrender.com/webhook_africastalking.php`
- Delivery reports:
  - `https://YOUR-RENDER-APP.onrender.com/webhook_delivery_report.php`
