# Medicback — HPV Patient Engagement API

Backend for **Nyeri Town Health Center** (Afya Rafiki): patient registration, SMS/WhatsApp messaging, appointments, HPV workflow, and webhooks.

**Frontend (separate repo):** [medicfront](https://github.com/Wilson-1907/medicfront) — deploy `deploy/vercel` on Vercel.

## Repository layout

| Path | Purpose |
|------|---------|
| `hospital_portal/` | PHP application (API, staff UI, webhooks, cron) |
| `deploy/render/` | Docker + Render deployment config |
| `phv_pilot_schema.sql` | Base database schema |
| `hospital_portal/sql/` | Incremental migrations (run in order) |
| `hospital_portal/docs/` | Official Afya Rafiki message and flow documentation |

## Quick start (local)

1. Import `phv_pilot_schema.sql`, then run migrations under `hospital_portal/sql/`.
2. Copy `hospital_portal/.env.example` to `hospital_portal/.env` and set DB + Africa's Talking + Groq keys.
3. Serve: `php -S 127.0.0.1:8000 -t hospital_portal`

See `hospital_portal/README.md` for workflows, API list, and webhooks.

## Production

- **API / webhooks:** Render (`deploy/render/`) — e.g. `https://medicback.onrender.com`
- **Console UI:** Vercel (medicfront repo)
- Set `HOSPITAL_NAME` and `CLINIC_SITE_NAME` to `Nyeri Town Health Center` on Render.

## Security

Do not commit `.env` or API keys. Rotate any key that was exposed during testing.
