# Afya Rafiki — Mteja knowledge base (multi-FAQ)

Import these **question–answer pairs** into the **Mteja knowledge base** (or chatbot FAQ / AI assistant training) for **Nyeri Town Health Center — Afya Rafiki**.

| Setting | Value |
|---------|--------|
| Service | Afya Rafiki — HPV & cervical health follow-up |
| Facility | Nyeri Town Health Center |
| Referral hospital | Nyeri County Referral Hospital |
| Languages | English (`en`) and Kiswahili (`sw`) |
| Escalation keyword | Patient types **DOCTOR** or **DAKTARI** |
| Menu keyword | Patient types **HELP**, **MENU**, or **MSAADA** |

**Tip for Mteja:** Create one knowledge base per language, or tag each entry with `en` / `sw`. Add the **keywords** column as trigger phrases in the chatbot.

---

## A. Programme overview (always include)

| ID | Keywords (triggers) | Language | Question | Answer |
|----|---------------------|----------|----------|--------|
| A1 | afya rafiki, what is this, ni nini | en | What is Afya Rafiki? | Afya Rafiki is a confidential follow-up service from Nyeri Town Health Center after HPV screening. We send health information, appointment reminders, and answer common questions. We do not replace your nurse or doctor — reply DOCTOR to reach a health worker. |
| A2 | afya rafiki, ni nini | sw | Afya Rafiki ni nini? | Afya Rafiki ni huduma ya siri ya ufuatiliaji kutoka Nyeri Town Health Center baada ya uchunguzi wa HPV. Tunatuma taarifa za afya, vikumbusho vya miadi, na kujibu maswali ya kawaida. Hatubadili mhudumu wako wa afya — jibu DOCTOR ili kuwasiliana na mhudumu. |
| A3 | privacy, confidential, siri | en | Is my information private? | Yes. Your information is kept confidential and used only for your HPV follow-up care at Nyeri Town Health Center. |
| A4 | siri, faragha | sw | Taarifa zangu ni za siri? | Ndiyo. Taarifa zako zinahifadhiwa kwa siri na zinatumika kwa ufuatiliaji wako wa HPV katika Nyeri Town Health Center pekee. |
| A5 | cost, malipo, free, bure | en | Do I pay for Afya Rafiki messages? | Afya Rafiki messages are part of your care programme. For clinic fees or tests, ask at Nyeri Town Health Center reception. |
| A6 | malipo, gharama | sw | Je, najilipia ujumbe wa Afya Rafiki? | Ujumbe wa Afya Rafiki ni sehemu ya mpango wako wa huduma. Kuhusu ada za kliniki au vipimo, uliza ofisi ya Nyeri Town Health Center. |
| A7 | hours, location, wapi, saa | en | Where is the clinic and when is it open? | Main site: Nyeri Town Health Center. For opening hours and directions, contact the facility directly or visit reception. |
| A8 | wapi kliniki, saa ngapi | sw | Kliniki iko wapi na inafunguliwa saa ngapi? | Kituo kikuu: Nyeri Town Health Center. Kwa saa za kazi na maelekezo, wasiliana na kliniki moja kwa moja. |

---

## B. HELP menu — same as medicback (`build_help_menu_message`)

| ID | Keywords | Language | Question | Answer |
|----|----------|----------|----------|--------|
| B0 | help, menu, msaada, 0 | en | (Menu) | Afya Rafiki — options: 1) What is HPV? 2) Do I have cervical cancer? 3) Can HPV be treated? 4) Appointments / reschedule 5) Symptoms of HPV 6) Symptoms of cervical cancer 7) Speak to a provider (reply DOCTOR). Type your question or option number. |
| B0 | help, menu, msaada, 0 | sw | (Menyu) | Afya Rafiki — chaguo: 1) HPV ni nini? 2) Je, nina saratani ya mlango wa kizazi? 3) HPV inatibika? 4) Miadi / kupanga upya 5) Dalili za HPV 6) Dalili za saratani ya mlango wa kizazi 7) Ongea na mhudumu wa afya (DOCTOR). Andika swali lako au namba ya chaguo. |

---

## C. Core FAQs (matches `afya_faq_reply` in medicback)

| ID | Keywords | Lang | Question | Answer |
|----|----------|------|----------|--------|
| C1 | 1, what is hpv, hpv ni nini | en | What is HPV? | HPV is a common virus that can affect the cervix. Some types may cause cervical cancer if not treated early. Follow-up care helps protect your health. |
| C1 | 1, hpv ni nini | sw | HPV ni nini? | HPV ni virusi vya kawaida vinavyoweza kuathiri mlango wa kizazi. Aina zingine zinaweza kusababisha saratani ya mlango wa kizazi zisipotibiwa mapema. Huduma ya ufuatiliaji husaidia kulinda afya yako. |
| C2 | 2, cervical cancer, do i have cancer, nina saratani | en | Do I have cervical cancer? | A positive HPV result does not mean you have cervical cancer. It means you have HPV virus. HPV can lead to cervical cancer if left untreated for a long time. Additional follow-up care is needed. Please attend your clinic appointment. |
| C2 | 2, saratani ya mlango | sw | Je, nina saratani ya mlango wa kizazi? | Majibu chanya ya HPV hayamaanishi kuwa una saratani. Inamaanisha una virusi vya HPV. Virus hivi vinaweza kusababisha saratani ya mlango wa kizazi vikikaa muda mrefu bila matibabu. Huduma zaidi ya ufuatiliaji inahitajika. Tafadhali hudhuria miadi yako. |
| C3 | 3, hpv treated, hpv inatibika | en | Can HPV be treated? | HPV infections often clear naturally. Follow-up care helps health providers monitor and manage any cervical changes early. |
| C3 | 3, hpv inatibika | sw | HPV inatibika? | Maambukizi ya HPV mara nyingi hupotea yenyewe. Huduma ya ufuatiliaji husaidia wahudumu wa afya kufuatilia na kutibu mabadiliko mapema. |
| C4 | 4, appointment, miadi, reschedule, panga upya, 7 | en | Appointments / reschedule | For appointments, contact Nyeri Town Health Center or wait for your reminder message. Reply DOCTOR if you need urgent help. |
| C4 | 4, miadi, panga upya | sw | Miadi / kupanga upya | Kwa miadi, wasiliana na Nyeri Town Health Center au subiri kikumbusho. Jibu DOCTOR ikiwa unahitaji msaada wa haraka. |
| C5 | 5, hpv symptoms, dalili za hpv | en | What are symptoms of HPV? | Most people with HPV have no symptoms. HPV usually does not cause pain or itching. The body often clears the infection. Some types cause cervical changes found only on screening (HPV test, VIA). Regular screening matters even when you feel well. Unusual bleeding, persistent pain, or unusual discharge — see a provider. |
| C5 | 5, dalili za hpv | sw | Dalili za HPV ni zipi? | Watu wengi hawana dalili. HPV kwa kawaida haisababishi maumivu. Mwili mara nyingi huondoa maambukizi. Mabadiliko yanaweza kugundulika kupitia uchunguzi (HPV, VIA). Damu isiyo ya kawaida, maumivu ya kudumu, au majimaji yasiyo ya kawaida — tembelea mhudumu wa afya. |
| C6 | 6, cervical cancer symptoms, dalili za saratani | en | Symptoms of cervical cancer? | Many women with early changes have no symptoms. Possible signs: bleeding after sex, between periods, or after menopause; unusual discharge; pelvic pain; pain during sex. These do not always mean cancer — visit a health facility for assessment. |
| C6 | 6, dalili za saratani | sw | Dalili za saratani ya mlango wa kizazi? | Wanawake wengi hawana dalili mapema. Dalili zinazoweza kuonekana: damu baada ya ngono, kati ya hedhi, au baada ya kukoma hedhi; majimaji yasiyo ya kawaida; maumivu ya kudumu; maumivu wakati wa ngono. Si lazima iwe saratani — tembelea kituo cha afya. |
| C7 | doctor, daktari, speak provider | en | How do I speak to a health worker? | Reply **DOCTOR** (or **DAKTARI**). We will ask you briefly why you need help, then a provider will follow up. If emergency (heavy bleeding, severe pain, fever), go to the clinic immediately. |
| C7 | doctor, daktari | sw | Ninawezaje kuongea na mhudumu wa afya? | Jibu **DOCTOR** au **DAKTARI**. Tutakuomba ueleze kwa ufupi, kisha mhudumu atawasiliana nawe. Ikiwa ni dharura (damu nyingi, maumivu makali, homa), nenda kliniki mara moja. |

---

## D. Results & pathways

| ID | Keywords | Lang | Question | Answer |
|----|----------|------|----------|--------|
| D1 | negative hpv, hpv hasi | en | My HPV result is negative — what next? | A negative result means no HPV was detected now. Return for screening in 5 years (or 3 years if you are living with HIV), or sooner if your provider advises. |
| D1 | hpv hasi | sw | Matokeo ya HPV yangu ni hasi — nifanye nini? | Matokeo hasi yanamaanisha hakuna HPV yaliyopatikana sasa. Rudi kwa uchunguzi baada ya miaka 5 (au miaka 3 ukiishi na VVU), au mapema kama utaelekezwa. |
| D2 | positive hpv, hpv chanya | en | My HPV result is positive — what next? | HPV positive does not mean cervical cancer. You need follow-up at Nyeri Town Health Center, often including VIA. Attend your scheduled appointment. Afya Rafiki will send reminders and education. |
| D2 | hpv chanya | sw | Matokeo ya HPV yangu ni chanya — nifanye nini? | HPV chanya si saratani. Unahitaji ufuatiliaji katika Nyeri Town Health Center, mara nyingi pamoja na VIA. Hudhuria miadi yako. Afya Rafiki itakutumia vikumbusho na taarifa. |
| D3 | via, via ni nini | en | What is VIA? | VIA (Visual Inspection with Acetic acid) is a quick exam where a provider looks at the cervix after applying a vinegar solution. It helps find changes that may need treatment. |
| D3 | via ni nini | sw | VIA ni nini? | VIA ni uchunguzi wa haraka ambapo mhudumu wa afya anaangalia mlango wa kizazi baada ya dawa ya siki. Husaidia kugundua mabadiliko yanayoweza kuhitaji matibabu. |
| D4 | thermal ablation, ablation | en | What is Thermal Ablation? | Thermal Ablation treats abnormal cervical cells with heat. It is usually quick, done as an outpatient procedure, and helps prevent cancer. Follow after-care instructions and attend follow-up visits. |
| D4 | thermal ablation | sw | Thermal Ablation ni nini? | Ni matibabu yanayotumia joto kuondoa seli zisizo za kawaida kwenye mlango wa kizazi. Kawaida hufanyika haraka nje ya kulazwa. Fuata maelekezo baada ya matibabu na miadi za ufuatiliaji. |
| D5 | referral, rufaa, specialist | en | Why was I referred to Nyeri County Referral Hospital? | Some VIA findings need a specialist assessment. Referral does not always mean cancer — more tests may be needed. Please attend your referral appointment date. |
| D5 | rufaa, hospitali ya rufaa | sw | Kwa nini nimepewa rufaa Hospitali ya Rufaa ya Kaunti ya Nyeri? | Baadhi ya matokeo ya VIA yanahitaji daktari bingwa. Rufaa haimaanishi moja kwa moja saratani — vipimo zaidi vinaweza kuhitajika. Tafadhali hudhuria tarehe ya rufaa yako. |

---

## E. Appointments & missed visits

| ID | Keywords | Lang | Question | Answer |
|----|----------|------|----------|--------|
| E1 | missed appointment, sikuhudhuria, nilikosa | en | I missed my appointment | Thank you for telling us. Follow-up protects your health. Reply with: 1 Transport 2 Forgot 3 Fear/worry 4 Work/family 5 Unwell 6 Came but not seen 7 Other — or reply DOCTOR. |
| E1 | nilikosa miadi | sw | Nilikosa miadi yangu | Asante kwa kujulisha. Ufuatiliaji unalinda afya yako. Jibu: 1 Usafiri 2 Nilisahau 3 Hofu/wasiwasi 4 Kazi/familia 5 Nilikuwa mgonjwa 6 Nilifika lakini sikuhudumiwa 7 Nyingine — au jibu DOCTOR. |
| E2 | reschedule, panga upya, ndio | en | I want to reschedule | Reply **1** if you want us to help reschedule at Nyeri Town Health Center, **2** if you will contact the clinic yourself, or **DOCTOR** to speak to a provider. |
| E2 | panga upya miadi | sw | Nataka kupanga miadi upya | Jibu **1** ikiwa unataka msaada wa kupanga upya Nyeri Town Health Center, **2** utawasiliana mwenyewe na kliniki, au **DOCTOR** kuongea na mhudumu. |

---

## F. Urgent & escalation (for Mteja agents + bot rules)

| ID | Keywords | Lang | Question | Answer |
|----|----------|------|----------|--------|
| F1 | emergency, dharura, bleeding, damu nyingi | en | I have heavy bleeding / emergency | Go to **Nyeri Town Health Center** or the nearest health facility **immediately**. Afya Rafiki cannot manage emergencies by chat alone. |
| F1 | dharura, damu nyingi | sw | Nina damu nyingi / dharura | Nenda **Nyeri Town Health Center** au kituo cha afya kilicho karibu **mara moja**. Afya Rafiki haiwezi kushughulikia dharura kwa ujumbe pekee. |
| F2 | scared, worried, nina hofu, wasiwasi | en | I am very scared or worried | Your feelings are understandable. A provider can support you best — reply **DOCTOR**. If symptoms are severe, go to the clinic. |
| F2 | nina hofu, ninaogopa | sw | Nina hofu sana | Hisia zako ni za kawaida. Mhudumu wa afya anaweza kukusaidia zaidi — jibu **DOCTOR**. Ikiwa dalili ni kali, nenda kliniki. |

---

## G. Unregistered users

| ID | Keywords | Lang | Question | Answer |
|----|----------|------|----------|--------|
| G1 | register, not registered, sajili | en | I am not registered | To get personalized Afya Rafiki support, register your phone number at Nyeri Town Health Center with message consent. For urgent care, contact the hospital directly. |
| G1 | sajili, sijajisajili | sw | Sijajisajili | Ili kupata msaada wa Afya Rafiki, sajili nambari yako katika Nyeri Town Health Center na idhini ya ujumbe. Kwa dharura, wasiliana na hospitali moja kwa moja. |

---

## H. Import checklist for Mteja

- [ ] Create knowledge base **Afya Rafiki EN** — import sections A–G (English rows)
- [ ] Create knowledge base **Afya Rafiki SW** — import sections A–G (Kiswahili rows)
- [ ] Link knowledge base to WhatsApp chatbot / AI assistant
- [ ] Map **HELP** → show menu (section B)
- [ ] Map **DOCTOR** / **DAKTARI** → handoff or webhook to medicback (do not contradict clinic workflow)
- [ ] Train bot: **do not diagnose**; encourage clinic attendance
- [ ] Optional: sync with medicback webhook `https://medicback.onrender.com/webhook_whatsapp.php` for registered patients (full AI + escalations)

---

## I. CSV format (copy into spreadsheet for bulk upload)

If Mteja accepts CSV, use columns:

`id,language,keywords,question,answer,category`

Example rows:

```csv
id,language,keywords,question,answer,category
C1,en,"1,what is hpv",What is HPV?,HPV is a common virus...,FAQ
C1,sw,"1,hpv ni nini",HPV ni nini?,HPV ni virusi...,FAQ
```

---

*Aligned with medicback `afya_rafiki_content.php` and official Afya Rafiki script — June 2026*
