# Study script vs Afya Rafiki system — gap analysis

**Study:** Co-design and feasibility evaluation — AI-enabled rule-based digital navigator for HPV positive women, Nyeri County.  
**Script author:** Dr Evah Maina et al.  
**System:** medicback (`hospital_portal/`) · **102 Mteja templates approved**  
**Last reviewed:** June 2026  

---

## Executive summary

| Area | Status |
|------|--------|
| Core HPV+ pathway (register → HPV+ → book → reminders → VIA → follow-up SMS) | **Mostly built** |
| HPV− light path (one result SMS, 3y/5y) | **Built** |
| Full counseling drip (Messages 1–10 pre-VIA) | **Partial** — simplified FAQ drip, not full script |
| Post-visit & missed-appointment conversation (§12–13) | **Not wired** |
| Referral 3-message pathway | **Partial** (1 of 3) |
| Groq AI + FAQ + escalation | **Built** — **WhatsApp inbound not live** until Mteja webhook |
| Study “nurse activates Afya Rafiki after phone call” | **Process gap** — system auto-sends on confirm |

---

## 1. Master workflow (Study §14)

```
Study intended:
  HPV Result → Enrollment → Consent → HPV Education → Reminders → FAQ → Triage → Follow-up completion

System today:
  Register → consent + welcome → [HPV record] → HPV confirm → HPV+ drip until VIA
  → Book appt → 7d/3d/1d reminders → Attend/Miss → Record VIA → Book follow-up
  → VIA result SMS + appt SMS → [stop drip]
  → Inbound FAQ / Groq / DOCTOR / escalation
  → ❌ post-visit thank-you
  → ❌ missed survey → reschedule conversation
  → ❌ treatment-specific completion messages (§12b–c)
```

---

## 2. What is aligned ✅

| Study requirement | System |
|-------------------|--------|
| Afya Rafiki name, warm tone, no diagnosis | Groq system prompt + FAQ builders |
| Paper consent, no SMS YES/NO | `record_registration_consent` |
| Client ID `NC/NTHC/001/` + lab digits | `patient_client_id.php` |
| Register: name, DOB, phone, language, channel, HIV, etc. | Patient API + UI |
| VIA not at registration | Forced `not_done` at register |
| HPV negative → HIV 3y / 5y message | `build_hpv_negative_result_notification` |
| HPV positive → result + appointment date | `build_hpv_positive_result_notification` |
| HPV+ requires appointment before confirm | Gated in `confirm_patient_hpv_result` |
| Appointment booked / updated SMS | `send_appointment_notification` |
| Reminders 7d, 3d, 1d (evening before) | `reminders.php` cron |
| Missed appointment survey (reply 1–7) | `build_missed_appointment_message` on mark missed |
| FAQ: HPV, cancer, treat, symptoms | `afya_faq_reply` + templates 28–33 |
| Escalation + DOCTOR keyword | `afya_escalation_check`, `doctor_call_requests.php` |
| HELP menu | `build_help_menu_message` |
| VIA referral (suspicious/cancer) | `build_referral_initial_message` / manual Nyeri refer |
| Attendance attended / missed | `mark_appointment_attended` / `mark_appointment_missed` |
| Record VIA after first attended visit | UI workflow + API gates |

---

## 3. Flow gaps — missing or different ❌

### 3.1 Activation model (Study Step 1 / INTRODUCTION)

| Study | System | Gap |
|-------|--------|-----|
| HPV+ woman **called by nurse**, informed, return date set, then nurse **activates** Afya Rafiki | Messages fire on **registration** (consent + welcome) and **HPV confirm** automatically | No explicit “Activate Afya Rafiki” button or gate |
| **Introduction** (1 English / 2 Kiswahili / 3 stop) sent when service starts for HPV+ | `build_language_introduction_message` sent on HPV+ confirm **only if** no prior paper consent | Usually **skipped** because registration already records consent |
| Registration welcome is confidential intro text | Same text exists but WhatsApp maps to **`afya_welcome`** (wrong template) | Template mapping bug |

**Recommendation:** Treat HPV confirm as “activation” in SOP; optionally send language intro on HPV+ confirm always, or stop sending registration welcome until HPV result known.

---

### 3.2 HPV counseling Messages 1–10 (Study §3)

| Study message | Topic | System |
|---------------|-------|--------|
| 1 | Understanding HPV (8 in 10…) | Partial — FAQ drip msg 1, not full script |
| 2 | Importance of follow-up | **Missing** as dedicated drip step |
| 3 | Reducing fear | Partial — drip msg 2 |
| 4 | Confidence / taking action | **Missing** |
| 5 | Social support | **Missing** |
| 6 | Perceived risk of non-attendance | **Missing** |
| 7 | What happens next — VIA | **Missing** from drip (only in counsel_pos_07 in template pack, unused) |
| 8 | Understanding VIA results | **Missing** from drip |
| 9–10 | VIA neg / VIA pos scenarios | Sent **on follow-up book**, not as pre-VIA education |

**System today:** 10-message **simplified drip** (`afya_simple_drip.php`): 3 FAQ lines + 7 generic engagement tips until VIA recorded.  
**Your 102 templates include** `afya_counsel_pos_01` … `_16` but code **does not send 01–08, 11–16** in the drip.

**Gap:** Study’s timed counseling sequence is **not fully automated**. Content exists in template pack; wiring was replaced by short FAQ drip for Mteja limits.

---

### 3.3 VIA timing and result messages (Study §5 / §12)

| Study | System | Gap |
|-------|--------|-----|
| VIA result given **immediately after test** | VIA **saved silently**; result SMS sent when **follow-up appointment is booked** | **Workflow difference** — intentional in pilot so SMS includes next appointment date |
| VIA negative → return **1 year** (§12b, counseling msg 8) | Schedules **3y/5y HPV re-screen** from VIA date by HIV status | **Clinical pathway difference** — confirm with study team |
| VIA negative SMS is long §12b text with appointment date | Sends `build_via_negative_result_notification` (shorter; counsel_pos_09 on WA) | Wording + 1-year date may differ |
| VIA positive + ablation → §12c long message with TOC date | Sends `build_via_positive_result_notification` (counsel_pos_10) | Shorter than study §12c; ablation/postponed variants **not sent** |
| Thermal ablation after-care (msgs 11–15) | Only in unused `afya_counsel_pos_11`–`_15` | **Not triggered** by `treatment_date` on VIA record |

---

### 3.4 Referral 3-stage pathway (Study counseling msg 10)

| Stage | Study | System |
|-------|-------|--------|
| 1 — Initial referral + appt date | ✅ | `afya_via_referral` on cancer flag or manual refer |
| 2 — Reassurance 1–2 days later | ✅ SMS scheduled | Wrong WA template; should be `afya_referral_reassurance` (**Batch A2**) |
| 3 — Specialist appointment reminder | ❌ | `build_referral_appointment_reminder` exists, **never scheduled** |

---

### 3.5 After visit (Study §12a)

| Message | System |
|---------|--------|
| General thank-you after attending appointment | Builder `build_post_visit_acknowledgement` exists · **`afya_post_visit` in your 102** · **never sent** on attended |

---

### 3.6 Missed appointment conversation (Study §13)

| Step | Study | System |
|------|-------|--------|
| 13 — Survey 1–7 | ✅ Sent on missed | ✅ |
| 13b — Reschedule offer (YES/NO/DOCTOR) | ❌ | Builder exists · **no inbound handler** for replies 1–7 |
| 13c — Confirmation with new date | ❌ | Builder exists · **not wired** |

Templates **`afya_missed_reschedule`** (17) and new **`afya_missed_reschedule_confirm`** (Batch C5) cover wording; **code wiring is the blocker**.

---

### 3.7 Inbound AI (Study §9–11)

| Capability | Code | Live? |
|------------|------|-------|
| Groq conversational replies | ✅ `ai_generate_reply` | ⚠️ **WhatsApp inbound webhook not configured** (`WHATSAPP_VERIFY_TOKEN` empty) |
| Rule FAQ before AI | ✅ | Same blocker for WhatsApp |
| Escalation triggers | ✅ | Same |
| SMS inbound via Africa’s Talking | ✅ Ready | Works when patient SMS-replies |

---

### 3.8 HPV negative path

| Study | System |
|-------|--------|
| One result message, return 3y/5y | ✅ On HPV confirm |
| No ongoing counseling for HPV− | ✅ Drip cancelled on negative confirm (June 2026) |
| No scheduled 3y/5y reminder SMS | ⚠️ Text in message only — no cron job for HPV− alone |

---

## 4. Template gaps vs your 102

See **`MTEJA_ADDITIONAL_TEMPLATES.md`** for submission list.

| Category | Count | Notes |
|----------|-------|-------|
| **Wrong mapping (submit Batch A)** | 8 new | registration welcome, referral reassurance, 3y/5y checkup |
| **In 102 but unwired (Batch B)** | 0 new | post_visit, missed_reschedule, counsel 09/10 |
| **Study §12–13 not in pack (Batch C)** | 10 new | referral reminder, post-VIA variants, reschedule confirm |
| **Optional inbound ack (Batch D)** | 3–4 | language 1/2, unsubscribe |

**You do not need to resubmit** `afya_counsel_pos_01`–`_16` if already in the 102 — you need **code to send them** OR accept simplified drip.

---

## 5. Priority fix order (recommended)

### P0 — Operational (no new templates)

1. **Mteja inbound webhook** → `webhook_whatsapp.php` + `WHATSAPP_VERIFY_TOKEN`  
2. **AT delivery reports** → `webhook_delivery_report.php` (not inbound URL)  
3. **Approve `afya_ai_reply`** for Groq WhatsApp replies  

### P1 — Mapping (Batch A — 8 templates)

4. `afya_registration_welcome`  
5. `afya_referral_reassurance`  
6. `afya_checkup_hiv_via_neg_3y` / `_5y`  

### P2 — Wire existing 102 templates

7. Send **`afya_post_visit`** when staff marks attended  
8. Inbound handler: missed reply 1–7 → **`afya_missed_reschedule`** → staff books → **`afya_missed_reschedule_confirm`**  
9. Schedule **referral appointment reminder** when Nyeri appt booked  

### P3 — Clinical alignment decisions

10. **1-year vs 3y/5y** after VIA negative — study vs current code  
11. Full **counsel_pos 01–08 drip** vs simplified FAQ drip  
12. **Treatment-triggered** messages (ablation, postponed) from VIA form fields  

### P4 — Batch C templates (after clinical sign-off)

13. Long-form §12b/§12c WhatsApp bodies if counsel_pos_09/10 text is insufficient  

---

## 6. Study tone & identity checklist

| Guideline | System |
|-----------|--------|
| Warm, supportive, non-judgmental | ✅ Prompt + builders |
| Short messages | ⚠️ Some counseling templates are long (Meta 1024 limit) |
| Normalize HPV, promote hope | ✅ Counseling + FAQ content |
| Avoid frightening / stigmatizing language | ✅ Reviewed in builders |
| Escalate complex issues | ✅ Escalation + DOCTOR |
| Does not diagnose or prescribe | ✅ AI guardrails in prompt |

---

## 7. Quick “what nurses should know today”

1. **HPV+:** Record HPV → book appointment → confirm HPV (sends result + starts drip) → appointment day → attended → record VIA → book follow-up (sends VIA result + appt).  
2. **HPV−:** Record → confirm (one SMS, then quiet).  
3. **Patient replies:** Work on **WhatsApp** only after Mteja webhook is live; **SMS** replies work via Africa’s Talking today.  
4. **Missed visits:** Survey goes out; **replies are not yet handled automatically** — nurse should call or reschedule manually until §13 is wired.  
5. **After attended visit:** **No automatic thank-you SMS yet** — optional manual message from console.

---

*Cross-reference: `SCRIPT_VS_SYSTEM_COMPARISON.md` (technical detail) · `MTEJA_ADDITIONAL_TEMPLATES.md` (new submissions) · `MTEJA_WHATSAPP_GO_LIVE.md` (webhook setup)*
