# Render Backend Deployment

This folder defines deployment for the PHP backend (`hospital_portal`) on Render.

## What Render Hosts

- Staff web app (`index.php`, `patients.php`, `patient_view.php`)
- Africa's Talking webhooks:
  - `/webhook_africastalking.php` (incoming messages)
  - `/webhook_delivery_report.php` (delivery callbacks)
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

- Incoming messages:
  - `https://YOUR-RENDER-APP.onrender.com/webhook_africastalking.php`
- Delivery reports:
  - `https://YOUR-RENDER-APP.onrender.com/webhook_delivery_report.php`
