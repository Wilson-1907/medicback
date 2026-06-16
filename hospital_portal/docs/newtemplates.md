# New Mteja WhatsApp templates — `afya_nav_*` batch

**Purpose:** Templates **different from your existing 102**. Submit to Mteja as **UTILITY**.

**Naming rule:** Each language is a **separate template name** — e.g. `afya_nav_edu_01_en` (English) and `afya_nav_edu_01_sw` (Kiswahili).  
**Code:** `mteja_nav_template_id('afya_nav_edu_01', 'en')` → `afya_nav_edu_01_en`

---

## Full template name index (28 templates)

| # | English template name | Kiswahili template name | When sent |
|---|------------------------|-------------------------|-----------|
| 1 | `afya_nav_edu_01_en` | `afya_nav_edu_01_sw` | Counseling drip msg 1 |
| 2 | `afya_nav_edu_02_en` | `afya_nav_edu_02_sw` | Counseling drip msg 2 |
| 3 | `afya_nav_edu_03_en` | `afya_nav_edu_03_sw` | Counseling drip msg 3 |
| 4 | `afya_nav_edu_04_en` | `afya_nav_edu_04_sw` | Counseling drip msg 4 |
| 5 | `afya_nav_edu_05_en` | `afya_nav_edu_05_sw` | Counseling drip msg 5 |
| 6 | `afya_nav_edu_06_en` | `afya_nav_edu_06_sw` | Counseling drip msg 6 |
| 7 | `afya_nav_edu_07_en` | `afya_nav_edu_07_sw` | Counseling drip msg 7 |
| 8 | `afya_nav_edu_08_en` | `afya_nav_edu_08_sw` | Counseling drip msg 8 |
| 9 | `afya_nav_edu_09_en` | `afya_nav_edu_09_sw` | Counseling drip msg 9 |
| 10 | `afya_nav_edu_10_en` | `afya_nav_edu_10_sw` | Counseling drip msg 10 |
| 11 | `afya_nav_via_neg_result_en` | `afya_nav_via_neg_result_sw` | VIA negative §12b (vars `{{1}}` `{{2}}`) |
| 12 | `afya_nav_checkup_1y_en` | `afya_nav_checkup_1y_sw` | 1-year checkup reminder |
| 13 | `afya_nav_missed_offer_en` | `afya_nav_missed_offer_sw` | Missed survey reply §13b |
| 14 | `afya_nav_missed_confirm_en` | `afya_nav_missed_confirm_sw` | Reschedule booked §13c |

Missed **survey** (§13) still uses **`afya_missed_appt_en`** / **`afya_missed_appt_sw`** from your 102 pack.

---

## Pre-VIA counseling (study messages 1–10)

**Schedule:** HPV+ **recorded** arms the pathway; on **confirm & notify** msg 1 sends **immediately** after the result SMS, then msg 2 **+2 min**, msg 3 **+1 h**, msgs 4–10 **+21 h** each (~**6.3 days**, within **6.5 day** limit). Stops when VIA is recorded. Cron: `process_due_scheduled_messages` every **5–15 min** for msgs 2–10.

### `afya_nav_edu_01_en` — Understanding HPV

```
Your HPV test was positive. This means that HPV was detected in your sample. HPV is a very common infection, and about 8 out of every 10 sexually active people will get HPV at some point in their lives. Most HPV infections clear on their own without causing health problems. However, follow-up care is important to identify and treat any changes early and help prevent cervical cancer.
```

### `afya_nav_edu_01_sw`

```
Majibu yako ya HPV yalikuwa chanya (positive). Hii inamaanisha kuwa virusi vya HPV vimepatikana kwenye sampuli yako. HPV ni maambukizi ya kawaida sana, na takribani watu 8 kati ya 10 wanaoshiriki ngono hupata HPV wakati fulani maishani mwao. Maambukizi mengi ya HPV huisha yenyewe bila kusababisha matatizo ya kiafya. Hata hivyo, huduma ya ufuatiliaji ni muhimu ili kugundua na kutibu mabadiliko yoyote mapema na kusaidia kuzuia saratani ya mlango wa kizazi.
```

### `afya_nav_edu_02_en` — Importance of follow-up

```
Follow-up care helps health providers detect and treat changes early before they become serious. Please attend your recommended clinic visit.
```

### `afya_nav_edu_02_sw`

```
Huduma ya ufuatiliaji husaidia wahudumu wa afya kugundua na kutibu mabadiliko mapema kabla hayajawa makubwa. Tafadhali hudhuria kliniki yako kama ulivyoelekezwa.
```

### `afya_nav_edu_03_en` — Reducing fear

```
A positive HPV result does not mean you have cervical cancer. It means more follow-up is needed to help detect abnormal cervical changes early.
```

### `afya_nav_edu_03_sw`

```
Majibu chanya (Positive) ya HPV hayamaanishi kuwa una saratani ya mlango wa kizazi. Inamaanisha kuwa ufuatiliaji zaidi unahitajika ili kulinda afya yako.
```

### `afya_nav_edu_04_en` — Taking action

```
You have the ability to take important steps to protect your health. By attending your appointments and following the advice of your healthcare provider, you are helping to prevent cervical cancer.
```

### `afya_nav_edu_04_sw`

```
Una uwezo wa kuchukua hatua muhimu za kulinda afya yako. Kwa kuhudhuria miadi yako na kufuata ushauri wa mhudumu wa afya, unasaidia kuzuia saratani ya mlango wa kizazi.
```

### `afya_nav_edu_05_en` — Social support

```
If you feel comfortable, consider sharing information about your appointment with a trusted family member or friend who can support you.
```

### `afya_nav_edu_05_sw`

```
Ikiwa unajisikia huru kufanya hivyo, unaweza kumshirikisha mwanafamilia au rafiki unayemwamini ili akusaidie kuhudhuria miadi yako.
```

### `afya_nav_edu_06_en` — Risk of non-attendance

```
Most HPV infections clear naturally. However, some infections can persist and cause changes on the cervix over time. Attending follow-up appointments helps ensure that any changes are identified and managed early.
```

### `afya_nav_edu_06_sw`

```
Maambukizi mengi ya HPV huisha yenyewe. Hata hivyo, baadhi yanaweza kuendelea kwa muda mrefu na kusababisha mabadiliko kwenye mlango wa kizazi. Kuhudhuria miadi ya ufuatiliaji husaidia kuhakikisha kuwa mabadiliko yoyote yanagunduliwa na kushughulikiwa mapema.
```

### `afya_nav_edu_07_en` — What happens next (VIA)

```
Because your HPV test is positive, the next step is an examination called Visual Inspection with Acetic acid (VIA). During VIA, a trained healthcare provider applies a special solution to the cervix and looks for any abnormal areas that may need treatment. The procedure is simple, safe, and usually takes only a few minutes.
```

### `afya_nav_edu_07_sw`

```
Kwa kuwa majibu yako ya HPV ni chanya (Positive), hatua inayofuata ni uchunguzi unaoitwa Visual Assessment with Acetic acid (VIA). Wakati wa VIA, mhudumu wa afya hupaka dawa maalum ya siki kwenye mlango wa kizazi na kuangalia kama kuna sehemu zisizo za kawaida zinazohitaji matibabu. Uchunguzi huu ni salama na huchukua dakika chache tu.
```

### `afya_nav_edu_08_en` — Understanding VIA results

```
After VIA, your results will be given immediately after the test. The results may be: VIA Negative: No visible abnormal changes were found on the cervix. VIA Positive: Changes were seen on the cervix that may require treatment to prevent cervical cancer. Findings requiring further assessment: Sometimes, the healthcare provider may see changes on the cervix that need a closer assessment by a specialist. This does not necessarily mean that you have cervical cancer. You may be referred for additional tests or review to help determine the most appropriate care. Your healthcare provider will explain your results and guide you on the next steps.
```

### `afya_nav_edu_08_sw`

```
Baada ya VIA, matokeo yako yanaweza kuwa: VIA Hasi (Negative): Hakuna mabadiliko yasiyo ya kawaida yaliyoonekana kwenye mlango wa kizazi. VIA Chanya (Positive): Mabadiliko yalionekana kwenye mlango wa kizazi ambayo yanaweza kuhitaji matibabu ili kuzuia saratani ya mlango wa kizazi. Matokeo yanayohitaji uchunguzi zaidi: Wakati mwingine mhudumu wa afya anaweza kuona mabadiliko kwenye mlango wa kizazi yanayohitaji uchunguzi wa karibu zaidi na daktari bingwa. Hii haimaanishi moja kwa moja kuwa una saratani ya mlango wa kizazi. Unaweza kupewa rufaa kwa vipimo zaidi au uchunguzi wa ziada ili kubaini huduma inayofaa zaidi kwako. Mhudumu wako wa afya atakueleza matokeo yako na kukuelekeza hatua zinazofuata.
```

### `afya_nav_edu_09_en` — If VIA is negative (education)

```
Your HPV test can be positive, but your VIA result negative. This means that HPV was found, but no abnormal changes were seen on your cervix at this time. Most HPV infections clear on their own without causing health problems. Only a small number of women develop cervical changes that may require treatment, which is why regular follow-up screening is important. You will not need treatment at this time. You are advised to return for follow up after one year. Please continue attending routine health check-ups as advised.
```

### `afya_nav_edu_09_sw`

```
Majibu yako ya HPV yanaweza kuwa chanya (positive), lakini matokeo ya VIA yakawa hasi (negative). Hii inamaanisha kuwa virusi vya HPV vilipatikana, lakini hakuna mabadiliko yasiyo ya kawaida yaliyoonekana kwenye mlango wa kizazi kwa sasa. Maambukizi mengi ya HPV huisha yenyewe bila kusababisha matatizo ya kiafya. Ni wanawake wachache tu hupata mabadiliko yanayohitaji matibabu, ndiyo sababu uchunguzi wa ufuatiliaji ni muhimu. Hutahitaji matibabu matokea ya VIA yakiwa Hasi (Negative). Utashauriwa kurudi kwa uchunguzi wa ufuatiliaji baada ya mwaka mmoja. Tafadhali endelea kuhudhuria huduma za afya kama ulivyoelekezwa.
```

### `afya_nav_edu_10_en` — If VIA is positive (education)

```
Your HPV test can be positive, and your VIA result also positive. This means that HPV was detected and some changes were seen on your cervix that may require treatment. This does not mean that you have cervical cancer. Treatment at this stage helps remove the abnormal cells and prevents them from developing into cervical cancer in the future. If you are eligible for treatment, your healthcare provider may recommend Thermal Ablation, a simple procedure that removes abnormal cervical cells and protects you from developing cervical cancer.
```

### `afya_nav_edu_10_sw`

```
Majibu yako ya HPV yanaweza kuwa chanya (positive), na matokeo ya VIA pia yakawa chanya. Hii inamaanisha kuwa virusi vya HPV vilipatikana na mabadiliko fulani yalionekana kwenye mlango wa kizazi ambayo yanaweza kuhitaji matibabu. Hii haimaanishi kuwa una saratani ya mlango wa kizazi. Matibabu katika hatua hii husaidia kuondoa seli zisizo za kawaida na kuzuia zisigeuke kuwa saratani baadaye. Ikiwa unafaa kupata matibabu, mhudumu wa afya anaweza kupendekeza Thermal Ablation, matibabu rahisi yanayo ondoa seli zisizo za kawaida kwenye mlango wa kizazi na kusaidia kulinda afya yako.
```

---

## VIA negative result — study §12b

### `afya_nav_via_neg_result_en`

Variables: `{{1}}` = first name, `{{2}}` = appointment date

```
Hello {{1}}, your HPV test was positive, but your VIA examination did not show any changes on the cervix that require treatment at this time. This is good news. Although HPV was detected, no visible changes were seen on your cervix. Most HPV infections clear naturally without causing health problems. However, regular follow-up is important because a small number of HPV infections may persist and cause changes over time. You do not need treatment at this time. It is important that you return for a repeat HPV test after 1 year to monitor your cervical health and ensure that any future changes are detected early. Your follow-up appointment is scheduled for: Date: {{2}} If you experience unusual symptoms such as abnormal vaginal bleeding, foul-smelling discharge, or persistent lower abdominal pain, please visit a health facility for assessment. Thank you for choosing Afya Rafiki. We are here to support you.
```

### `afya_nav_via_neg_result_sw`

```
Habari {{1}}, majibu yako ya HPV yalikuwa chanya (positive), lakini uchunguzi wa VIA haukuonyesha mabadiliko kwenye mlango wa kizazi yanayohitaji matibabu kwa sasa. Haya ni matokeo mazuri. Ingawa virusi vya HPV vilipatikana, hakuna mabadiliko yaliyoonekana kwenye mlango wa kizazi kwa sasa. Maambukizi mengi ya HPV huisha yenyewe bila kusababisha matatizo ya kiafya. Hata hivyo, ufuatiliaji wa mara kwa mara ni muhimu kwa sababu baadhi ya maambukizi yanaweza kuendelea kwa muda mrefu na kusababisha mabadiliko kwenye mlango wa kizazi. Huhitaji matibabu kwa sasa. Ni muhimu urudi kwa kipimo kingine cha HPV baada ya mwaka 1 ili kufuatilia afya ya mlango wa kizazi na kuhakikisha kuwa mabadiliko yoyote yatagunduliwa mapema ikiwa yatatokea. Tarehe ya miadi yako ya ufuatiliaji ni: Tarehe: {{2}} Ikiwa utapata dalili kama kutokwa na damu isiyo ya kawaida ukeni, majimaji yenye harufu mbaya kutoka ukeni, au maumivu ya muda mrefu chini ya tumbo, tafadhali tembelea kituo cha afya kwa uchunguzi zaidi. Asante kwa kutumia Afya Rafiki. Tuko hapa kukusaidia.
```

---

## 1-year check-up reminder

### `afya_nav_checkup_1y_en`

Variables: `{{1}}` name, `{{2}}` reminder date

```
Hello {{1}}, please return to Nyeri Town Health Center for a repeat HPV test after 1 year. Reminder date: {{2}}.
```

### `afya_nav_checkup_1y_sw`

```
Habari {{1}}, ni muhimu urudi Nyeri Town Health Center kwa kipimo kingine cha HPV baada ya mwaka 1. Tarehe ya kukumbushwa: {{2}}.
```

---

## Missed appointment — study §13b

### `afya_nav_missed_offer_en`

```
Thank you for your response. We would like to help you continue your follow-up care. Would you like to reschedule your appointment at Nyeri Town Health Centre? Reply: 1. YES - Reschedule my appointment 2. NO - I will contact the clinic myself 3. I need to speak with a healthcare provider
```

### `afya_nav_missed_offer_sw`

```
Asante kwa majibu yako. Tungependa kukusaidia kuendelea na huduma yako ya ufuatiliaji. Je, ungependa kupanga upya miadi yako katika Nyeri Town Health Centre? Jibu: 1. NDIO - Nipangie miadi nyingine 2. HAPANA - Nitawasiliana na kliniki mwenyewe 3. Ningepeda kuzungumza na mhudumu wa afya
```

---

## Missed reschedule confirmation — study §13c

### `afya_nav_missed_confirm_en`

Variables: `{{1}}` name, `{{2}}` new appointment date

```
Hello {{1}}, thank you for choosing to continue your follow-up care. Rescheduling your appointment is an important step in protecting your health and preventing cervical cancer. Your new appointment is scheduled for: Date: {{2}} We look forward to seeing you.
```

### `afya_nav_missed_confirm_sw`

```
Habari {{1}}, asante kwa kuchagua kuendelea na huduma yako ya ufuatiliaji. Kupanga upya miadi yako ni hatua muhimu katika kulinda afya yako na kuzuia saratani ya mlango wa kizazi. Miadi yako mpya imepangwa tarehe: Tarehe: {{2}} Tunatarajia kukuona.
```

---

## Mteja submit checklist (Batch 1)

- [ ] Create **28 templates** using exact names above (`*_en` and `*_sw`)
- [ ] Category: **UTILITY**
- [ ] Language field in Mteja: **en** for `*_en`, **sw** for `*_sw`
- [ ] Verify Meta length (especially `afya_nav_edu_08_en` / `_sw`)
- [ ] After approval, redeploy medicback — code resolves via `mteja_nav_template_id()`

**Optional Render overrides:** `MTEJA_TEMPLATE_AFYA_NAV_EDU_01_EN=...` if Mteja uses a different registered name.

**Batch 2 (registration, referral, post-visit, inbound ack) starts below.**

---

## Batch 2 — registration, referral, post-visit, inbound ack (16 templates)

**Not in your 102 pack.** Names use `afya_nav_*` because content differs from older templates. Submit as **UTILITY**.

### Batch 2 index

| # | English | Kiswahili | When sent |
|---|---------|-----------|-----------|
| 15 | `afya_nav_registration_welcome_en` | `afya_nav_registration_welcome_sw` | Registration message #2 (after consent thank-you) |
| 16 | `afya_nav_referral_reassurance_en` | `afya_nav_referral_reassurance_sw` | +2 min after Nyeri referral SMS |
| 17 | `afya_nav_referral_appt_reminder_en` | `afya_nav_referral_appt_reminder_sw` | 7 days before specialist appointment |
| 18 | `afya_nav_post_visit_en` | `afya_nav_post_visit_sw` | Staff marks appointment **attended** |
| 19 | `afya_nav_via_ablation_en` | `afya_nav_via_ablation_sw` | VIA+ with treatment done (Thermal Ablation) |
| 20 | `afya_nav_tx_postponed_en` | `afya_nav_tx_postponed_sw` | VIA+ with future treatment date (postponed) |
| 21 | `afya_nav_lang_set_en` | — | Patient replies **1** (English) |
| 22 | — | `afya_nav_lang_set_sw` | Patient replies **2** (Kiswahili) |
| 23 | `afya_nav_unsubscribe_en` | `afya_nav_unsubscribe_sw` | Patient replies **3** or **STOP** |

**Batch 1 + Batch 2 total: 44 templates** (42 EN/SW pairs + 2 single-language lang-set).

---

### `afya_nav_registration_welcome_en`

**When:** Immediately after `afya_consent_thanks` at registration (102 pack).

```
Hello. Welcome to Afya rafiki. We are here to support you after your HPV screening results. This service will provide health information, reminders, and guidance for your follow-up care. Your information will remain confidential.
```

### `afya_nav_registration_welcome_sw`

```
Karibu kwenye Afya rafiki. Tuko hapa kukusaidia baada ya majibu yako ya uchunguzi wa HPV. Huduma hii itakutumia taarifa za afya, vikumbusho, na mwongozo wa huduma ya ufuatiliaji. Taarifa zako zitahifadhiwa kwa siri.
```

---

### `afya_nav_referral_reassurance_en`

Variable: `{{1}}` = first name

```
Hello {{1}}, we understand that receiving a referral may cause concern. Please remember that many women referred for specialist assessment do not have cervical cancer. The purpose of the referral is to allow a closer examination of the cervix and ensure that you receive the most appropriate care. Attending your appointment is an important step in protecting your health. Afya Rafiki is here to support you.
```

### `afya_nav_referral_reassurance_sw`

```
Habari {{1}}, tunaelewa kuwa kupokea rufaa kunaweza kukusababishia wasiwasi. Tafadhali kumbuka kuwa wanawake wengi wanaopewa rufaa kwa uchunguzi wa daktari bingwa hawapatikani na saratani ya mlango wa kizazi. Lengo la rufaa ni kusaidia daktari kuchunguza mlango wa kizazi kwa karibu zaidi na kuhakikisha unapata huduma inayofaa. Kuhudhuria miadi yako ni hatua muhimu katika kulinda afya yako. Afya Rafiki iko hapa kukusaidia.
```

---

### `afya_nav_referral_appt_reminder_en`

Variable: `{{1}}` = appointment date/time

```
Reminder from Afya Rafiki. You have a specialist review appointment at Nyeri County Referral Hospital on {{1}}. Please attend as scheduled. This visit will help determine the most appropriate next steps for your care. If you are unable to attend, please contact your healthcare provider to arrange another appointment.
```

### `afya_nav_referral_appt_reminder_sw`

```
Kikumbusho kutoka Afya Rafiki. Una miadi ya uchunguzi wa daktari bingwa katika Hospitali ya Rufaa ya Kaunti ya Nyeri tarehe {{1}}. Tafadhali hudhuria kama ulivyopangiwa. Ziara hii itasaidia kubaini hatua zinazofuata zinazofaa kwa huduma yako. Ikiwa hutaweza kuhudhuria, tafadhali wasiliana na mhudumu wako wa afya ili kupanga miadi nyingine.
```

---

### `afya_nav_post_visit_en`

Variable: `{{1}}` = first name

```
Hello {{1}}, thank you for attending your scheduled follow-up appointment. You have taken an important step in protecting your health and preventing cervical cancer. By attending your appointment, you have helped ensure that any cervical changes can be identified and managed early if needed. We encourage you to continue following the advice of your healthcare provider and attend any future appointments that may be recommended. Keep taking positive steps for your health. Afya Rafiki is proud to support you on your journey. Thank you for choosing Afya Rafiki.
```

### `afya_nav_post_visit_sw`

```
Habari {{1}}, asante kwa kuhudhuria miadi yako ya ufuatiliaji kama ulivyopangiwa. Umechukua hatua muhimu katika kulinda afya yako na kuzuia saratani ya mlango wa kizazi. Kwa kuhudhuria miadi yako, umechangia kuhakikisha kwamba mabadiliko yoyote kwenye mlango wa kizazi yanagunduliwa na kushughulikiwa mapema ikiwa yatahitajika. Tunakuhimiza kuendelea kufuata ushauri wa mhudumu wako wa afya na kuhudhuria miadi nyingine yoyote utakayopangiwa. Endelea kuchukua hatua chanya kwa afya yako. Afya Rafiki inajivunia kuwa sehemu ya safari yako ya afya. Asante kwa kutumia Afya Rafiki.
```

---

### `afya_nav_via_ablation_en`

Variables: `{{1}}` name, `{{2}}` 1-year follow-up date

```
Hello {{1}}, your HPV test was positive, and your VIA examination showed changes on the cervix that required treatment. Thermal Ablation was successfully performed to remove the abnormal cells and help prevent cervical cancer. After treatment, it is normal to experience mild watery discharge and mild lower abdominal discomfort for a few days, mild blood spots. Please use a sanitary pad if needed. Please return to the health facility immediately if you experience heavy bleeding, foul-smelling discharge, severe lower abdominal pain, fever, or any other concerning symptoms. It is important that you return for a repeat HPV test after 1 year to confirm if treatment was successful. Your follow-up appointment is scheduled for: Date: {{2}} Thank you for choosing Afya Rafiki. We are here to support you.
```

### `afya_nav_via_ablation_sw`

```
Habari {{1}}, majibu yako ya HPV yalikuwa chanya (positive), na uchunguzi wa VIA ulionyesha mabadiliko kwenye mlango wa kizazi yaliyohitaji matibabu. Thermal Ablation imefanyika kwa mafanikio ili kuondoa seli zisizo za kawaida na kusaidia kuzuia saratani ya mlango wa kizazi. Baada ya matibabu, ni kawaida kupata majimaji kutoka ukeni na maumivu madogo chini ya tumbo kwa siku chache. Unaweza kutumia pedi ikiwa itahitajika. Tafadhali rudi hospitalini mara moja ikiwa utapata kutokwa na damu nyingi, majimaji yenye harufu mbaya kutoka ukeni, maumivu makali chini ya tumbo, homa, au dalili nyingine zinazokusumbua. Ni muhimu urudi kwa kipimo kingine cha HPV baada ya mwaka 1 ili kuthibitisha kuwa matibabu yalifanikiwa. Tarehe ya miadi yako ya ufuatiliaji ni: Tarehe: {{2}} Asante kwa kutumia Afya Rafiki. Tuko hapa kukusaidia.
```

---

### `afya_nav_tx_postponed_en`

Variables: `{{1}}` name, `{{2}}` rescheduled treatment date

```
Hello {{1}}, your HPV test was positive, and your VIA examination showed changes on the cervix that require treatment. This does not mean that you have cervical cancer. Early treatment helps prevent abnormal cells from developing into cervical cancer. Your treatment was postponed and has been rescheduled for: Date: {{2}} It is important that you attend this appointment so that the recommended treatment can be completed. If you are unable to attend, please contact your healthcare provider or visit Nyeri Town Health Center to arrange another appointment. If you have any questions, Afya Rafiki is here to support you. Thank you for choosing Afya Rafiki.
```

### `afya_nav_tx_postponed_sw`

```
Habari {{1}}, majibu yako ya HPV yalikuwa chanya (positive), na uchunguzi wa VIA ulionyesha mabadiliko kwenye mlango wa kizazi yanayohitaji matibabu. Hii haimaanishi kuwa una saratani ya mlango wa kizazi. Matibabu ya mapema husaidia kuzuia seli zisizo za kawaida zisigeuke kuwa saratani. Matibabu yako yameahirishwa na umepangiwa tarehe nyingine ya matibabu: Tarehe: {{2}} Ni muhimu uhudhurie miadi hii ili matibabu yaliyopendekezwa yakamilike. Ikiwa hutaweza kuhudhuria, tafadhali wasiliana na mhudumu wako wa afya au tembelea Nyeri Town Health Center kupanga tarehe nyingine. Ikiwa una maswali yoyote, Afya Rafiki iko hapa kukusaidia. Asante kwa kutumia Afya Rafiki.
```

---

### `afya_nav_lang_set_en`

**When:** Patient replies **1** to language menu (`afya_welcome` from 102 pack).

```
Thank you. Afya Rafiki will send messages in English. Reply HELP anytime.
```

### `afya_nav_lang_set_sw`

**When:** Patient replies **2**.

```
Asante. Afya Rafiki itatumia Kiswahili. Jibu HELP wakati wowote.
```

---

### `afya_nav_unsubscribe_en`

**When:** Patient replies **3**, **STOP**, **NO**, etc.

```
You have been unsubscribed from Afya Rafiki messages. Contact Nyeri Town Health Centre if you need help.
```

### `afya_nav_unsubscribe_sw`

```
Umejiondoa kupokea ujumbe kutoka Afya Rafiki. Wasiliana na Nyeri Town Health Centre ikiwa unahitaji msaada.
```

---

## Combined Mteja checklist (Batch 1 + 2)

- [ ] Batch 1: **28** `afya_nav_edu_*`, `afya_nav_via_neg_result_*`, `afya_nav_checkup_1y_*`, `afya_nav_missed_*`
- [ ] Batch 2: **16** templates above (registration through unsubscribe)
- [ ] Keep using **102 pack** for: consent thank-you, welcome menu, HPV results, appt booked/updated, reminders, missed survey, referral initial, FAQ, etc.
- [ ] Category: **UTILITY** · languages **en** / **sw** per suffix
- [ ] Redeploy medicback after Mteja approval

**Optional Render overrides:** `MTEJA_TEMPLATE_AFYA_NAV_REGISTRATION_WELCOME_EN=...` etc.

---

## Batch 3 — HPV failed / inconclusive (retest)

| # | English | Kiswahili | When sent |
|---|---------|-----------|-----------|
| 24 | `afya_hpv_failed_en` | `afya_hpv_failed_sw` | Staff confirms **HPV failed** after retest appointment is booked |

### `afya_hpv_failed_en`

Variables: `{{1}}` name, `{{2}}` retest appointment date

```
Hello {{1}}, Welcome to Afya Rafiki. Your HPV test did not give a clear result (failed / inconclusive). This sometimes happens and does not mean you have cancer. You need to repeat the HPV test. You have been scheduled for a retest at Nyeri Town Health Center on: Date: {{2}} Please attend your appointment as scheduled. If you have any questions, Afya Rafiki is here to support you. Thank you for choosing Afya Rafiki.
```

### `afya_hpv_failed_sw`

```
Habari {{1}}, Karibu kwenye Afya Rafiki. Kipimo chako cha HPV hakikutoa matokeo wazi (failed / inconclusive). Hii inaweza kutokea wakati mwingine na haimaanishi kuwa una saratani. Unahitaji kufanya kipimo tena. Umepangiwa miadi ya kufanya kipimo tena katika Nyeri Town Health Center tarehe: Tarehe: {{2}} Tafadhali hudhuria miadi yako kama ulivyopangiwa. Ikiwa una maswali, Afya Rafiki iko hapa kukusaidia. Asante kwa kutumia Afya Rafiki.
```

---

## Batch 4 — HPV negative (5-year return, no appointment)

| # | English | Kiswahili | When sent |
|---|---------|-----------|-----------|
| 25 | `afya_hpv_negative_en` | `afya_hpv_negative_sw` | Staff confirms **HPV negative** — one message only; no clinic visit booked |

Replaces the longer `afya_hpv_neg_hivpos` / `afya_hpv_neg_hivneg` pair for new go-live (all patients: return in **5 years**).

### `afya_hpv_negative_en`

Variables: `{{1}}` name

```
Hello {{1}}, Welcome to Afya Rafiki. Your HPV test result is negative. Please return to Nyeri Town Health Center in 5 years for your next HPV test. Thank you for choosing Afya Rafiki.
```

### `afya_hpv_negative_sw`

```
Habari {{1}}, Karibu kwenye Afya Rafiki. Majibu yako ya HPV ni hasi (negative). Tafadhali rudi Nyeri Town Health Center baada ya miaka 5 kwa kipimo chako kingine cha HPV. Asante kwa kutumia Afya Rafiki.
```

---

*June 2026 — Afya Rafiki pilot · Nyeri Town Health Center*
