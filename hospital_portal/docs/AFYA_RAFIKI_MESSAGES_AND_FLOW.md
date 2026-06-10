# Afya Rafiki — How the Service Works and Every Message Sent

**Programme:** Nyeri Town Health Center — HPV follow-up care  
**Service name:** Afya Rafiki  
**How patients are reached:** SMS and WhatsApp  
**Client number on register:** NC/NTHC/001/ followed by the unique digits from the lab book (for example NC/NTHC/001/022)

---

## About Afya Rafiki

Afya Rafiki supports women after HPV screening. It sends health information, appointment reminders, and follow-up guidance by SMS or WhatsApp. Patients can reply with questions. When something needs a clinician, the hospital team is notified and can call the patient back.

Afya Rafiki speaks in a **warm, respectful, and simple** way. It avoids frightening language, stigma, heavy medical jargon, and cold replies. It encourages clinic attendance, normalises HPV, and promotes hope. It does **not** diagnose, prescribe, or replace a visit with a health worker.

At registration the patient signs the hospital form agreeing to receive messages. They are **not** asked by SMS to reply YES or NO.

### Chatbot identity and tone

| Do | Avoid |
|----|--------|
| Short messages | Frightening language |
| Encourage follow-up care | Stigmatizing terms |
| Normalize HPV infection | Heavy medical jargon |
| Promote hope and prevention | Robotic responses |
| Escalate complex issues to providers | |

---

## Step 1 — Registration at the hospital

The nurse registers the patient on the HPV console: client number, name, date of birth, phone, language (English or Kiswahili), SMS or WhatsApp, and screening details (HIV status, HPV history, residence). **VIA is not recorded at registration** — it is uploaded on the patient page after the VIA test.

When registration is saved and the patient agreed to receive messages on paper, **two messages** are sent in order: (1) thank-you for agreeing, (2) registration welcome below. HPV result messages are sent when staff confirm the lab result. VIA result messages are sent when staff record VIA after the test.

### Welcome — sent on successful registration (opt-in)

**English**  
Hello. Welcome to Afya rafiki. We are here to support you after your HPV screening results. This service will provide health information, reminders, and guidance for your follow-up care. Your information will remain confidential.

**Kiswahili**  
Karibu kwenye Afya rafiki. Tuko hapa kukusaidia baada ya majibu yako ya uchunguzi wa HPV. Huduma hii itakutumia taarifa za afya, vikumbusho, na mwongozo wa huduma ya ufuatiliaji. Taarifa zako zitahifadhiwa kwa siri.

### When HPV results are negative

Staff record and **Confirm & notify**. The patient receives **one** SMS based on HIV status:

**English — HPV negative, HIV positive**

Hello [Name],  
Your HPV test result is negative. This means no HPV infection was detected at this time. To continue protecting your health, please return to Nyeri Town Health Center for repeat cervical cancer screening after **3 years**, or earlier if advised by your healthcare provider.  
Thank you for choosing Afya Rafiki.

**English — HPV negative, HIV negative**

Hello [Name],  
Your HPV test result is negative. This means no HPV infection was detected at this time. To maintain good cervical health, please return to Nyeri Town Health Center for repeat cervical cancer screening after **5 years**, or earlier if advised by your healthcare provider.  
Thank you for choosing Afya Rafiki.

*(Kiswahili versions are sent when the patient’s language is Kiswahili — see `build_hpv_negative_result_notification` in code.)*

### When HPV results are positive

The nurse informs the patient by phone, schedules follow-up, then **Confirm & notify** in the console. The system sends:

1. **HPV positive result** with follow-up appointment date (welcome was already sent at registration)  
2. **Sixteen counseling messages** on a gentle drip schedule (3 hours, then 5 hours, then 1 day between each — not all at once)

**English — HPV positive result (excerpt)**

Hello [Name],  
Your HPV test result is positive. This does not mean that you have cervical cancer. It means that the HPV virus was detected and further follow-up is needed…  
You have been scheduled for a follow-up appointment at Nyeri Town Health Center on:  
Date: [from appointment or __________]  
Thank you for choosing Afya Rafiki.

The 16 counseling messages cover: understanding HPV, follow-up importance, reducing fear, confidence, social support, attendance, VIA, VIA results, VIA negative/positive pathways, Thermal Ablation, after-care, urgent return signs, healing advice, Test of Cure, and suspicious-for-cancer referral information. Full text: `hospital_portal/afya_counseling_positive.php`.

---

## Appointment reminders (7 days, 3 days, 1 day before)

**7 days — English**  
Reminder from Afya Rafiki: You have a follow-up appointment scheduled next week ([date]) at Nyeri Town Health Center. Attending follow-up care is important for your health.

**3 days — English**  
Reminder from Afya Rafiki: You have a follow-up appointment scheduled on ([date]) at Nyeri Town Health Center. Attending follow-up care is important for your health.

**1 day — English**  
Reminder: Your clinic follow-up visit at Nyeri Town Health Center is tomorrow. Please attend as scheduled or contact the facility if you need assistance.

---

## FAQ (reply HELP or ask naturally)

| Topic | Summary |
|-------|---------|
| What is HPV? | Common virus; follow-up protects health |
| Do I have cervical cancer? | Positive HPV ≠ cancer; attend clinic |
| Can HPV be treated? | Often clears; follow-up monitors changes |
| HPV symptoms | Usually no symptoms; screening matters |
| Cervical cancer symptoms | May be none early; list of warning signs |
| Appointments | Contact clinic or reply DOCTOR |

Full FAQ replies: `afya_faq_reply()` in `afya_rafiki_content.php`.

---

## Escalation and triage

**Complex questions — English**  
Thank you for your question. A healthcare provider will be better able to assist you. Please contact your clinic or wait for a provider follow-up call.

**Missed appointment — English**  
Hello [Name], we noticed you may have missed your scheduled follow-up… Reply 1–7 with the reason (transport, forgot, fear, work/family, unwell, attended but not seen, other). Then offer reschedule (YES / NO / speak to provider).

**Automatic referral to staff** when patients report severe symptoms, distress, repeated missed visits, complex medical questions, or request DOCTOR.

---

## VIA results (after the test — not at registration)

When the patient attends clinic for VIA and staff record the result on the patient page:

- **VIA negative** → immediate negative result SMS (counseling step 9) + annual check-up scheduled
- **VIA positive** → immediate positive result SMS (counseling step 10; Thermal Ablation pathway)
- **VIA positive + cancer/suspicious** → referral SMS to **Nyeri County Referral Hospital** — see `build_referral_message()` and `afya_via_referral` template

---

## Post-visit messages (staff-triggered when implemented)

- Acknowledgement after attending appointment (`build_post_visit_acknowledgement`)  
- After Thermal Ablation success  
- Treatment postponed / rescheduled  
- Suspicious-for-cancer referral (see referral template above)

---

## Suggested workflow

```
HPV result recorded by staff
        ↓
Confirm & notify patient
        ↓
Negative → one HIV-stratified SMS
Positive → Welcome + positive result SMS + 16 counseling messages (scheduled)
        ↓
Appointment reminders (7d, 3d, 1d)
        ↓
HELP / FAQ / AI (Afya Rafiki tone)
        ↓
Triage & escalation → staff call-back
```

---

*Implementation: `hospital_portal/afya_rafiki_content.php`, `afya_counseling_positive.php`, `hpv_results.php`, `reminders.php`, `webhook_africastalking.php`. June 2026.*
