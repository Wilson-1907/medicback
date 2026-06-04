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
| **2 — System & FAQ replies** | 9–33 | 50 |
| **3 — HPV counseling (positive)** | 34–49 | 32 |
| **Total** | **49** | **98** |

**Phase 1:** submit first (urgent go-live).  
**Phase 2:** appointments, HELP/DOCTOR, missed visit, check-ups, FAQ options 1–6.  
**Phase 3:** 16 counseling messages (official script) — batch 2 if Meta requires templates for each step.

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

## 27. Appointment updated (date/time changed)

| Field | English | Kiswahili |
|-------|---------|----------|
| **Template name** | `afya_appt_updated_en` | `afya_appt_updated_sw` |
| **Category** | UTILITY | UTILITY |
| **When sent** | Staff changes appointment date/time | Same |

**Body (en):**

```
Hello {{1}}, your appointment at Nyeri Town Health Center has been updated. New date/time: {{2}}. We are here for you. Reply HELP or DOCTOR if you need support.
```

**Body (sw):**

```
Habari {{1}}, miadi yako katika Nyeri Town Health Center imebadilishwa. Tarehe/Saa mpya: {{2}}. Tupo hapa kwako. Jibu HELP au DOCTOR ikiwa unahitaji msaada.
```

| Variable | Sample |
|----------|--------|
| `{{1}}` | Jane |
| `{{2}}` | Wednesday, 18 Jun 2026 2:00 PM |

---

## 28. FAQ reply — What is HPV? (option 1)

| Field | English | Kiswahili |
|-------|---------|----------|
| **Template name** | `afya_faq_hpv_en` | `afya_faq_hpv_sw` |
| **Category** | UTILITY | UTILITY |
| **When sent** | Patient sends 1 or asks about HPV | Same |

**Body (en):**

```
HPV is a common virus that can affect the cervix. Some types may cause cervical cancer if not treated early. Follow-up care helps protect your health.
```

**Body (sw):**

```
HPV ni virusi vya kawaida vinavyoweza kuathiri mlango wa kizazi. Aina zingine zinaweza kusababisha saratani ya mlango wa kizazi zisipotibiwa mapema. Huduma ya ufuatiliaji husaidia kulinda afya yako.
```

**Variables:** none

---

## 29. FAQ reply — Cervical cancer? (option 2)

| Field | English | Kiswahili |
|-------|---------|----------|
| **Template name** | `afya_faq_cancer_en` | `afya_faq_cancer_sw` |
| **Category** | UTILITY | UTILITY |

**Body (en):**

```
A positive HPV result does not mean you have cervical cancer. It means you have HPV virus. Additional follow-up care is needed. Please attend your clinic appointment.
```

**Body (sw):**

```
Majibu chanya ya HPV hayamaanishi kuwa una saratani. Inamaanisha una virusi vya HPV. Huduma zaidi ya ufuatiliaji inahitajika. Tafadhali hudhuria miadi yako ya kliniki.
```

**Variables:** none

---

## 30. FAQ reply — Can HPV be treated? (option 3)

| Field | English | Kiswahili |
|-------|---------|----------|
| **Template name** | `afya_faq_treat_en` | `afya_faq_treat_sw` |
| **Category** | UTILITY | UTILITY |

**Body (en):**

```
HPV infections often clear naturally. Follow-up care helps health providers monitor and manage any cervical changes early.
```

**Body (sw):**

```
Maambukizi ya HPV mara nyingi hupotea yenyewe. Huduma ya ufuatiliaji husaidia wahudumu wa afya kufuatilia na kutibu mabadiliko mapema.
```

**Variables:** none

---

## 31. FAQ reply — Appointments (option 4)

| Field | English | Kiswahili |
|-------|---------|----------|
| **Template name** | `afya_faq_appt_en` | `afya_faq_appt_sw` |
| **Category** | UTILITY | UTILITY |

**Body (en):**

```
For appointments, contact Nyeri Town Health Center or wait for your reminder message. Reply DOCTOR if you need urgent help.
```

**Body (sw):**

```
Kwa miadi, wasiliana na Nyeri Town Health Center au subiri kikumbusho. Jibu DOCTOR ikiwa unahitaji msaada wa haraka.
```

**Variables:** none

---

## 32. FAQ reply — Symptoms of HPV (option 5)

| Field | English | Kiswahili |
|-------|---------|----------|
| **Template name** | `afya_faq_symptoms_hpv_en` | `afya_faq_symptoms_hpv_sw` |
| **Category** | UTILITY | UTILITY |

**Body (en):**

```
Most people with HPV have no symptoms. Regular screening (HPV test, VIA) is important even when you feel well. Unusual bleeding, persistent pain, or unusual discharge — see a healthcare provider.
```

**Body (sw):**

```
Watu wengi hawana dalili za HPV. Uchunguzi wa mara kwa mara (HPV, VIA) ni muhimu hata ukiwa na afya njema. Damu isiyo ya kawaida, maumivu ya kudumu, au majimaji yasiyo ya kawaida — tembelea mhudumu wa afya.
```

**Variables:** none

---

## 33. FAQ reply — Symptoms of cervical cancer (option 6)

| Field | English | Kiswahili |
|-------|---------|----------|
| **Template name** | `afya_faq_symptoms_cc_en` | `afya_faq_symptoms_cc_sw` |
| **Category** | UTILITY | UTILITY |

**Body (en):**

```
Many women with early changes have no symptoms. Possible signs: bleeding after sex, between periods, or after menopause; unusual discharge; pelvic pain. These do not always mean cancer — visit a health facility.
```

**Body (sw):**

```
Wanawake wengi hawana dalili mapema. Dalili zinazoweza kuonekana: damu baada ya ngono au kati ya hedhi; majimaji yasiyo ya kawaida; maumivu ya kudumu. Si lazima iwe saratani — tembelea kituo cha afya.
```

**Variables:** none

---

## Phase 3 — HPV positive counseling (templates 34–49)

Official script from `afya_counseling_positive.php`. If Meta rejects a body over **1024 characters**, use session message for that step or ask Mteja to split (steps **42** and **49** are longest).

---

## 34. Counseling step 1 — HPV positive explained

| Field | English | Kiswahili |
|-------|---------|----------|
| **Template name** | `afya_counsel_pos_01_en` | `afya_counsel_pos_01_sw` |
| **Category** | UTILITY | UTILITY |
| **When sent** | Day 1 after positive HPV confirm | Same |

**Body (en):**

```
Your HPV test was positive. This means that HPV was detected in your sample. HPV is a very common infection, and about 8 out of every 10 sexually active people will get HPV at some point in their lives. Most HPV infections clear on their own without causing health problems. However, follow-up care is important to identify and treat any changes early and help prevent cervical cancer.
```

**Body (sw):**

```
Majibu yako ya HPV yalikuwa chanya (positive). Hii inamaanisha kuwa virusi vya HPV vimepatikana kwenye sampuli yako. HPV ni maambukizi ya kawaida sana, na takribani watu 8 kati ya 10 wanaoshiriki ngono hupata HPV wakati fulani maishani mwao. Maambukizi mengi ya HPV huisha yenyewe bila kusababisha matatizo ya kiafya. Hata hivyo, huduma ya ufuatiliaji ni muhimu ili kugundua na kutibu mabadiliko yoyote mapema na kusaidia kuzuia saratani ya mlango wa kizazi.
```

**Variables:** none

---

## 35. Counseling step 2 — Attend follow-up

| Field | English | Kiswahili |
|-------|---------|----------|
| **Template name** | `afya_counsel_pos_02_en` | `afya_counsel_pos_02_sw` |
| **Category** | UTILITY | UTILITY |

**Body (en):**

```
Follow-up care helps health providers detect and treat changes early before they become serious. Please attend your recommended clinic visit.
```

**Body (sw):**

```
Huduma ya ufuatiliaji husaidia wahudumu wa afya kugundua na kutibu mabadiliko mapema kabla hayajawa makubwa. Tafadhali hudhuria kliniki yako kama ulivyoelekezwa.
```

**Variables:** none

---

## 36. Counseling step 3 — Not cancer

| Field | English | Kiswahili |
|-------|---------|----------|
| **Template name** | `afya_counsel_pos_03_en` | `afya_counsel_pos_03_sw` |
| **Category** | UTILITY | UTILITY |

**Body (en):**

```
A positive HPV result does not mean you have cervical cancer. It means that more follow-up is needed to keep you healthy.
```

**Body (sw):**

```
Majibu chanya (Positive) ya HPV hayamaanishi kuwa una saratani ya mlango wa kizazi. Inamaanisha kuwa ufuatiliaji zaidi unahitajika ili kulinda afya yako.
```

**Variables:** none

---

## 37. Counseling step 4 — You can protect your health

| Field | English | Kiswahili |
|-------|---------|----------|
| **Template name** | `afya_counsel_pos_04_en` | `afya_counsel_pos_04_sw` |
| **Category** | UTILITY | UTILITY |

**Body (en):**

```
You have the ability to take important steps to protect your health. By attending your appointments and following the advice of your healthcare provider, you are helping to prevent cervical cancer.
```

**Body (sw):**

```
Una uwezo wa kuchukua hatua muhimu za kulinda afya yako. Kwa kuhudhuria miadi yako na kufuata ushaudi wa mhudumu wa afya, unasaidia kuzuia saratani ya mlango wa kizazi.
```

**Variables:** none

---

## 38. Counseling step 5 — Family support

| Field | English | Kiswahili |
|-------|---------|----------|
| **Template name** | `afya_counsel_pos_05_en` | `afya_counsel_pos_05_sw` |
| **Category** | UTILITY | UTILITY |

**Body (en):**

```
If you feel comfortable, consider sharing information about your appointment with a trusted family member or friend who can support you.
```

**Body (sw):**

```
Ikiwa unajisikia huru kufanya hivyo, unaweza kumshirikisha mwanafamilia au rafiki unayemwamini ili akusaidie kuhudhuria miadi yako.
```

**Variables:** none

---

## 39. Counseling step 6 — HPV may persist

| Field | English | Kiswahili |
|-------|---------|----------|
| **Template name** | `afya_counsel_pos_06_en` | `afya_counsel_pos_06_sw` |
| **Category** | UTILITY | UTILITY |

**Body (en):**

```
Most HPV infections clear naturally. However, some infections can persist and cause changes on the cervix over time. Attending follow-up appointments helps ensure that any changes are identified and managed early.
```

**Body (sw):**

```
Maambukizi mengi ya HPV huisha yenyewe. Hata hivyo, baadhi yanaweza kuendelea kwa muda mrefu na kusababisha mabadiliko kwenye mlango wa kizazi. Kuhudhuria miadi ya ufuatiliaji husaidia kuhakikisha kuwa mabadiliko yoyote yanagunduliwa na kushughulikiwa mapema.
```

**Variables:** none

---

## 40. Counseling step 7 — What is VIA

| Field | English | Kiswahili |
|-------|---------|----------|
| **Template name** | `afya_counsel_pos_07_en` | `afya_counsel_pos_07_sw` |
| **Category** | UTILITY | UTILITY |

**Body (en):**

```
Because your HPV test is positive, the next step is an examination called Visual Assessment with Acetic acid (VIA). During VIA, a trained healthcare provider applies a special vinegar solution to the cervix and looks for any abnormal areas that may need treatment. The procedure is simple, safe, and usually takes only a few minutes.
```

**Body (sw):**

```
Kwa kuwa majibu yako ya HPV ni chanya (Positive), hatua inayofuata ni uchunguzi unaoitwa Visual Assessment with Acetic acid (VIA). Wakati wa VIA, mhudumu wa afya hupaka dawa maalum ya siki kwenye mlango wa kizazi na kuangalia kama kuna sehemu zisizo za kawaida zinazohitaji matibabu. Uchunguzi huu ni salama na huchukua dakika chache tu.
```

**Variables:** none

---

## 41. Counseling step 8 — VIA results explained

| Field | English | Kiswahili |
|-------|---------|----------|
| **Template name** | `afya_counsel_pos_08_en` | `afya_counsel_pos_08_sw` |
| **Category** | UTILITY | UTILITY |

**Body (en):**

```
After VIA, your results may be: VIA Negative: No visible abnormal changes were found on the cervix. VIA Positive: Changes were seen on the cervix that may require treatment to prevent cervical cancer.
```

**Body (sw):**

```
Baada ya VIA, matokeo yako yanaweza kuwa: VIA Hasi (Negative): Hakuna mabadiliko yasiyo ya kawaida yaliyoonekana kwenye mlango wa kizazi. VIA Chanya (Positive): Mabadiliko yalionekana kwenye mlango wa kizazi ambayo yanaweza kuhitaji matibabu ili kuzuia saratani ya mlango wa kizazi.
```

**Variables:** none

---

## 42. Counseling step 9 — HPV+ / VIA negative

| Field | English | Kiswahili |
|-------|---------|----------|
| **Template name** | `afya_counsel_pos_09_en` | `afya_counsel_pos_09_sw` |
| **Category** | UTILITY | UTILITY |

**Body (en):**

```
Your HPV test can be positive, but your VIA result negative. This means that HPV was found, but no abnormal changes were seen on your cervix at this time. Most HPV infections clear on their own without causing health problems. Only a small number of women develop cervical changes that may require treatment, which is why regular follow-up screening is important. You do not need treatment at this time. Women living with HIV: Repeat HPV screening after 3 years. Women without HIV: Repeat HPV screening after 5 years. Please continue attending routine health check-ups as advised.
```

**Body (sw):**

```
Majibu yako ya HPV yanaweza kuwa chanya (positive), lakini matokeo ya VIA yakawa hasi (negative). Hii inamaanisha kuwa virusi vya HPV vilipatikana, lakini hakuna mabadiliko yasiyo ya kawaida yaliyoonekana kwenye mlango wa kizazi kwa sasa. Maambukizi mengi ya HPV huisha yenyewe bila kusababisha matatizo ya kiafya. Ni wanawake wachache tu hupata mabadiliko yanayohitaji matibabu, ndiyo sababu uchunguzi wa ufuatiliaji ni muhimu. Huhitaji matibabu kwa sasa. Wanawake wanaoishi na HIV: Rudia uchunguzi wa HPV baada ya miaka 3. Wanawake wasio na HIV: Rudia uchunguzi wa HPV baada ya miaka 5. Tafadhali endelea kuhudhuria huduma za afya kama ulivyoelekezwa.
```

**Variables:** none

---

## 43. Counseling step 10 — HPV+ / VIA positive

| Field | English | Kiswahili |
|-------|---------|----------|
| **Template name** | `afya_counsel_pos_10_en` | `afya_counsel_pos_10_sw` |
| **Category** | UTILITY | UTILITY |

**Body (en):**

```
Your HPV test can be positive, and your VIA result also positive. This means that HPV was detected and some changes were seen on your cervix that may require treatment. This does not mean that you have cervical cancer. Treatment at this stage helps remove the abnormal cells and prevents them from developing into cervical cancer in the future. If you are eligible for treatment, your healthcare provider may recommend Thermal Ablation, a simple procedure that uses heat to remove abnormal cervical cells and protect your health.
```

**Body (sw):**

```
Majibu yako ya HPV yanaweza kuwa chanya (positive), na matokeo ya VIA pia yakawa chanya. Hii inamaanisha kuwa virusi vya HPV vilipatikana na mabadiliko fulani yalionekana kwenye mlango wa kizazi ambayo yanaweza kuhitaji matibabu. Hii haimaanishi kuwa una saratani ya mlango wa kizazi. Matibabu katika hatua hii husaidia kuondoa seli zisizo za kawaida na kuzuia zisigeuke kuwa saratani baadaye. Ikiwa unafaa kupata matibabu, mhudumu wa afya anaweza kupendekeza Thermal Ablation, matibabu rahisi yanayotumia joto kuondoa seli zisizo za kawaida kwenye mlango wa kizazi na kusaidia kulinda afya yako.
```

**Variables:** none

---

## 44. Counseling step 11 — Thermal Ablation intro

| Field | English | Kiswahili |
|-------|---------|----------|
| **Template name** | `afya_counsel_pos_11_en` | `afya_counsel_pos_11_sw` |
| **Category** | UTILITY | UTILITY |

**Body (en):**

```
If your HPV test was positive and your VIA positive (result showed changes on the cervix), your healthcare provider may recommend Thermal Ablation. Thermal Ablation is a simple treatment that uses heat to remove abnormal cells on the cervix before they can develop into cervical cancer. The procedure usually takes only a few minutes and does not require admission to hospital. Early treatment is highly effective and helps keep your cervix healthy.
```

**Body (sw):**

```
Majibu yako ya HPV yakiwa chanya (positive) na matokeo ya VIA yaonyeshe mabadiliko kwenye mlango wa kizazi (VIA Positive), mhudumu wa afya anaweza kupendekeza Thermal Ablation. Thermal Ablation ni matibabu rahisi yanayotumia joto kuondoa seli zisizo za kawaida kwenye mlango wa kizazi kabla hazijageuka kuwa saratani ya mlango wa kizazi. Matibabu haya huchukua dakika chache tu na kwa kawaida hayahitaji kulazwa hospitalini. Matibabu ya mapema yanafanikiwa sana na husaidia kudumisha afya ya mlango wa kizazi.
```

**Variables:** none

---

## 45. Counseling step 12 — After ablation (normal symptoms)

| Field | English | Kiswahili |
|-------|---------|----------|
| **Template name** | `afya_counsel_pos_12_en` | `afya_counsel_pos_12_sw` |
| **Category** | UTILITY | UTILITY |

**Body (en):**

```
After Thermal Ablation, it is normal to experience: Mild watery discharge — use pad or panty liner. Mild lower abdominal discomfort. These symptoms usually improve within a few days to weeks (about 2–6 weeks).
```

**Body (sw):**

```
Baada ya Thermal Ablation, ni kawaida kupata: Majimaji kutoka ukeni — tumia pad au panty liner. Maumivu madogo chini ya tumbo. Dalili hizi kwa kawaida hupungua ndani ya siku au wiki chache (2–6 weeks).
```

**Variables:** none

---

## 46. Counseling step 13 — When to return urgently

| Field | English | Kiswahili |
|-------|---------|----------|
| **Template name** | `afya_counsel_pos_13_en` | `afya_counsel_pos_13_sw` |
| **Category** | UTILITY | UTILITY |

**Body (en):**

```
Please return to the health facility immediately if you experience: Heavy vaginal bleeding. Foul-smelling vaginal discharge. Severe lower abdominal pain. Fever or high body temperature. Any symptoms that concern you.
```

**Body (sw):**

```
Tafadhali rudi hospitalini mara moja ikiwa utapata: Kutokwa na damu nyingi ukeni. Majimaji yenye harufu mbaya kutoka ukeni. Maumivu makali chini ya tumbo. Homa au joto la mwili kuongezeka. Dalili nyingine zinazokusumbua.
```

**Variables:** none

---

## 47. Counseling step 14 — Healing after treatment

| Field | English | Kiswahili |
|-------|---------|----------|
| **Template name** | `afya_counsel_pos_14_en` | `afya_counsel_pos_14_sw` |
| **Category** | UTILITY | UTILITY |

**Body (en):**

```
To allow your cervix to heal: Avoid sexual intercourse for 4 weeks or as advised by your healthcare provider. Avoid inserting anything into the vagina during the healing period (e.g. tampons). Attend all scheduled follow-up appointments.
```

**Body (sw):**

```
Ili kuruhusu mlango wa kizazi kupona: Epuka kufanya ngono kwa wiki 4 au kama ulivyoelekezwa na mhudumu wa afya. Epuka kuingiza kitu chochote ukeni wakati wa kupona (k.m. tampons). Hudhuria miadi yote ya ufuatiliaji.
```

**Variables:** none

---

## 48. Counseling step 15 — Test of Cure (1 year)

| Field | English | Kiswahili |
|-------|---------|----------|
| **Template name** | `afya_counsel_pos_15_en` | `afya_counsel_pos_15_sw` |
| **Category** | UTILITY | UTILITY |

**Body (en):**

```
After Thermal Ablation, you should return for a Test of Cure (ToC) using HPV testing after 1 year. This helps confirm that treatment was successful and that your cervix remains healthy.
```

**Body (sw):**

```
Baada ya Thermal Ablation, unapaswa kurudi kwa kipimo cha kuthibitisha mafanikio ya matibabu (Test of Cure) kwa kutumia kipimo cha HPV baada ya mwaka 1. Hii husaidia kuthibitisha kuwa matibabu yalifanikiwa na afya ya mlango wa kizazi inaendelea kuwa nzuri.
```

**Variables:** none

---

## 49. Counseling step 16 — VIA pathways & referral

| Field | English | Kiswahili |
|-------|---------|----------|
| **Template name** | `afya_counsel_pos_16_en` | `afya_counsel_pos_16_sw` |
| **Category** | UTILITY | UTILITY |

**Body (en):**

```
As you prepare for your VIA examination, it is important to know that there are several possible results. Most women will have either a normal result (VIA negative) or changes that can be treated early (VIA positive). In some cases, the healthcare provider may see changes that require further assessment by a specialist. This is called a "suspicious for cancer" result. A suspicious result does not mean that you have cervical cancer. It simply means that more tests are needed. If this happens, you will be referred to Nyeri County Referral Hospital for specialist review and further care. Early assessment helps ensure that any health concerns are identified and managed as soon as possible.
```

**Body (sw):**

```
Unapojitayarisha kwa uchunguzi wa VIA, ni muhimu kujua kwamba kuna matokeo tofauti yanayoweza kupatikana. Wanawake wengi hupata matokeo ya kawaida (VIA negative) au mabadiliko ambayo yanaweza kutibiwa mapema (VIA positive). Wakati mwingine, mhudumu wa afya anaweza kuona mabadiliko yanayohitaji uchunguzi zaidi na daktari bingwa — matokeo ya "kuhisiwa kuwa na saratani" (suspicious for cancer). Matokeo haya hayamaanishi moja kwa moja kuwa una saratani. Yanamaanisha vipimo zaidi vinahitajika. Ikiwa hali hii itatokea, utapewa rufaa kwenda Hospitali ya Rufaa ya Kaunti ya Nyeri kwa uchunguzi na huduma zaidi. Uchunguzi wa mapema husaidia kuhakikisha matatizo yoyote ya afya yanagunduliwa na kushughulikiwa mapema iwezekanavyo.
```

**Variables:** none

---

## Checklist before submit

- [ ] Display name: **Nyeri Town Health Center** (or **Afya Rafiki** if approved)
- [ ] **Phase 1** (1–8) submitted first — EN + SW each
- [ ] **Phase 2** (9–33) — system, FAQ, appointment updated
- [ ] **Phase 3** (34–49) — counseling `afya_counsel_pos_01` … `16` (verify length for 42 & 49)
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

Phase 1 (urgent, 16): templates 1–8 (welcome, HPV results, reminders, VIA referral)

Phase 2 (50): templates 9–33 (appointments, HELP, DOCTOR, missed visit, check-ups,
FAQ 1–6, unlinked, fallback, consent, engagement)

Phase 3 (32, batch 2): templates 34–49 (afya_counsel_pos_01 … 16, EN + SW each)

Total: 49 template types × 2 languages = 98 submissions

Also import two FAQ knowledge bases:
hospital_portal/docs/MTEJA_KNOWLEDGE_BASE_EN.md (English)
hospital_portal/docs/MTEJA_KNOWLEDGE_BASE_SW.md (Kiswahili)

Category: UTILITY only. HPV cervical follow-up programme.
Reply with template IDs when approved.
```

---

## Template name index (all 49 — add `_en` or `_sw`)

| # | Template name |
|---|---------------|
| 1 | `afya_welcome` |
| 2 | `afya_hpv_neg_hivpos` |
| 3 | `afya_hpv_neg_hivneg` |
| 4 | `afya_hpv_positive` |
| 5 | `afya_appt_reminder_7d` |
| 6 | `afya_appt_reminder_3d` |
| 7 | `afya_appt_reminder_1d` |
| 8 | `afya_via_referral` |
| 9 | `afya_appt_booked` |
| 10 | `afya_help_menu` |
| 11 | `afya_escalation` |
| 12 | `afya_doctor_reason_ask` |
| 13 | `afya_doctor_reason_remind` |
| 14 | `afya_doctor_reason_ack` |
| 15 | `afya_doctor_already` |
| 16 | `afya_missed_appt` |
| 17 | `afya_missed_reschedule` |
| 18 | `afya_post_visit` |
| 19 | `afya_unlinked` |
| 20 | `afya_fallback` |
| 21 | `afya_checkup_via_neg` |
| 22 | `afya_checkup_hiv_hpvneg` |
| 23 | `afya_checkup_hiv_hpvpos` |
| 24 | `afya_checkup_generic` |
| 25 | `afya_consent_thanks` |
| 26 | `afya_engagement_tip` |
| 27 | `afya_appt_updated` |
| 28 | `afya_faq_hpv` |
| 29 | `afya_faq_cancer` |
| 30 | `afya_faq_treat` |
| 31 | `afya_faq_appt` |
| 32 | `afya_faq_symptoms_hpv` |
| 33 | `afya_faq_symptoms_cc` |
| 34–49 | `afya_counsel_pos_01` … `afya_counsel_pos_16` |

---

*Aligned with medicback message builders and Afya Rafiki official script — June 2026*
