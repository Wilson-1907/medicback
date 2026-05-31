# Implementation Guide: AI Replies, Language Support, and Appointment Reminders

## Overview

This implementation adds three critical features to the medicback PHV engagement system:

1. **✅ Enhanced Patient Message Routing** - Messages sent by patients now reliably reach the AI reply system
2. **✅ Multilingual Support** - Full Swahili (and English) conversation support with language-aware messages
3. **✅ Appointment Reminder System** - Immediate confirmation + 7-day, 3-day, and night-before reminders

---

## Files Modified/Created

### 1. `webhook_africastalking.php` - FIXED
**Problem:** Patient messages weren't reaching AI consistently due to:
- Loose phone number matching
- No fallback lookup
- Language not being retrieved

**Solution:**
- Improved phone normalization with E.164 format enforcement
- Added opted-in status check in query
- Fetch `preferred_language` during patient lookup
- Pass language to all message functions
- Better error logging and edge case handling

**Key Features:**
```php
function find_patient_by_phone(string $phone): ?array
// - Exact match: c.address = ?
// - Flexible match: digit-only comparison
// - Only opted-in patients
// - Returns preferred_language
```

### 2. `openai_assistant.php` - ENHANCED
**New:** Language-aware system prompts

**Features:**
- English and Swahili support (extensible to other languages)
- Context-aware conversation history (up to 12 turns)
- Fallback responses in patient's language
- Full conversation audit trail in `ai_turns` table

**Usage:**
```php
$ai = ai_generate_reply($patientId, $channel, $body, $lang);
// Returns: ['ok' => bool, 'reply' => string, 'error' => ?string]
```

### 3. `messaging.php` - EXPANDED
**New:** Multilingual message template functions

**Functions Added:**
- `build_welcome_message($name, $lang)` - Swahili/English welcome
- `build_appointment_confirmation_message($name, $appointment, $lang)` - Immediate confirmation
- `build_engagement_menu_message($lang)` - Multilingual menu
- `build_appointment_reminder_message(..., $lang)` - Reminder with language support
- `send_appointment_confirmation_messages()` - New function to send initial confirmation

**Language Support:**
```php
'en' => English messages
'sw' => Swahili (Kiswahili) messages
// Extensible to other language codes
```

### 4. `reminders.php` - IMPROVED
**New:** Language-aware reminder dispatching

**Timing:**
- **7 days before:** First appointment reminder (ordinal 1/3)
- **3 days before:** Second appointment reminder (ordinal 2/3)
- **Night before (20:00):** Final appointment reminder (ordinal 3/3)

**Key Query:**
```sql
WHERE a.status IN ('proposed','confirmed')
  AND a.{$column} IS NULL
  AND NOW() >= {$whenExpr}
```

Fetches `preferred_language` for each patient to send language-specific messages.

### 5. `api/appointments.php` - NEW
**Actions:**
- `action=add` - Create new appointment
- `action=reschedule` - Update existing appointment

**Features:**
- Sends immediate confirmation message on creation
- Required `reason` field for audit trail
- Language-aware confirmation based on patient's preference
- Transaction support with rollback

**New Behavior:**
```php
// On successful appointment creation:
send_appointment_confirmation_messages(
    $patientId,
    $patientName,
    $appointment,
    $lang  // From patient record
);
```

### 6. `api/patients.php` - ENHANCED
**New:** Language-aware welcome messages

**Changes:**
- Registration endpoint now sends language-aware welcome
- Fetches `preferred_language` from request body
- Sends welcome + engagement menu in patient's language

**Usage:**
```json
POST /api/patients.php
{
  "full_name": "John Doe",
  "phone": "+254712345678",
  "preferred_language": "sw",
  "contact_channel": "whatsapp",
  "opt_in": true
}
```

---

## Database Schema (No Changes Required)

All features use existing schema fields:

- **patients.preferred_language** - Already exists (default 'en')
- **appointments.reminder_*_sent_at** - Already exists (7d, 3d, night)
- **ai_conversations** - Already exists
- **ai_turns** - Already exists
- **outbound_messages** - Already exists

---

## Deployment Checklist

### 1. Update Core Files
```bash
# Replace webhook handler
cp webhook_africastalking.php hospital_portal/

# Replace AI assistant
cp openai_assistant.php hospital_portal/

# Replace messaging functions
cp messaging.php hospital_portal/

# Replace reminder scheduler
cp reminders.php hospital_portal/

# Update API endpoints
cp api/appointments.php hospital_portal/api/
cp api/patients.php hospital_portal/api/
```

### 2. Test Locally

**Test 1: AI Message Routing**
```bash
# Register patient with language preference
curl -X POST http://localhost:8000/api/patients.php \
  -H "Content-Type: application/json" \
  -d '{
    "full_name": "Test Patient",
    "phone": "+254712345678",
    "preferred_language": "sw",
    "contact_channel": "sms",
    "opt_in": true
  }'

# Verify welcome message sent in Swahili in outbound_messages table
SELECT * FROM outbound_messages WHERE patient_id = ? ORDER BY created_at DESC LIMIT 1;
```

**Test 2: Swahili Conversation**
```bash
# Simulate incoming Swahili message via webhook
# Check that patient's preferred_language is loaded
# Verify AI system prompt is in Swahili
# Check ai_turns table for Swahili conversation

SELECT * FROM ai_turns WHERE conversation_id = ? ORDER BY created_at;
```

**Test 3: Appointment Reminders**
```bash
# Create appointment with future date
curl -X POST http://localhost:8000/api/appointments.php \
  -H "Content-Type: application/json" \
  -d '{
    "patient_id": 1,
    "scheduled_start": "2026-06-10 10:00:00",
    "reason": "Follow-up visit",
    "action": "add"
  }'

# Verify immediate confirmation sent
SELECT * FROM outbound_messages WHERE patient_id = 1 ORDER BY created_at DESC LIMIT 1;

# Manually trigger cron (simulate scheduler)
curl http://localhost:8000/cron_run_reminders.php

# Monitor reminder_*_sent_at columns
SELECT id, reminder_7d_sent_at, reminder_3d_sent_at, reminder_night_sent_at 
FROM appointments WHERE id = ?;
```

### 3. Configure Cron Job (Production on Render)

**In Render Dashboard:**
1. Create **Background Job** (not Web Service)
2. Command: `curl https://your-render-app.onrender.com/cron_run_reminders.php`
3. Schedule: Every 30-60 minutes
4. OR use Render's native Cron Job service

**Alternative: GitHub Actions**
```yaml
name: Appointment Reminders
on:
  schedule:
    - cron: '*/45 * * * *'  # Every 45 minutes
jobs:
  remind:
    runs-on: ubuntu-latest
    steps:
      - run: curl https://your-render-app.onrender.com/cron_run_reminders.php
```

### 4. Production Deployment

**Environment Variables:**
```dotenv
OPENAI_API_KEY=sk-... # For AI replies
AFRICASTALKING_MODE=production
AFRICASTALKING_PROD_USERNAME=...
AFRICASTALKING_PROD_API_KEY=...
```

**Database Migration (Optional):**
```sql
-- No changes required - all columns already exist
-- Optional: Add index for faster reminder lookups
ALTER TABLE appointments ADD INDEX idx_reminder_7d (reminder_7d_sent_at, scheduled_start);
ALTER TABLE appointments ADD INDEX idx_reminder_3d (reminder_3d_sent_at, scheduled_start);
ALTER TABLE appointments ADD INDEX idx_reminder_night (reminder_night_sent_at, scheduled_start);
```

---

## Testing Scenarios

### Scenario 1: English Patient Sends Message
```
1. Patient registers with preferred_language='en'
2. Patient sends SMS: "How do I prevent PHV?"
3. Expected:
   - Message logged in inbound_messages
   - Patient found by phone
   - AI reply generated in English
   - Response sent back via SMS
```

### Scenario 2: Swahili Patient Sends Message
```
1. Patient registers with preferred_language='sw'
2. Patient sends WhatsApp: "Dalili za kumangalia?"
3. Expected:
   - Message logged in inbound_messages
   - Patient found by phone
   - Language detected as 'sw'
   - OpenAI gets Swahili system prompt
   - Swahili response generated
   - Response sent via WhatsApp in Swahili
```

### Scenario 3: Appointment Confirmation
```
1. Staff creates appointment via API
2. Patient receives immediate SMS: "Your appointment is confirmed..."
3. Expected:
   - outbound_messages has confirmation (message_type='appointment_reminder')
   - Engagement menu sent immediately after
   - reminder_7d_sent_at, etc. are NULL (not yet sent)
```

### Scenario 4: Reminder Progression
```
1. Appointment scheduled for 2026-06-10
2. Cron runs on 2026-06-03 → 7-day reminder sent, reminder_7d_sent_at set
3. Cron runs on 2026-06-07 → 3-day reminder sent, reminder_3d_sent_at set
4. Cron runs on 2026-06-09 20:00 → Night reminder sent, reminder_night_sent_at set
5. Expected:
   - 3 reminder messages in outbound_messages
   - All timestamps properly recorded
   - No duplicate messages (NULL checks prevent re-sending)
```

---

## Troubleshooting

### Issue: Messages Not Reaching AI
**Check:**
1. Is patient opted_in? `SELECT opted_in FROM contact_channels WHERE patient_id = ?`
2. Is phone number normalized? `SELECT address FROM contact_channels WHERE patient_id = ?`
3. Are inbound messages being logged? `SELECT * FROM inbound_messages ORDER BY received_at DESC LIMIT 10`
4. Check webhook logs in Render dashboard

**Fix:**
- Re-register patient with explicit opt-in
- Test with exact E.164 format: `+254712345678`

### Issue: Reminders Not Sending
**Check:**
1. Is cron job running? Check Render logs: `cron_run_reminders.php`
2. Is AFRICASTALKING_API_KEY set? `echo $AFRICASTALKING_API_KEY`
3. Are reminders_*_sent_at still NULL? `SELECT * FROM appointments WHERE reminder_7d_sent_at IS NULL`
4. Are appointments in future? `SELECT scheduled_start FROM appointments`

**Fix:**
- Verify cron job is configured and running
- Check Africa's Talking API credentials
- Ensure appointments are in future (not past)

### Issue: Wrong Language in Response
**Check:**
1. Is preferred_language set correctly? `SELECT preferred_language FROM patients WHERE id = ?`
2. Is language passed to message builders?
3. Check ai_turns for system prompt language

**Fix:**
- Update patient record: `UPDATE patients SET preferred_language='sw' WHERE id = ?`
- Verify webhook is fetching language in `find_patient_by_phone()`

---

## Monitoring & Analytics

### Key Queries for Monitoring

**Message Routing Success Rate:**
```sql
SELECT 
  DATE(received_at) as day,
  COUNT(*) as total_messages,
  COUNT(CASE WHEN patient_id IS NOT NULL THEN 1 END) as routed,
  ROUND(100.0 * COUNT(CASE WHEN patient_id IS NOT NULL THEN 1 END) / COUNT(*), 2) as route_rate
FROM inbound_messages
GROUP BY DATE(received_at)
ORDER BY day DESC;
```

**AI Conversation Activity:**
```sql
SELECT 
  DATE(opened_at) as day,
  COUNT(DISTINCT patient_id) as unique_patients,
  COUNT(*) as conversations,
  (SELECT COUNT(*) FROM ai_turns WHERE conversation_id IN (...)) as turns
FROM ai_conversations
GROUP BY DATE(opened_at);
```

**Reminder Delivery:**
```sql
SELECT 
  'reminder_7d' as type,
  COUNT(*) as sent,
  AVG(TIMESTAMPDIFF(SECOND, reminder_7d_sent_at, CURRENT_TIMESTAMP)) as avg_age_seconds
FROM appointments
WHERE reminder_7d_sent_at IS NOT NULL
UNION ALL
SELECT 
  'reminder_3d',
  COUNT(*),
  AVG(TIMESTAMPDIFF(SECOND, reminder_3d_sent_at, CURRENT_TIMESTAMP))
FROM appointments
WHERE reminder_3d_sent_at IS NOT NULL
UNION ALL
SELECT 
  'reminder_night',
  COUNT(*),
  AVG(TIMESTAMPDIFF(SECOND, reminder_night_sent_at, CURRENT_TIMESTAMP))
FROM appointments
WHERE reminder_night_sent_at IS NOT NULL;
```

---

## Summary

✅ **All three requirements implemented:**

1. **Patient AI Replies** - Messages now reliably reach the system with robust phone matching
2. **Language Support** - Full Swahili + English support with language-aware prompts and messages
3. **Appointment Reminders** - Immediate confirmation + optimized 7-day, 3-day, night-before reminders

**Testing:** Ready for local testing and production deployment to Render.

**Next Steps:**
1. Deploy files to repository
2. Test locally with provided scenarios
3. Configure cron scheduler on Render
4. Monitor via provided SQL queries
