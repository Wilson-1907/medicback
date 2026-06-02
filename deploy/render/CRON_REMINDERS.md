# Afya Rafiki appointment reminders (Render)

The backend sends **three automated SMS/WhatsApp reminders** per booked appointment:

| When | Column | Message |
|------|--------|---------|
| **7 days** before visit | `reminder_7d_sent_at` | Afya Rafiki — appointment next week |
| **3 days** before visit | `reminder_3d_sent_at` | Afya Rafiki — appointment in 3 days |
| **Night before** (8:00 PM local server time) | `reminder_night_sent_at` | Afya Rafiki — visit tomorrow |

Recipients must have **opted in** and replied **YES/NDIO** to the consent message.

## Schedule on Render

Create a **Cron Job** (or use [cron-job.org](https://cron-job.org)) that calls every **30–60 minutes**:

```http
GET https://medicback.onrender.com/cron_run_reminders.php?key=YOUR_CRON_SECRET
```

Set `CRON_SECRET` in the Render web service environment (same value as `?key=`).

## Manual test

```http
GET https://medicback.onrender.com/cron_run_reminders.php?key=YOUR_CRON_SECRET
```

Response example:

```json
{
  "ok": true,
  "appointment_reminders": { "7d": 0, "3d": 1, "night": 0 },
  "engagement_boost": { "sent": 0 }
}
```

## Env vars

- `CLINIC_SITE_NAME` — e.g. `Nyeri Town Health Center` (used in reminder text)
- `AFYA_RAFIKI_NAME` — default `Afya Rafiki`
