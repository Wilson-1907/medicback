# Afya Rafiki appointment reminders (Render)

The backend sends **three automated SMS/WhatsApp reminders** per booked appointment:

| When | Column | Message |
|------|--------|---------|
| **7 days** before visit | `reminder_7d_sent_at` | Afya Rafiki — appointment next week |
| **3 days** before visit | `reminder_3d_sent_at` | Afya Rafiki — appointment in 3 days |
| **Night before** (8:00 PM local server time) | `reminder_night_sent_at` | Afya Rafiki — visit tomorrow |

Recipients must have **opted in** and replied **YES/NDIO** to the consent message.

## CRON_SECRET (you create it — it does not exist yet)

1. Generate a random string (PowerShell example):
   ```powershell
   -join ((48..57) + (65..90) + (97..122) | Get-Random -Count 40 | ForEach-Object { [char]$_ })
   ```
2. Save it — use the **same value** on the **web service** and the **cron job**.

## Option A — Render Cron Job (recommended)

Repo files: `deploy/render/Dockerfile.cron` + `deploy/render/cron_ping.sh`.

### A1. Set secret on medicback web service

[Render Dashboard](https://dashboard.render.com) → **medicback** web service → **Environment**:

| Key | Value |
|-----|--------|
| `CRON_SECRET` | your random string |

Save → wait for redeploy.

### A2. Create the cron job

**New +** → **Cron Job** → connect repo **`Wilson-1907/medicback`**:

| Field | Value |
|-------|--------|
| **Name** | `medicback-cron-reminders` |
| **Region** | Same as web service |
| **Branch** | `main` |
| **Runtime** | **Docker** |
| **Dockerfile path** | `deploy/render/Dockerfile.cron` |
| **Schedule** | `*/10 * * * *` (every 10 min) or `*/5 * * * *` (every 5) |
| **Docker command** | *(leave empty — uses `CMD` in Dockerfile.cron)* |

**Environment** on the cron job:

| Key | Value |
|-----|--------|
| `CRON_SECRET` | **same** as web service |
| `CRON_BASE_URL` | `https://medicback.onrender.com` |

Create → **Logs** on first run → JSON with `"ok": true`.

### A3. Test manually

```http
GET https://medicback.onrender.com/cron_run_reminders.php?key=YOUR_CRON_SECRET
```

## Option B — Blueprint in repo (`render.yaml`)

`deploy/render/render.yaml` defines both the web service and cron job. `CRON_SECRET` uses `sync: false` (Render prompts once).

After pushing, in Render: **Blueprints** → sync, or link the blueprint if not already linked.

Shared secret via env group `medicback-shared` — one `CRON_SECRET` for web + cron.

## Manual test

```http
GET https://medicback.onrender.com/cron_run_reminders.php?key=YOUR_CRON_SECRET
```

Response example:

```json
{
  "ok": true,
  "appointment_reminders": { "7d": 0, "3d": 1, "night": 0 },
  "scheduled_messages": { "processed": 1, "sent": 1, "failed": 0 },
  "engagement_boost": { "sent": 0 },
  "hpv_drip_repaired": 0
}
```

## Env vars

- `CLINIC_SITE_NAME` — e.g. `Nyeri Town Health Center` (used in reminder text)
- `AFYA_RAFIKI_NAME` — default `Afya Rafiki`
