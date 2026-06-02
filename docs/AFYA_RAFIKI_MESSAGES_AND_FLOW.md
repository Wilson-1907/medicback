# Afya Rafiki — System Flow & Message Library

**Chatbot name:** Afya Rafiki  
**Facility:** Nyeri Town Health Center / Nyeri Level 4 Hospital HPV program  
**Client ID format:** `NC/NTHC/001/` + unique digits (e.g. `022` → `NC/NTHC/001/022`)

---

## 1. Chatbot identity & tone

- Warm, supportive, respectful, simple, encouraging, confidential  
- Avoid frightening language, stigma, jargon, robotic tone  
- Short messages; encourage follow-up; normalize HPV; promote hope; escalate complex issues to staff  

---

## 2. Registration (staff console)

1. Nurse enters **client digits only** (prefix `NC/NTHC/001/` is fixed).  
2. Demographics: name, DOB (age calculated), phone +254, language, channel.  
3. Clinical: HIV (positive / negative / **not known**), HPV done before (+ prior result), residence, VIA result/date, cancer flag if VIA+, treatment date.  
4. **Written consent signed on paper** — checkbox required; **no SMS asking YES/NO for consent**.  
5. On save (if consent checked):  
   - Welcome SMS (once)  
   - Referral SMS immediately if VIA positive + cancer → **Nyeri County Referral Hospital**  
   - Scheduled check-up reminders per rules below  

---

## 3. Automated follow-up scheduling

| Rule | Scheduled SMS |
|------|----------------|
| VIA **negative** | Return in **1 year** (annual check-up) |
| HIV **positive** + HPV **negative** | HPV check-up in **5 years** |
| HIV **positive** + HPV **positive** | HPV check-up in **3 years** |
| VIA **positive** + **cancer** | **Immediate** referral SMS |

Cron (`cron_run_reminders.php`) sends queued messages when due.

---

## 4. End-to-end workflow

```
Registration (signed consent on paper)
    ↓
Welcome SMS (Afya Rafiki)
    ↓
Staff records/confirms HPV lab result (when available)
    ↓
HPV result notification SMS
    ↓
Counseling drip (positive: 12 messages / negative: 6 — spaced via cron)
    ↓
Appointment reminders (7 days, 3 days, 1 day before)
    ↓
FAQ (HELP) / DOCTOR escalation / missed appointment triage
```

**Consent SMS (YES/NO) is removed** — consent is captured at registration only.

---

## 5. Constant messages (English / Swahili)

### 5.1 Welcome (sent once after registration)

**EN:** Hello. Welcome to Afya rafiki. We are here to support you after your HPV screening results. This service will provide health information, reminders, and guidance for your follow-up care. Your information will remain confidential.

**SW:** Karibu kwenye Afya rafiki. Tuko hapa kukusaidia baada ya majibu yako ya uchunguzi wa HPV. Huduma hii itakutumia taarifa za afya, vikumbusho, na mwongozo wa huduma ya ufuatiliaji. Taarifa zako zitahifadhiwa kwa siri.

### 5.2 Referral (VIA positive + cancer)

**EN:** Hello {name}, your VIA results indicate a condition that needs further care. You are referred to **Nyeri County Referral Hospital** for further assessment and treatment. Contact our clinic if you need travel support or assistance.

**SW:** Habari {name}, matokeo yako ya VIA yanaonyesha hali inayohitaji uangalizi zaidi. Tunakuelekeza kwenda **Hospitali ya Rufaa ya Kaunti ya Nyeri** kwa uchunguzi na matibabu zaidi.

### 5.3 HPV counseling (positive pathway — 12 messages)

Stored in `afya_counseling_messages_positive()` — matches approved document sections 3 (Understanding HPV through Test of Cure).

### 5.4 Appointment reminders

- **7 days:** Reminder from Afya Rafiki — follow-up next week at {site} ({date}).  
- **3 days:** Follow-up in 3 days at {site}.  
- **1 day:** Visit tomorrow at {site}; contact clinic if you need help.

### 5.5 Escalation

**EN:** Thank you for your question. A healthcare provider will be better able to assist you. Please contact your clinic or wait for a provider follow-up call.

**SW:** Asante kwa swali lako. Mhudumu wa afya ataweza kukusaidia vizuri zaidi. Tafadhali wasiliana na kliniki yako.

### 5.6 Missed appointment

**EN:** We noticed you may have missed your follow-up appointment. Would you like help rescheduling? Reply 1 YES / 2 NO

**SW:** Tumeona huenda hukuhudhuria miadi yako. Je, ungependa kusaidiwa kupanga upya? Jibu 1 NDIO / 2 HAPANA

---

## 6. Human referral triggers

Escalate to provider when patient reports severe symptoms, distress, repeated missed visits, complex medical questions, or requests direct provider support (reply **DOCTOR**).

---

## 7. Technical references

| Component | Location |
|-----------|----------|
| Message text | `hospital_portal/afya_rafiki_content.php` |
| Registration API | `hospital_portal/api/patients.php` |
| Client ID | `hospital_portal/patient_client_id.php` |
| Screening rules | `hospital_portal/patient_screening.php` |
| Scheduled SMS | `hospital_portal/scheduled_messages.php` |
| Staff UI | `deploy/vercel/app.js` |
