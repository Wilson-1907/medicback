#!/bin/sh
# Render Cron Job: ping medicback reminder + drip processor.
# Requires env CRON_SECRET and CRON_BASE_URL on the cron service.
set -eu

BASE="${CRON_BASE_URL:-https://medicback.onrender.com}"
KEY="${CRON_SECRET:-}"

URL="${BASE}/cron_run_reminders.php"
if [ -n "$KEY" ]; then
  URL="${URL}?key=${KEY}"
fi

echo "Pinging ${URL}"
curl -fsS "$URL"
echo ""
