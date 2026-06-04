# Afya Rafiki — Mteja knowledge base (English)

Import this document into the **Mteja knowledge base** for **English** (`en`).

| Setting | Value |
|---------|--------|
| Knowledge base name | Afya Rafiki — English |
| Language | English |
| Facility | Nyeri Town Health Center |
| Referral hospital | Nyeri County Referral Hospital |
| Menu keyword | HELP, MENU |
| Escalation keyword | DOCTOR |

Add each **Keywords** value as a trigger phrase in Mteja.

---

## Programme overview

| ID | Keywords | Question | Answer |
|----|----------|----------|--------|
| A1 | afya rafiki, what is this, what is afya rafiki | What is Afya Rafiki? | Afya Rafiki is a confidential follow-up service from Nyeri Town Health Center after HPV screening. We send health information, appointment reminders, and answer common questions. We do not replace your nurse or doctor — reply DOCTOR to reach a health worker. |
| A3 | privacy, confidential, private | Is my information private? | Yes. Your information is kept confidential and used only for your HPV follow-up care at Nyeri Town Health Center. |
| A5 | cost, payment, free, pay | Do I pay for Afya Rafiki messages? | Afya Rafiki messages are part of your care programme. For clinic fees or tests, ask at Nyeri Town Health Center reception. |
| A7 | hours, location, where, open, address | Where is the clinic and when is it open? | Main site: Nyeri Town Health Center. For opening hours and directions, contact the facility directly or visit reception. |

---

## HELP menu

| ID | Keywords | Question | Answer |
|----|----------|----------|--------|
| B0 | help, menu, 0, options | (Menu) | Afya Rafiki — options: 1) What is HPV? 2) Do I have cervical cancer? 3) Can HPV be treated? 4) Appointments / reschedule 5) Symptoms of HPV 6) Symptoms of cervical cancer 7) Speak to a provider (reply DOCTOR). Type your question or option number. |

---

## Core FAQs

| ID | Keywords | Question | Answer |
|----|----------|----------|--------|
| C1 | 1, what is hpv, hpv | What is HPV? | HPV is a common virus that can affect the cervix. Some types may cause cervical cancer if not treated early. Follow-up care helps protect your health. |
| C2 | 2, cervical cancer, do i have cancer, cancer | Do I have cervical cancer? | A positive HPV result does not mean you have cervical cancer. It means you have HPV virus. HPV can lead to cervical cancer if left untreated for a long time. Additional follow-up care is needed. Please attend your clinic appointment. |
| C3 | 3, hpv treated, treat hpv, treatment | Can HPV be treated? | HPV infections often clear naturally. Follow-up care helps health providers monitor and manage any cervical changes early. |
| C4 | 4, appointment, reschedule, book, 7 | Appointments / reschedule | For appointments, contact Nyeri Town Health Center or wait for your reminder message. Reply DOCTOR if you need urgent help. |
| C5 | 5, hpv symptoms, symptoms of hpv | What are symptoms of HPV? | Most people with HPV have no symptoms. HPV usually does not cause pain or itching. The body often clears the infection. Some types cause cervical changes found only on screening (HPV test, VIA). Regular screening matters even when you feel well. Unusual bleeding, persistent pain, or unusual discharge — see a provider. |
| C6 | 6, cervical cancer symptoms, cancer symptoms | Symptoms of cervical cancer? | Many women with early changes have no symptoms. Possible signs: bleeding after sex, between periods, or after menopause; unusual discharge; pelvic pain; pain during sex. These do not always mean cancer — visit a health facility for assessment. |
| C7 | doctor, speak provider, health worker, nurse | How do I speak to a health worker? | Reply **DOCTOR**. We will ask you briefly why you need help, then a provider will follow up. If emergency (heavy bleeding, severe pain, fever), go to the clinic immediately. |

---

## Results & pathways

| ID | Keywords | Question | Answer |
|----|----------|----------|--------|
| D1 | negative hpv, hpv negative, result negative | My HPV result is negative — what next? | A negative result means no HPV was detected now. Return for screening in 5 years (or 3 years if you are living with HIV), or sooner if your provider advises. |
| D2 | positive hpv, hpv positive, result positive | My HPV result is positive — what next? | HPV positive does not mean cervical cancer. You need follow-up at Nyeri Town Health Center, often including VIA. Attend your scheduled appointment. Afya Rafiki will send reminders and education. |
| D3 | via, what is via | What is VIA? | VIA (Visual Inspection with Acetic acid) is a quick exam where a provider looks at the cervix after applying a vinegar solution. It helps find changes that may need treatment. |
| D4 | thermal ablation, ablation, treatment heat | What is Thermal Ablation? | Thermal Ablation treats abnormal cervical cells with heat. It is usually quick, done as an outpatient procedure, and helps prevent cancer. Follow after-care instructions and attend follow-up visits. |
| D5 | referral, specialist, referral hospital | Why was I referred to Nyeri County Referral Hospital? | Some VIA findings need a specialist assessment. Referral does not always mean cancer — more tests may be needed. Please attend your referral appointment date. |

---

## Appointments & missed visits

| ID | Keywords | Question | Answer |
|----|----------|----------|--------|
| E1 | missed appointment, missed visit, did not attend | I missed my appointment | Thank you for telling us. Follow-up protects your health. Reply with: 1 Transport 2 Forgot 3 Fear/worry 4 Work/family 5 Unwell 6 Came but not seen 7 Other — or reply DOCTOR. |
| E2 | reschedule, book again, new appointment, yes reschedule | I want to reschedule | Reply **1** if you want us to help reschedule at Nyeri Town Health Center, **2** if you will contact the clinic yourself, or **DOCTOR** to speak to a provider. |

---

## Urgent & escalation

| ID | Keywords | Question | Answer |
|----|----------|----------|--------|
| F1 | emergency, bleeding, heavy bleeding, urgent | I have heavy bleeding / emergency | Go to **Nyeri Town Health Center** or the nearest health facility **immediately**. Afya Rafiki cannot manage emergencies by chat alone. |
| F2 | scared, worried, afraid, anxious | I am very scared or worried | Your feelings are understandable. A provider can support you best — reply **DOCTOR**. If symptoms are severe, go to the clinic. |

---

## Unregistered users

| ID | Keywords | Question | Answer |
|----|----------|----------|--------|
| G1 | register, not registered, sign up | I am not registered | To get personalized Afya Rafiki support, register your phone number at Nyeri Town Health Center with message consent. For urgent care, contact the hospital directly. |

---

## Mteja import checklist

- [ ] Create knowledge base: **Afya Rafiki — English**
- [ ] Import all rows above (25 Q&A pairs)
- [ ] Link to English WhatsApp channel / chatbot
- [ ] Map **HELP** → entry B0
- [ ] Map **DOCTOR** → handoff or medicback webhook
- [ ] Rule: do not diagnose; encourage clinic visits

**Registered patients:** medicback can also answer via `https://medicback.onrender.com/webhook_whatsapp.php`

---

## CSV bulk upload (optional)

```csv
id,keywords,question,answer,category
A1,"afya rafiki,what is this",What is Afya Rafiki?,Afya Rafiki is a confidential follow-up service...,Overview
B0,"help,menu,0",(Menu),Afya Rafiki — options: 1) What is HPV?...,Menu
C1,"1,what is hpv",What is HPV?,HPV is a common virus...,FAQ
```

---

*English only — Kiswahili: `MTEJA_KNOWLEDGE_BASE_SW.md` — June 2026*
