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

| Phase | Templates | Count (EN+SW) |
|-------|-----------|---------------|
| **1 — Go-live** | 1–8 | 16 |
| **2 — System messages** | 9–26 | 36 |
| **3 — HPV counseling (positive)** | 27–42 | 32 |
| **Total** | | **84** |

**Phase 1:** submit first (urgent go-live).  
**Phase 2:** all automated replies (appointments, HELP, DOCTOR flow, missed visit, etc.).  
**Phase 3:** 16 counseling steps — submit if Mteja/Meta require templates outside 24h session.

**Knowledge base (FAQ):** `MTEJA_KNOWLEDGE_BASE_EN.md` + `MTEJA_KNOWLEDGE_BASE_SW.md` — two Mteja knowledge bases (one per language).

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

## 9. Appointment booked or updated

| Field | English | Kiswahili |
|-------|---------|----------|
| **Template name** | `afya_appt_booked_en` | `afya_appt_booked_sw` |
| **Category** | UTILITY | UTILITY |
| **When sent** | Staff books or updates appointment | Same |

**Body (en):**

```
Hello {{1}}, your appointment at Nyeri Town Health Center is booked. Date/Time: {{2}}. We are here for you. Reply HELP for health guidance or DOCTOR for direct hospital contact.
```

**Body (sw):**

```
Habari {{1}}, miadi yako katika Nyeri Town Health Center imepangwa. Tarehe/Saa: {{2}}. Tupo hapa kwako. Jibu HELP kwa mwongozo wa afya au DOCTOR kwa msaada wa hospitali.
```

| Variable | Sample |
|----------|--------|
| `{{1}}` | Jane |
| `{{2}}` | Monday, 15 Jun 2026 10:00 AM |

---

## 10. HELP menu (FAQ entry point)

| Field | English | Kiswahili |
|-------|---------|----------|
| **Template name** | `afya_help_menu_en` | `afya_help_menu_sw` |
| **Category** | UTILITY | UTILITY |
| **When sent** | Patient sends HELP / MENU | Same |

**Body (en):**

```
Afya Rafiki — options: 1) What is HPV? 2) Do I have cervical cancer? 3) Can HPV be treated? 4) Appointments / reschedule 5) Symptoms of HPV 6) Symptoms of cervical cancer 7) Speak to a provider (reply DOCTOR). Type your question or option number.
```

**Body (sw):**

```
Afya Rafiki — chaguo: 1) HPV ni nini? 2) Je, nina saratani ya mlango wa kizazi? 3) HPV inatibika? 4) Miadi / kupanga upya 5) Dalili za HPV 6) Dalili za saratani ya mlango wa kizazi 7) Ongea na mhudumu wa afya (DOCTOR). Andika swali lako au namba ya chaguo.
```

**Variables:** none

---

## 11. Escalation acknowledgment

| Field | English | Kiswahili |
|-------|---------|----------|
| **Template name** | `afya_escalation_en` | `afya_escalation_sw` |
| **Category** | UTILITY | UTILITY |
| **When sent** | Symptom/distress triggers escalation | Same |

**Body (en):**

```
Thank you for your question. A healthcare provider will be better able to assist you. Please contact your clinic or wait for a provider follow-up call.
```

**Body (sw):**

```
Asante kwa swali lako. Mhudumu wa afya ataweza kukusaidia vizuri zaidi. Tafadhali wasiliana na kliniki yako au subiri simu kutoka kwa mhudumu wa afya.
```

**Variables:** none

---

## 12. DOCTOR — ask reason

| Field | English | Kiswahili |
|-------|---------|----------|
| **Template name** | `afya_doctor_reason_ask_en` | `afya_doctor_reason_ask_sw` |
| **Category** | UTILITY | UTILITY |
| **When sent** | Patient sends DOCTOR / DAKTARI | Same |

**Body (en):**

```
Thanks for reaching out to Afya Rafiki. We would like to help you. Please reply in a short message with why you would like to speak with a health specialist (for example: pain, worry about your results, or booking a visit).
```

**Body (sw):**

```
Asante kwa kuwasiliana na Afya Rafiki. Tungependa kukusaidia. Tafadhali andika kwa ufupi kwa nini ungependa kuongea na mhudumu wa afya (mfano: maumivu, wasiwasi kuhusu matokeo, au kupanga miadi).
```

**Variables:** none

---

## 13. DOCTOR — reminder to send reason

| Field | English | Kiswahili |
|-------|---------|----------|
| **Template name** | `afya_doctor_reason_remind_en` | `afya_doctor_reason_remind_sw` |
| **Category** | UTILITY | UTILITY |

**Body (en):**

```
We are still waiting for a short message explaining why you would like to speak with a health specialist. Please reply in your own words — for example pain or a concern about your results.
```

**Body (sw):**

```
Tunasubiri ujumbe mfupi ukielezea kwa nini ungependa kuongea na mhudumu wa afya. Andika kwa maneno yako — mfano: maumivu au wasiwasi kuhusu matokeo.
```

**Variables:** none

---

## 14. DOCTOR — reason received

| Field | English | Kiswahili |
|-------|---------|----------|
| **Template name** | `afya_doctor_reason_ack_en` | `afya_doctor_reason_ack_sw` |
| **Category** | UTILITY | UTILITY |

**Body (en):**

```
Thank you — we have received your message. A health specialist will try to reach you soon. If this is an emergency, please go to the clinic right away.
```

**Body (sw):**

```
Asante — tumepokea ujumbe wako. Mhudumu wa afya atajaribu kuwasiliana nawe hivi karibuni. Ikiwa hali yako ni dharura, nenda kliniki mara moja.
```

**Variables:** none

---

## 15. DOCTOR — request already logged

| Field | English | Kiswahili |
|-------|---------|----------|
| **Template name** | `afya_doctor_already_en` | `afya_doctor_already_sw` |
| **Category** | UTILITY | UTILITY |

**Body (en):**

```
We already have your request to speak with a health specialist. Please wait for them to contact you.
```

**Body (sw):**

```
Tayari tumepokea ombi lako la kuongea na mhudumu wa afya. Tafadhali subiri simu au ujumbe kutoka kwao.
```

**Variables:** none

---

## 16. Missed appointment survey

| Field | English | Kiswahili |
|-------|---------|----------|
| **Template name** | `afya_missed_appt_en` | `afya_missed_appt_sw` |
| **Category** | UTILITY | UTILITY |
| **When sent** | Patient reports missed visit | Same |

**Body (en):**

```
Hello {{1}}, we noticed that you may have missed your scheduled follow-up appointment. Follow-up care is important to help protect your health. Could you tell us what prevented you from attending? Reply with the number: 1 Transport 2 Forgot 3 Fear or worry 4 Work or family 5 I was unwell 6 I attended but was not seen 7 Other reason
```

**Body (sw):**

```
Habari {{1}}, tumeona huenda hukuhudhuria miadi yako ya ufuatiliaji. Huduma ya ufuatiliaji ni muhimu kwa afya yako. Tafadhali tujulishe kilichokuzuia: 1 Usafiri 2 Nilisahau 3 Hofu au wasiwasi 4 Kazi au familia 5 Nilikuwa mgonjwa 6 Nilifika lakini sikuhudumiwa 7 Sababu nyingine
```

| Variable | Sample |
|----------|--------|
| `{{1}}` | Grace |

---

## 17. Missed appointment — reschedule offer

| Field | English | Kiswahili |
|-------|---------|----------|
| **Template name** | `afya_missed_reschedule_en` | `afya_missed_reschedule_sw` |
| **Category** | UTILITY | UTILITY |

**Body (en):**

```
Thank you for your response. We would like to help you continue your follow-up care. Would you like to reschedule at Nyeri Town Health Center? Reply: 1 YES - Reschedule 2 NO - I will contact the clinic 3 I need to speak with a healthcare provider
```

**Body (sw):**

```
Asante kwa majibu yako. Tungependa kukusaidia kuendelea na huduma yako. Je, ungependa kupanga upya miadi Nyeri Town Health Center? Jibu: 1 NDIO - Nipangie miadi 2 HAPANA - Nitawasiliana mwenyewe 3 Ningependa kuongea na mhudumu wa afya
```

**Variables:** none

---

## 18. Post-visit thank you

| Field | English | Kiswahili |
|-------|---------|----------|
| **Template name** | `afya_post_visit_en` | `afya_post_visit_sw` |
| **Category** | UTILITY | UTILITY |
| **When sent** | After patient attended follow-up | Same |

**Body (en):**

```
Hello {{1}}, thank you for attending your scheduled follow-up appointment. You have taken an important step in protecting your health. Please continue following your provider's advice and attend any future appointments. Afya Rafiki is proud to support you. Thank you for choosing Afya Rafiki.
```

**Body (sw):**

```
Habari {{1}}, asante kwa kuhudhuria miadi yako ya ufuatiliaji. Umechukua hatua muhimu katika kulinda afya yako. Endelea kufuata ushauri wa mhudumu wako na miadi zijazo. Afya Rafiki inajivunia kuwa sehemu ya safari yako ya afya. Asante kwa kutumia Afya Rafiki.
```

| Variable | Sample |
|----------|--------|
| `{{1}}` | Mary |

---

## 19. Unregistered number

| Field | English | Kiswahili |
|-------|---------|----------|
| **Template name** | `afya_unlinked_en` | `afya_unlinked_sw` |
| **Category** | UTILITY | UTILITY |
| **When sent** | Message from unknown phone | Same |

**Body (en):**

```
Hi. To get personalized health support, please register your number with Nyeri Town Health Center. If this is urgent, contact the hospital directly.
```

**Body (sw):**

```
Habari. Ili kupata msaada wa kiafya, tafadhali sajili nambari yako katika Nyeri Town Health Center. Ikiwa ni dharura, wasiliana na hospitali moja kwa moja.
```

**Variables:** none

---

## 20. AI / system fallback

| Field | English | Kiswahili |
|-------|---------|----------|
| **Template name** | `afya_fallback_en` | `afya_fallback_sw` |
| **Category** | UTILITY | UTILITY |
| **When sent** | AI unavailable; general reply | Same |

**Body (en):**

```
Thank you for your message. Afya Rafiki is here for you. Reply HELP for questions or DOCTOR for a provider.
```

**Body (sw):**

```
Asante kwa ujumbe wako. Afya Rafiki iko hapa kukusaidia. Jibu HELP kwa maswali au DOCTOR kwa mhudumu wa afya.
```

**Variables:** none

---

## 21. Scheduled check-up — VIA negative (1 year)

| Field | English | Kiswahili |
|-------|---------|----------|
| **Template name** | `afya_checkup_via_neg_en` | `afya_checkup_via_neg_sw` |
| **Category** | UTILITY | UTILITY |

**Body (en):**

```
Hello {{1}}, your VIA result was negative. Please return to Nyeri Town Health Center for your annual check-up on {{2}}.
```

**Body (sw):**

```
Habari {{1}}, matokeo yako ya VIA yalikuwa hasi. Tafadhali rudi Nyeri Town Health Center kwa uchunguzi wa mwaka tarehe {{2}}.
```

| Variable | Sample |
|----------|--------|
| `{{1}}` | Anne |
| `{{2}}` | 2027-06-15 |

---

## 22. Scheduled check-up — HIV+ / HPV negative (5-year path)

| Field | English | Kiswahili |
|-------|---------|----------|
| **Template name** | `afya_checkup_hiv_hpvneg_en` | `afya_checkup_hiv_hpvneg_sw` |
| **Category** | UTILITY | UTILITY |

**Body (en):**

```
Hello {{1}}, for follow-up (HIV positive, HPV negative), please return to Nyeri Town Health Center for HPV screening on {{2}}.
```

**Body (sw):**

```
Habari {{1}}, kwa ufuatiliaji (VVU chanya, HPV hasi), tafadhali rudi kliniki tarehe {{2}} kwa kipimo cha HPV.
```

| Variable | Sample |
|----------|--------|
| `{{1}}` | Jane |
| `{{2}}` | 2031-03-10 |

---

## 23. Scheduled check-up — HIV+ / HPV positive (3-year path)

| Field | English | Kiswahili |
|-------|---------|----------|
| **Template name** | `afya_checkup_hiv_hpvpos_en` | `afya_checkup_hiv_hpvpos_sw` |
| **Category** | UTILITY | UTILITY |

**Body (en):**

```
Hello {{1}}, for follow-up (HIV positive, HPV positive), please return to Nyeri Town Health Center for HPV screening on {{2}}.
```

**Body (sw):**

```
Habari {{1}}, kwa ufuatiliaji (VVU chanya, HPV chanya), tafadhali rudi kliniki tarehe {{2}} kwa kipimo cha HPV.
```

| Variable | Sample |
|----------|--------|
| `{{1}}` | Grace |
| `{{2}}` | 2029-03-10 |

---

## 24. Generic scheduled check-up reminder

| Field | English | Kiswahili |
|-------|---------|----------|
| **Template name** | `afya_checkup_generic_en` | `afya_checkup_generic_sw` |
| **Category** | UTILITY | UTILITY |

**Body (en):**

```
Hello {{1}}, please return to Nyeri Town Health Center for a check-up on {{2}}.
```

**Body (sw):**

```
Habari {{1}}, tafadhali rudi Nyeri Town Health Center kwa uchunguzi tarehe {{2}}.
```

| Variable | Sample |
|----------|--------|
| `{{1}}` | Mary |
| `{{2}}` | 2028-01-20 |

---

## 25. Consent thank you (optional)

| Field | English | Kiswahili |
|-------|---------|----------|
| **Template name** | `afya_consent_thanks_en` | `afya_consent_thanks_sw` |
| **Category** | UTILITY | UTILITY |
| **When sent** | Legacy consent flow only | Same |

**Body (en):**

```
Thank you {{1}}. We appreciate you agreeing to receive messages from Afya Rafiki. Your HPV screening results will be sent here when confirmed by the clinic. We are here to support you.
```

**Body (sw):**

```
Asante sana {{1}}. Tunashukuru kwa kukubali kupokea ujumbe kutoka Afya Rafiki. Matokeo yako ya HPV yatatumwa hapa mara tu yatakapothibitishwa na kliniki. Tuko hapa kukusaidia.
```

| Variable | Sample |
|----------|--------|
| `{{1}}` | Jane |

---

## 26. Engagement / health tip (optional)

| Field | English | Kiswahili |
|-------|---------|----------|
| **Template name** | `afya_engagement_tip_en` | `afya_engagement_tip_sw` |
| **Category** | UTILITY | UTILITY |
| **When sent** | Periodic encouragement (cron) | Same |

**Body (en):**

```
Your health matters. You have taken a good step by following up on your health. Reply HELP if you have a question or DOCTOR to speak with a provider. Afya Rafiki — Nyeri Town Health Center.
```

**Body (sw):**

```
Afya yako ni muhimu. Umechukua hatua nzuri kwa kufuatilia afya yako. Jibu HELP kwa maswali au DOCTOR kwa mhudumu wa afya. Afya Rafiki — Nyeri Town Health Center.
```

**Variables:** none

---

## Phase 3 — HPV positive counseling (templates 27–42)

**Naming:** `afya_counsel_pos_01_en` … `afya_counsel_pos_16_en` (and `_sw`).  
**Source code:** `hospital_portal/afya_counseling_positive.php`.  
**Note:** If Meta rejects a body over **1024 characters**, ask Mteja to split into two templates or use session messages for that step only.

### 27–42. Counseling steps 1–16

For each step `NN` (01–16), create **two** templates (`_en` / `_sw`) with the bodies below.

#### Step 01 — `afya_counsel_pos_01`

**EN:** Your HPV test was positive. This means that HPV was detected in your sample. HPV is a very common infection, and about 8 out of every 10 sexually active people will get HPV at some point in their lives. Most HPV infections clear on their own without causing health problems. However, follow-up care is important to identify and treat any changes early and help prevent cervical cancer.

**SW:** Majibu yako ya HPV yalikuwa chanya (positive). Hii inamaanisha kuwa virusi vya HPV vimepatikana kwenye sampuli yako. HPV ni maambukizi ya kawaida sana, na takribani watu 8 kati ya 10 wanaoshiriki ngono hupata HPV wakati fulani maishani mwao. Maambukizi mengi ya HPV huisha yenyewe bila kusababisha matatizo ya kiafya. Hata hivyo, huduma ya ufuatiliaji ni muhimu ili kugundua na kutibu mabadiliko yoyote mapema na kusaidia kuzuia saratani ya mlango wa kizazi.

#### Step 02 — `afya_counsel_pos_02`

**EN:** Follow-up care helps health providers detect and treat changes early before they become serious. Please attend your recommended clinic visit.

**SW:** Huduma ya ufuatiliaji husaidia wahudumu wa afya kugundua na kutibu mabadiliko mapema kabla hayajawa makubwa. Tafadhali hudhuria kliniki yako kama ulivyoelekezwa.

#### Step 03 — `afya_counsel_pos_03`

**EN:** A positive HPV result does not mean you have cervical cancer. It means that more follow-up is needed to keep you healthy.

**SW:** Majibu chanya (Positive) ya HPV hayamaanishi kuwa una saratani ya mlango wa kizazi. Inamaanisha kuwa ufuatiliaji zaidi unahitajika ili kulinda afya yako.

#### Step 04 — `afya_counsel_pos_04`

**EN:** You have the ability to take important steps to protect your health. By attending your appointments and following the advice of your healthcare provider, you are helping to prevent cervical cancer.

**SW:** Una uwezo wa kuchukua hatua muhimu za kulinda afya yako. Kwa kuhudhuria miadi yako na kufuata ushaudi wa mhudumu wa afya, unasaidia kuzuia saratani ya mlango wa kizazi.

#### Step 05 — `afya_counsel_pos_05`

**EN:** If you feel comfortable, consider sharing information about your appointment with a trusted family member or friend who can support you.

**SW:** Ikiwa unajisikia huru kufanya hivyo, unaweza kumshirikisha mwanafamilia au rafiki unayemwamini ili akusaidie kuhudhuria miadi yako.

#### Step 06 — `afya_counsel_pos_06`

**EN:** Most HPV infections clear naturally. However, some infections can persist and cause changes on the cervix over time. Attending follow-up appointments helps ensure that any changes are identified and managed early.

**SW:** Maambukizi mengi ya HPV huisha yenyewe. Hata hivyo, baadhi yanaweza kuendelea kwa muda mrefu na kusababisha mabadiliko kwenye mlango wa kizazi. Kuhudhuria miadi ya ufuatiliaji husaidia kuhakikisha kuwa mabadiliko yoyote yanagunduliwa na kushughulikiwa mapema.

#### Step 07 — `afya_counsel_pos_07`

**EN:** Because your HPV test is positive, the next step is an examination called Visual Assessment with Acetic acid (VIA). During VIA, a trained healthcare provider applies a special vinegar solution to the cervix and looks for any abnormal areas that may need treatment. The procedure is simple, safe, and usually takes only a few minutes.

**SW:** Kwa kuwa majibu yako ya HPV ni chanya (Positive), hatua inayofuata ni uchunguzi unaoitwa Visual Assessment with Acetic acid (VIA). Wakati wa VIA, mhudumu wa afya hupaka dawa maalum ya siki kwenye mlango wa kizazi na kuangalia kama kuna sehemu zisizo za kawaida zinazohitaji matibabu. Uchunguzi huu ni salama na huchukua dakika chache tu.

#### Step 08 — `afya_counsel_pos_08`

**EN:** After VIA, your results may be: VIA Negative: No visible abnormal changes were found on the cervix. VIA Positive: Changes were seen on the cervix that may require treatment to prevent cervical cancer.

**SW:** Baada ya VIA, matokeo yako yanaweza kuwa: VIA Hasi (Negative): Hakuna mabadiliko yasiyo ya kawaida yaliyoonekana. VIA Chanya (Positive): Mabadiliko yalionekana ambayo yanaweza kuhitaji matibabu ili kuzuia saratani ya mlango wa kizazi.

#### Step 09 — `afya_counsel_pos_09` *(long — verify length with Mteja)*

**EN:** Your HPV test can be positive, but your VIA result negative. This means HPV was found, but no abnormal changes were seen on your cervix at this time. Most HPV infections clear on their own. You do not need treatment at this time. Women living with HIV: repeat HPV screening after 3 years. Women without HIV: repeat after 5 years. Please continue routine check-ups as advised.

**SW:** Majibu yako ya HPV yanaweza kuwa chanya, lakini VIA hasi. HPV vilipatikana lakini hakuna mabadiliko yasiyo ya kawaida kwa sasa. Maambukizi mengi huisha yenyewe. Huhitaji matibabu sasa. Wanawake wenye VVU: rudia HPV baada ya miaka 3. Wasio na VVU: baada ya miaka 5. Endelea kuhudhuria huduma za afya kama ulivyoelekezwa.

#### Step 10 — `afya_counsel_pos_10`

**EN:** Your HPV and VIA results can both be positive. This means HPV was detected and some changes were seen on your cervix that may require treatment. This does not mean cervical cancer. Treatment at this stage helps remove abnormal cells and prevent cancer. Your provider may recommend Thermal Ablation.

**SW:** HPV na VIA vinaweza kuwa chanya. HPV vilipatikana na mabadiliko yalionekana kwenye mlango wa kizazi. Hii si saratani. Matibabu husaidia kuondoa seli zisizo za kawaida. Mhudumu wa afya anaweza kupendekeza Thermal Ablation.

#### Step 11 — `afya_counsel_pos_11`

**EN:** If your HPV and VIA are positive, your healthcare provider may recommend Thermal Ablation. It uses heat to remove abnormal cervical cells before they can develop into cancer. The procedure usually takes only a few minutes and does not require hospital admission. Early treatment is highly effective.

**SW:** Ikiwa HPV na VIA ni chanya, mhudumu anaweza kupendekeza Thermal Ablation. Matibabu yanatumia joto kuondoa seli zisizo za kawaida kabla hazijageuka saratani. Huchukua dakika chache na kwa kawaida hayahitaji kulazwa. Matibabu ya mapema yanafanikiwa.

#### Step 12 — `afya_counsel_pos_12`

**EN:** After Thermal Ablation, it is normal to experience mild watery discharge (use a pad) and mild lower abdominal discomfort. These symptoms usually improve within a few days to weeks (about 2–6 weeks).

**SW:** Baada ya Thermal Ablation, ni kawaida kupata majimaji kidogo (tumia pad) na maumivu madogo chini ya tumbo. Dalili hizi hupungua ndani ya siku au wiki chache (wiki 2–6).

#### Step 13 — `afya_counsel_pos_13`

**EN:** Please return to the health facility immediately if you experience: heavy vaginal bleeding, foul-smelling discharge, severe lower abdominal pain, fever, or any symptoms that concern you.

**SW:** Rudi hospitalini mara moja ikiwa utapata: damu nyingi ukeni, majimaji yenye harufu mbaya, maumivu makali chini ya tumbo, homa, au dalili nyingine zinazokusumbua.

#### Step 14 — `afya_counsel_pos_14`

**EN:** To allow your cervix to heal after treatment: avoid sexual intercourse for 4 weeks or as advised; avoid inserting anything into the vagina during healing (e.g. tampons); attend all scheduled follow-up appointments.

**SW:** Ili mlango wa kizazi upone: epuka ngono kwa wiki 4 au kama ulivyoelekezwa; epuka kuingiza kitu ukeni wakati wa kupona; hudhuria miadi zote za ufuatiliaji.

#### Step 15 — `afya_counsel_pos_15`

**EN:** After Thermal Ablation, return for a Test of Cure (ToC) using HPV testing after 1 year. This helps confirm treatment was successful and your cervix remains healthy.

**SW:** Baada ya Thermal Ablation, rudi kwa Test of Cure (ToC) kwa kipimo cha HPV baada ya mwaka 1. Hii husaidia kuthibitisha matibabu yalifanikiwa na afya ya mlango wa kizazi inaendelea kuwa nzuri.

#### Step 16 — `afya_counsel_pos_16` *(long — verify length with Mteja)*

**EN:** When preparing for VIA, most women have a normal result (VIA negative) or changes that can be treated early (VIA positive). Sometimes a "suspicious for cancer" result requires specialist assessment — this does not mean you have cancer. You may be referred to Nyeri County Referral Hospital. Early assessment helps protect your health.

**SW:** Unapojiandaa kwa VIA, wanawake wengi hupata matokeo ya kawaida (VIA hasi) au mabadiliko yanayoweza kutibiwa mapema (VIA chanya). Wakati mwingine matokeo ya "kuhisiwa kuwa na saratani" yanahitaji daktari bingwa — si saratani moja kwa moja. Unaweza pelekwa Hospitali ya Rufaa ya Kaunti ya Nyeri. Uchunguzi wa mapema unalinda afya yako.

---

## Checklist before submit

- [ ] Display name: **Nyeri Town Health Center** (or **Afya Rafiki** if approved)
- [ ] **Phase 1** (1–8) submitted first — EN + SW each
- [ ] **Phase 2** (9–26) — system messages
- [ ] **Phase 3** (27–42) — counseling `afya_counsel_pos_01` … `16` (or use session messages for long steps 9 & 16)
- [ ] **Knowledge base:** import `MTEJA_KNOWLEDGE_BASE_EN.md` and `MTEJA_KNOWLEDGE_BASE_SW.md`
- [ ] Category **UTILITY** for all templates
- [ ] Sample values for every `{{1}}` / `{{2}}`
- [ ] Mteja confirms template names + IDs when **APPROVED**

---

## After approval — what we need back from Mteja

| Item | Example |
|------|---------|
| Template name | `afya_welcome_en` |
| Meta / Mteja template ID | (they provide) |
| Status | APPROVED |
| Language | en / sw |

We will map IDs in medicback to send via **template name + variables** (code update after approval).

---

## Quick copy for Mteja email

```
Subject: Full WhatsApp pack — Nyeri Town Health Center (Afya Rafiki)

Please submit UTILITY templates (English + Kiswahili) from our document:
hospital_portal/docs/WHATSAPP_MESSAGE_TEMPLATES.md

Phase 1 (urgent, 16 templates): afya_welcome_*, afya_hpv_neg_*, afya_hpv_positive_*,
afya_appt_reminder_7d/3d/1d_*, afya_via_referral_*

Phase 2 (36 templates): afya_appt_booked_*, afya_help_menu_*, afya_escalation_*,
afya_doctor_* (4), afya_missed_* (2), afya_post_visit_*, afya_unlinked_*,
afya_fallback_*, afya_checkup_* (4), afya_consent_thanks_*, afya_engagement_tip_*

Phase 3 (32 templates, optional batch 2): afya_counsel_pos_01_en … afya_counsel_pos_16_sw

Also import two FAQ knowledge bases:
hospital_portal/docs/MTEJA_KNOWLEDGE_BASE_EN.md (English)
hospital_portal/docs/MTEJA_KNOWLEDGE_BASE_SW.md (Kiswahili)

Category: UTILITY only. HPV cervical follow-up programme.
Reply with template IDs when approved.
```

---

## Template name index (all 84)

| # | Name pattern (add `_en` / `_sw`) |
|---|----------------------------------|
| 1–8 | `afya_welcome`, `afya_hpv_neg_hivpos`, `afya_hpv_neg_hivneg`, `afya_hpv_positive`, `afya_appt_reminder_7d`, `afya_appt_reminder_3d`, `afya_appt_reminder_1d`, `afya_via_referral` |
| 9–26 | `afya_appt_booked`, `afya_help_menu`, `afya_escalation`, `afya_doctor_reason_ask`, `afya_doctor_reason_remind`, `afya_doctor_reason_ack`, `afya_doctor_already`, `afya_missed_appt`, `afya_missed_reschedule`, `afya_post_visit`, `afya_unlinked`, `afya_fallback`, `afya_checkup_via_neg`, `afya_checkup_hiv_hpvneg`, `afya_checkup_hiv_hpvpos`, `afya_checkup_generic`, `afya_consent_thanks`, `afya_engagement_tip` |
| 27–42 | `afya_counsel_pos_01` … `afya_counsel_pos_16` |

---

*Aligned with medicback message builders and Afya Rafiki official script — June 2026*
