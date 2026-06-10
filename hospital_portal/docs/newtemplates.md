# New WhatsApp templates to submit (Mteja)

Templates listed here are **not yet correctly mapped in medicback** or **do not exist** in `WHATSAPP_MESSAGE_TEMPLATES.md` but the system sends (or should send) the message. Submit as **UTILITY**, languages `en` + `sw`.

**Reference:** `WHATSAPP_MESSAGE_TEMPLATES.md` (approved pack), `SCRIPT_VS_SYSTEM_COMPARISON.md` (gaps).

---

## Priority 1 — Wrong or missing mapping today

### `afya_registration_welcome` (NEW — not in current pack)

**Problem:** Registration welcome is sent as `registration_welcome` but Mteja maps it to `afya_welcome` (language 1/2/3 menu). Wrong template on WhatsApp.

**When sent:** Immediately after consent thank-you at registration (`send_afya_enrollment_messages`).

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

### `afya_referral_reassurance` (NEW — not in current pack)

**Problem:** Sent +2 minutes after manual Nyeri referral (`build_referral_reassurance_message`). Uses `education_menu` type → currently resolves to `afya_help_menu` on WhatsApp.

**When sent:** Staff **Refer to Nyeri County Referral Hospital** after HPV confirmed + VIA recorded.

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

### `afya_checkup_hiv_via_neg_3y` (NEW — replaces use of old `afya_checkup_via_neg` 1-year)

**Problem:** After VIA negative, system schedules **3-year** HPV re-screen for HIV+ patients. SMS body is built in code; WhatsApp falls through to `afya_fallback`.

**When sent:** Scheduled `checkup_reminder` at VIA date + 3 years (HIV positive).

**Body (en):**

```
Hello {{1}}, for follow-up (HIV positive, HPV positive), please return to Nyeri Town Health Center for HPV screening on {{2}}.
```

**Body (sw):**

```
Habari {{1}}, kwa ufuatiliaji (VVU chanya, HPV chanya), tafadhali rudi Nyeri Town Health Center kwa kipimo cha HPV tarehe {{2}}.
```

| Variable | Sample |
|----------|--------|
| `{{1}}` | Grace |
| `{{2}}` | 2029-06-10 |

---

### `afya_checkup_hiv_via_neg_5y` (NEW — replaces old 1-year `afya_checkup_via_neg` for HIV−)

**When sent:** Scheduled `checkup_reminder` at VIA date + 5 years (HIV negative / not known).

**Body (en):**

```
Hello {{1}}, your VIA result was negative. Please return to Nyeri Town Health Center for repeat HPV screening on {{2}}.
```

**Body (sw):**

```
Habari {{1}}, matokeo yako ya VIA yalikuwa hasi. Tafadhali rudi Nyeri Town Health Center kwa kipimo cha HPV tarehe {{2}}.
```

| Variable | Sample |
|----------|--------|
| `{{1}}` | Mary |
| `{{2}}` | 2031-06-10 |

---

### ~~`afya_counsel_drip`~~ — **RESOLVED in code (June 2026)**

HPV positive drip now uses **10 short tips** (`afya_simple_drip.php`) sent as `engagement_boost` (+1d, then +2d). Mteja maps to `afya_faq_hpv`, `afya_faq_cancer`, `afya_faq_treat`, or `afya_engagement_tip`. Long `afya_counsel_pos_01`…`16` drip is **no longer used** for HPV confirm (VIA record still uses steps 9–10 for clinical result SMS).

---

## Priority 2 — Inbound replies (hardcoded SMS today)

### `afya_lang_set_en`

**When sent:** Patient replies `1` to language intro (`afya_welcome`).

```
Thank you. Afya Rafiki will send messages in English. Reply HELP anytime.
```

### `afya_lang_set_sw`

**When sent:** Patient replies `2`.

```
Asante. Afya Rafiki itatumia Kiswahili. Jibu HELP wakati wowote.
```

### `afya_unsubscribe_ack`

**When sent:** Patient replies `3` / STOP.

```
You have been unsubscribed from Afya Rafiki messages. Contact Nyeri Town Health Centre if you need help.
```

*(Kiswahili version recommended for `afya_unsubscribe_ack_sw`.)*

---

## Priority 3 — In pack but not wired in code (submit if not yet approved; wire send logic)

These exist in `WHATSAPP_MESSAGE_TEMPLATES.md` but **no PHP call sends them yet**:

| Template | # | Wire when |
|----------|---|-----------|
| `afya_missed_reschedule` | 17 | Patient replies 1–7 to missed-appt survey |
| `afya_post_visit` | 18 | After staff marks **Patient attended** (optional thank-you) |
| `afya_checkup_generic` | 26 | Fallback scheduled check-up |

**Post-visit variants** (builders in `afya_rafiki_content.php`, no templates in pack — add if clinical team wants WhatsApp for these):

| Suggested name | Trigger |
|----------------|---------|
| `afya_post_visit_via_neg` | After VIA negative + treatment plan with date |
| `afya_post_visit_ablation` | After Thermal Ablation documented |
| `afya_post_visit_tx_postponed` | Treatment rescheduled |

---

## Priority 4 — Script content missing from code

### `afya_counsel_pos_16` (in pack §49; **missing from** `afya_counseling_positive.php`)

**Action:** Add message index 15 to PHP array **and** ensure drip reaches it, **or** send as pre-VIA education only (step 8 replacement for referral prep).

**Body (en):** See `WHATSAPP_MESSAGE_TEMPLATES.md` §49 — VIA pathways & suspicious-for-cancer referral prep.

---

## Deprecated for current flow (do not submit unless reverting logic)

| Template | Reason |
|----------|--------|
| `afya_checkup_via_neg` (§21) | System no longer schedules 1-year annual after VIA neg; uses HIV 3y/5y instead |
| `afya_checkup_hiv_hpvneg` / `afya_checkup_hiv_hpvpos` (§22–23) | Were tied to registration `hpv_prior_result`; replaced by `via_neg_hiv_*` reasons |

---

## After Mteja approval — code updates needed

1. `mteja_whatsapp.php` — map `registration_welcome` → `afya_registration_welcome`
2. `mteja_whatsapp.php` — map `checkup_reminder` by body/reason to `afya_checkup_hiv_via_neg_3y` / `_5y`
3. `mteja_whatsapp.php` — map `education_menu` counseling bodies to `afya_counsel_pos_XX` or `afya_counsel_drip`
4. `mteja_whatsapp.php` — map referral reassurance body → `afya_referral_reassurance`
5. Add `afya_counsel_pos_16` to `afya_counseling_positive.php`
6. Wire `build_post_visit_*` and `build_missed_reschedule_confirmation` to inbound/staff actions

---

*June 2026 — Afya Rafiki / Nyeri Town Health Center*
