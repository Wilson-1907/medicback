# Afya Rafiki — WhatsApp message templates (Mteja / Meta)

Submit these in the **Mteja** dashboard (or Meta Business Manager) for **Nyeri Town Health Center**.

| Setting | Value |
|---------|--------|
| Business name | Nyeri Town Health Center |
| Service | Afya Rafiki — HPV follow-up |
| Recommended category | **UTILITY** (healthcare follow-up, appointments, test results) |
| Language codes | English: `en` · Kiswahili: `sw` |

**Variables:** Meta uses `{{1}}`, `{{2}}`, … in order.  
**Samples** below are for approval only — real values come from the hospital system.

---

## How to use this document with Mteja

1. Create **each row** as a separate template (English + Kiswahili = two templates).
2. Paste **Body** exactly; keep `{{1}}` / `{{2}}` where shown.
3. Use **Category: UTILITY** unless Mteja advises otherwise.
4. After approval, send Mteja the **template names** so we can map them in medicback (future step).

**Phase 1 (go-live):** templates 1–8 below.  
**Phase 2 (optional):** HPV counseling series — often sent inside the 24-hour session after the patient replies; add later if Meta requires templates for each.

---

## 1. Welcome (sent right after registration)

| Field | English | Kiswahili |
|-------|---------|----------|
| **Template name** | `afya_welcome_en` | `afya_welcome_sw` |
| **Category** | UTILITY | UTILITY |
| **When sent** | Patient registered with WhatsApp + message consent | Same |

**Body (en):**

```
Hello. Welcome to Afya Rafiki. We are here to support you after your HPV screening results. This service will provide health information, reminders, and guidance for your follow-up care. Your information will remain confidential.
```

**Body (sw):**

```
Karibu kwenye Afya Rafiki. Tuko hapa kukusaidia baada ya majibu yako ya uchunguzi wa HPV. Huduma hii itakutumia taarifa za afya, vikumbusho, na mwongozo wa huduma ya ufuatiliaji. Taarifa zako zitahifadhiwa kwa siri.
```

**Variables:** none  
**Sample for reviewer:** (static text)

---

## 2. HPV negative — HIV positive (3-year return)

| Field | English | Kiswahili |
|-------|---------|----------|
| **Template name** | `afya_hpv_neg_hivpos_en` | `afya_hpv_neg_hivpos_sw` |
| **Category** | UTILITY | UTILITY |
| **When sent** | Staff confirms HPV negative; patient HIV+ | Same |

**Body (en):**

```
Hello {{1}}, your HPV test result is negative. This means no HPV infection was detected at this time. To continue protecting your health, please return to Nyeri Town Health Center for repeat cervical cancer screening after 3 years, or earlier if advised by your healthcare provider. Thank you for choosing Afya Rafiki.
```

**Body (sw):**

```
Habari {{1}}, majibu yako ya HPV ni hasi (negative). Hii inamaanisha kuwa hakuna maambukizi ya HPV yaliyopatikana kwa sasa. Ili kuendelea kulinda afya yako, tafadhali rudi Nyeri Town Health Center kwa uchunguzi mwingine wa virusi vya HPV baada ya miaka 3 au mapema zaidi ikiwa utaelekezwa na mhudumu wa afya. Asante kwa kutumia Afya Rafiki.
```

| Variable | Sample |
|----------|--------|
| `{{1}}` | Jane |

---

## 3. HPV negative — HIV negative (5-year return)

| Field | English | Kiswahili |
|-------|---------|----------|
| **Template name** | `afya_hpv_neg_hivneg_en` | `afya_hpv_neg_hivneg_sw` |
| **Category** | UTILITY | UTILITY |

**Body (en):**

```
Hello {{1}}, your HPV test result is negative. This means no HPV infection was detected at this time. To maintain good cervical health, please return to Nyeri Town Health Center for repeat cervical cancer screening after 5 years, or earlier if advised by your healthcare provider. Thank you for choosing Afya Rafiki.
```

**Body (sw):**

```
Habari {{1}}, majibu yako ya HPV ni hasi (negative). Hii inamaanisha kuwa hakuna maambukizi ya HPV yaliyopatikana kwa sasa. Ili kudumisha afya nzuri ya mlango wa kizazi, tafadhali rudi Nyeri Town Health Center kwa uchunguzi mwingine wa saratani ya mlango wa kizazi baada ya miaka 5 au mapema zaidi ikiwa utaelekezwa na mhudumu wa afya. Asante kwa kutumia Afya Rafiki.
```

| Variable | Sample |
|----------|--------|
| `{{1}}` | Mary |

---

## 4. HPV positive + follow-up appointment

| Field | English | Kiswahili |
|-------|---------|----------|
| **Template name** | `afya_hpv_positive_en` | `afya_hpv_positive_sw` |
| **Category** | UTILITY | UTILITY |
| **When sent** | Staff confirms HPV positive | Same |

**Body (en):**

```
Hello {{1}}, your HPV test result is positive. This does not mean that you have cervical cancer. It means that the HPV virus was detected and further follow-up is needed to help protect your health and prevent cervical cancer. You have been scheduled for a follow-up appointment at Nyeri Town Health Center on: {{2}}. Please attend your appointment as scheduled. If you have any questions, Afya Rafiki is here to support you. Thank you for choosing Afya Rafiki.
```

**Body (sw):**

```
Habari {{1}}, majibu yako ya kipimo cha HPV ni chanya (positive). Hii haimaanishi kuwa una saratani ya mlango wa kizazi. Inamaanisha kuwa virusi vya HPV vimepatikana na unahitaji huduma zaidi ya ufuatiliaji ili kulinda afya yako na kusaidia kuzuia saratani ya mlango wa kizazi. Umepangiwa miadi ya ufuatiliaji katika Nyeri Town Health Center tarehe: {{2}}. Tafadhali hudhuria miadi yako kama ulivyopangiwa. Ikiwa una maswali yoyote, Afya Rafiki iko hapa kukusaidia. Asante kwa kutumia Afya Rafiki.
```

| Variable | Sample |
|----------|--------|
| `{{1}}` | Grace |
| `{{2}}` | Monday, 15 Jun 2026 10:00 AM |

---

## 5. Appointment reminder — 7 days before

| Field | English | Kiswahili |
|-------|---------|----------|
| **Template name** | `afya_appt_reminder_7d_en` | `afya_appt_reminder_7d_sw` |
| **Category** | UTILITY | UTILITY |

**Body (en):**

```
Reminder from Afya Rafiki: You have a follow-up appointment scheduled next week ({{1}}) at Nyeri Town Health Center. Attending follow-up care is important for your health.
```

**Body (sw):**

```
Kikumbusho kutoka Afya Rafiki: Una miadi ya ufuatiliaji wiki ijayo ({{1}}) katika Nyeri Town Health Center. Kuhudhuria huduma ya ufuatiliaji ni muhimu kwa afya yako.
```

| Variable | Sample |
|----------|--------|
| `{{1}}` | Monday, 15 Jun 2026 10:00 AM |

---

## 6. Appointment reminder — 3 days before

| Field | English | Kiswahili |
|-------|---------|----------|
| **Template name** | `afya_appt_reminder_3d_en` | `afya_appt_reminder_3d_sw` |
| **Category** | UTILITY | UTILITY |

**Body (en):**

```
Reminder from Afya Rafiki: You have a follow-up appointment scheduled on ({{1}}) at Nyeri Town Health Center. Attending follow-up care is important for your health.
```

**Body (sw):**

```
Kikumbusho kutoka Afya Rafiki: Una miadi ya ufuatiliaji ({{1}}) katika Nyeri Town Health Center. Kuhudhuria huduma ya ufuatiliaji ni muhimu kwa afya yako.
```

| Variable | Sample |
|----------|--------|
| `{{1}}` | Monday, 15 Jun 2026 10:00 AM |

---

## 7. Appointment reminder — 1 day before (tomorrow)

| Field | English | Kiswahili |
|-------|---------|----------|
| **Template name** | `afya_appt_reminder_1d_en` | `afya_appt_reminder_1d_sw` |
| **Category** | UTILITY | UTILITY |

**Body (en):**

```
Reminder: Your clinic follow-up visit at Nyeri Town Health Center is tomorrow. Please attend as scheduled or contact the facility if you need assistance.
```

**Body (sw):**

```
Kikumbusho: Ziara yako ya ufuatiliaji kliniki Nyeri Town Health Center ni kesho. Tafadhali hudhuria kama ulivyopangiwa au wasiliana na kliniki ikiwa unahitaji msaada.
```

**Variables:** none

---

## 8. VIA referral — specialist (suspicious / cancer pathway)

| Field | English | Kiswahili |
|-------|---------|----------|
| **Template name** | `afya_via_referral_en` | `afya_via_referral_sw` |
| **Category** | UTILITY | UTILITY |
| **When sent** | VIA+ with cancer flag at registration | Same |

**Body (en):**

```
Hello {{1}}, your VIA examination showed changes on the cervix that require further assessment by a specialist. This does not necessarily mean that you have cervical cancer. However, additional tests are needed. You have been referred to Nyeri County Referral Hospital. Please attend your scheduled appointment on: {{2}}. If you have any questions, contact your healthcare provider or Afya Rafiki. Thank you.
```

**Body (sw):**

```
Habari {{1}}, uchunguzi wako wa VIA ulionyesha mabadiliko kwenye mlango wa kizazi ambayo yanahitaji tathmini zaidi na daktari bingwa. Hii haimaanishi moja kwa moja kuwa una saratani ya mlango wa kizazi. Umepewa rufaa kwenda Hospitali ya Rufaa ya Kaunti ya Nyeri. Tafadhali hudhuria miadi yako tarehe: {{2}}. Kwa maswali, wasiliana na mhudumu wa afya au Afya Rafiki. Asante.
```

| Variable | Sample |
|----------|--------|
| `{{1}}` | Anne |
| `{{2}}` | Tuesday, 20 Jun 2026 9:00 AM |

---

## Phase 2 — HPV counseling (optional for first approval)

These are **16 educational messages** sent over several days after a **positive** HPV result. Options:

- **A)** Rely on the **24-hour session** after the patient replies to the welcome/result (no template for each; simpler).
- **B)** Submit **one utility template** per counseling step (32 templates EN+SW) — full compliance, longer approval.

If you choose **B** for step 1 only, use template name pattern:  
`afya_counsel_pos_01_en` … `afya_counsel_pos_16_en` (and `_sw`).

Full counseling text is in code: `hospital_portal/afya_counseling_positive.php`.  
Ask Mteja which approach they recommend for Kenya WABA.

---

## Checklist before submit

- [ ] Display name: **Nyeri Town Health Center** (or **Afya Rafiki** if approved)
- [ ] All Phase 1 templates created in **English** and **Kiswahili**
- [ ] Category **UTILITY** selected
- [ ] Sample values filled for `{{1}}` and `{{2}}`
- [ ] No marketing language; no fear/stigma wording
- [ ] Mteja confirms template names when **APPROVED**

---

## After approval — what we need back from Mteja

| Item | Example |
|------|---------|
| Template name | `afya_welcome_en` |
| Meta template ID / Mteja template ID | (they provide) |
| Status | APPROVED |
| Language | en / sw |

We will then connect medicback to send via **template name + variables** instead of free-text session messages (code update after you have IDs).

---

## Quick copy for Mteja email

```
Subject: WhatsApp template submission — Nyeri Town Health Center (Afya Rafiki)

Please create and submit the attached UTILITY templates (English + Kiswahili):
- afya_welcome_en / afya_welcome_sw
- afya_hpv_neg_hivpos_en / afya_hpv_neg_hivpos_sw
- afya_hpv_neg_hivneg_en / afya_hpv_neg_hivneg_sw
- afya_hpv_positive_en / afya_hpv_positive_sw
- afya_appt_reminder_7d_en / afya_appt_reminder_7d_sw
- afya_appt_reminder_3d_en / afya_appt_reminder_3d_sw
- afya_appt_reminder_1d_en / afya_appt_reminder_1d_sw
- afya_via_referral_en / afya_via_referral_sw

Full body text is in our shared document: WHATSAPP_MESSAGE_TEMPLATES.md
Healthcare programme: HPV follow-up only. Category: UTILITY.

Reply with template IDs when approved.
```

---

*Aligned with Afya Rafiki official script — June 2026*
