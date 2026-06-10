# Additional Mteja templates — beyond your 102 approved pack

**Context:** You have **102 templates** (51 base names × EN + SW) from `WHATSAPP_MESSAGE_TEMPLATES.md` §1–49 plus related FAQ/counseling templates.  
**This file lists only what is still missing** for the study script to work end-to-end on WhatsApp.  
**Do not duplicate** templates already in your 102 — submit these as a **second batch** after go-live.

**Related:** flow gaps → `STUDY_GAP_ANALYSIS.md` · code mapping fixes → `newtemplates.md`

---

## Batch A — Required for correct WhatsApp text (mapping gaps)

These messages **already send on SMS** but WhatsApp uses the wrong template or `afya_fallback`.

| # | Template base | EN + SW | Why needed |
|---|---------------|---------|------------|
| A1 | `afya_registration_welcome` | 2 | Registration welcome currently maps to `afya_welcome` (language 1/2/3 menu) |
| A2 | `afya_referral_reassurance` | 2 | Sent +2 min after Nyeri referral; today maps to `afya_help_menu` |
| A3 | `afya_checkup_hiv_via_neg_3y` | 2 | Scheduled HPV re-screen **3 years** after VIA negative (HIV+) |
| A4 | `afya_checkup_hiv_via_neg_5y` | 2 | Scheduled HPV re-screen **5 years** after VIA negative (HIV−) |

**Batch A total: 8 templates**

### A1 — `afya_registration_welcome`

**When:** Immediately after consent thank-you at registration.

**Body (en):**

```
Hello. Welcome to Afya rafiki. We are here to support you after your HPV screening results. This service will provide health information, reminders, and guidance for your follow-up care. Your information will remain confidential.
```

**Body (sw):**

```
Karibu kwenye Afya rafiki. Tuko hapa kukusaidia baada ya majibu yako ya uchunguzi wa HPV. Huduma hii itakutumia taarifa za afya, vikumbusho, na mwongozo wa huduma ya ufuatiliaji. Taarifa zako zitahifadhiwa kwa siri.
```

**Variables:** none

---

### A2 — `afya_referral_reassurance`

**When:** ~1–2 days after referral (system schedules +2 minutes today).

**Body (en):**

```
Hello {{1}}, we understand that receiving a referral may cause concern. Please remember that many women referred for specialist assessment do not have cervical cancer. The purpose of the referral is to allow a closer examination of the cervix and ensure that you receive the most appropriate care. Attending your appointment is an important step in protecting your health. Afya Rafiki is here to support you.
```

**Body (sw):**

```
Habari {{1}}, tunaelewa kuwa kupokea rufaa kunaweza kukusababishia wasiwasi. Tafadhali kumbuka kuwa wanawake wengi wanaopewa rufaa kwa uchunguzi wa daktari bingwa hawapatikani na saratani ya mlango wa kizazi. Lengo la rufaa ni kusaidia daktari kuchunguza mlango wa kizazi kwa karibu zaidi na kuhakikisha unapata huduma inayofaa. Kuhudhuria miadi yako ni hatua muhimu katika kulinda afya yako. Afya Rafiki iko hapa kukusaidia.
```

| Variable | Sample |
|----------|--------|
| `{{1}}` | Anne |

---

### A3 / A4 — `afya_checkup_hiv_via_neg_3y` / `_5y`

See full wording in `newtemplates.md` § Priority 1 (same bodies as scheduled SMS in code).

---

## Batch B — Study script §12–13 (in your 102 pack but NOT wired in code)

You likely **already have** these in Mteja — **wire code first** before resubmitting.

| # | Template (in pack) | Study section | Wire when |
|---|-------------------|---------------|-----------|
| B1 | `afya_post_visit` | §12a General thank-you after attended visit | Staff marks **Patient attended** |
| B2 | `afya_missed_reschedule` | §13b Reschedule offer after missed survey | Patient replies 1–7 to missed SMS |
| B3 | `afya_counsel_pos_09` | §12b VIA neg detailed result | Already used — verify body matches study §12b |
| B4 | `afya_counsel_pos_10` | §12c VIA pos / ablation | Already used — verify body matches study §12c |

**Optional:** use `afya_appt_reminder_7d` / `_3d` / `_1d` for referral specialist appointments once scheduled.

---

## Batch C — Study script content NOT in your 102 pack (new submissions)

These match Dr Maina’s script but were never in `WHATSAPP_MESSAGE_TEMPLATES.md`.

| # | Template base | EN + SW | Study section |
|---|---------------|---------|---------------|
| C1 | `afya_referral_appt_reminder` | 2 | Referral pathway Message 3 — specialist appointment reminder |
| C2 | `afya_post_visit_via_neg` | 2 | §12b Full VIA negative result + **1-year** follow-up date |
| C3 | `afya_post_visit_ablation` | 2 | §12c After Thermal Ablation + 1-year TOC date |
| C4 | `afya_post_visit_tx_postponed` | 2 | §12c Treatment postponed / rescheduled |
| C5 | `afya_missed_reschedule_confirm` | 2 | §13c Positive reinforcement after patient chooses reschedule |

**Batch C total: 10 templates**

### C1 — `afya_referral_appt_reminder`

**Body (en):**

```
Reminder from Afya Rafiki. You have a specialist review appointment at Nyeri County Referral Hospital on {{1}}. Please attend as scheduled. This visit will help determine the most appropriate next steps for your care. If you are unable to attend, please contact your healthcare provider to arrange another appointment.
```

**Body (sw):**

```
Kikumbusho kutoka Afya Rafiki. Una miadi ya uchunguzi wa daktari bingwa katika Hospitali ya Rufaa ya Kaunti ya Nyeri tarehe {{1}}. Tafadhali hudhuria kama ulivyopangiwa. Ziara hii itasaidia kubaini hatua zinazofuata zinazofaa kwa huduma yako. Ikiwa hutaweza kuhudhuria, tafadhali wasiliana na mhudumu wako wa afya ili kupanga miadi nyingine.
```

| Variable | Sample |
|----------|--------|
| `{{1}}` | Saturday, 14 June 2026 |

---

### C2 — `afya_post_visit_via_neg`

Use exact text from study §12b (English + Kiswahili). Variables: `{{1}}` name, `{{2}}` follow-up appointment date.

---

### C3 — `afya_post_visit_ablation`

Use exact text from study §12c “Thermal Ablation successfully performed…”. Variables: `{{1}}` name, `{{2}}` 1-year follow-up date.

---

### C4 — `afya_post_visit_tx_postponed`

Use exact text from study §12c “Treatment postponed…”. Variables: `{{1}}` name, `{{2}}` new treatment date.

---

### C5 — `afya_missed_reschedule_confirm`

**Body (en):**

```
Thank you for choosing to continue your follow-up care. Rescheduling your appointment is an important step in protecting your health and preventing cervical cancer. Your new appointment is scheduled for {{1}}. We look forward to seeing you.
```

**Body (sw):**

```
Asante kwa kuchagua kuendelea na huduma yako ya ufuatiliaji. Kupanga upya miadi yako ni hatua muhimu katika kulinda afya yako na kuzuia saratani ya mlango wa kizazi. Miadi yako mpya imepangwa tarehe {{1}}. Tunatarajia kukuona.
```

---

## Batch D — Optional inbound ack (SMS hardcoded today)

Low priority if patients reply inside 24h WhatsApp session window.

| # | Template base | EN + SW |
|---|---------------|---------|
| D1 | `afya_lang_set_en` | 1 (EN only) |
| D2 | `afya_lang_set_sw` | 1 (SW only) |
| D3 | `afya_unsubscribe_ack` | 2 |

---

## Do NOT submit (deprecated vs current clinical logic)

| Template in old pack | Reason |
|---------------------|--------|
| `afya_checkup_via_neg` (§21) | Study §12b says 1-year; **system schedules 3y/5y** from VIA date by HIV status |
| `afya_checkup_hiv_hpvneg` / `afya_checkup_hiv_hpvpos` (§22–23) | Replaced by A3/A4 tied to VIA date |

**Clinical decision needed:** If the study’s **1-year** VIA-negative follow-up is final, code must change before using C2 or §21 templates.

---

## Summary counts

| Batch | New Mteja submissions | Action |
|-------|----------------------|--------|
| **A** | **8** templates | Submit to Mteja + update `mteja_whatsapp.php` mapping |
| **B** | **0** (use existing 102) | Wire PHP send + inbound handlers |
| **C** | **10** templates | Submit after clinical sign-off on §12–13 wording |
| **D** | **3–4** optional | Submit if WhatsApp must carry language/stop ack outside session window |

**Minimum new submissions to fix WhatsApp quality today: 8 (Batch A).**  
**Full study fidelity on WhatsApp: 8 + 10 = 18 new templates**, plus wiring Batch B from existing 102.

---

*June 2026 — Afya Rafiki / Nyeri Town Health Center pilot*
