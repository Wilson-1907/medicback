<?php
declare(strict_types=1);

/**
 * HPV-positive counseling sequence — official Nyeri County study script (Dr Evah Maina et al.).
 * Source of truth: hospital_portal/docs/WHATSAPP_MESSAGE_TEMPLATES.md (counseling steps 34–48).
 *
 * @return list<string>
 */
function afya_counseling_messages_positive_en(): array
{
    return [
        'Your HPV test was positive. This means that HPV was detected in your sample. HPV is a very common infection, and about 8 out of every 10 sexually active people will get HPV at some point in their lives. Most HPV infections clear on their own without causing health problems. However, follow-up care is important to identify and treat any changes early and help prevent cervical cancer.',
        'Follow-up care helps health providers detect and treat changes early before they become serious. Please attend your recommended clinic visit.',
        'A positive HPV result does not mean you have cervical cancer. It means that more follow-up is needed to keep you healthy.',
        'You have the ability to take important steps to protect your health. By attending your appointments and following the advice of your healthcare provider, you are helping to prevent cervical cancer.',
        'If you feel comfortable, consider sharing information about your appointment with a trusted family member or friend who can support you.',
        'Most HPV infections clear naturally. However, some infections can persist and cause changes on the cervix over time. Attending follow-up appointments helps ensure that any changes are identified and managed early.',
        'Because your HPV test is positive, the next step is an examination called Visual Assessment with Acetic acid (VIA). During VIA, a trained healthcare provider applies a special vinegar solution to the cervix and looks for any abnormal areas that may need treatment. The procedure is simple, safe, and usually takes only a few minutes.',
        'After VIA, your results may be: VIA Negative: No visible abnormal changes were found on the cervix. VIA Positive: Changes were seen on the cervix that may require treatment to prevent cervical cancer.',
        'Your HPV test can be positive, but your VIA result negative. This means that HPV was found, but no abnormal changes were seen on your cervix at this time. Most HPV infections clear on their own without causing health problems. Only a small number of women develop cervical changes that may require treatment, which is why regular follow-up screening is important. You do not need treatment at this time. Women living with HIV: Repeat HPV screening after 3 years. Women without HIV: Repeat HPV screening after 5 years. Please continue attending routine health check-ups as advised.',
        'Your HPV test can be positive, and your VIA result also positive. This means that HPV was detected and some changes were seen on your cervix that may require treatment. This does not mean that you have cervical cancer. Treatment at this stage helps remove the abnormal cells and prevents them from developing into cervical cancer in the future. If you are eligible for treatment, your healthcare provider may recommend Thermal Ablation, a simple procedure that uses heat to remove abnormal cervical cells and protect your health.',
        'If your HPV test was positive and your VIA positive (result showed changes on the cervix), your healthcare provider may recommend Thermal Ablation. Thermal Ablation is a simple treatment that uses heat to remove abnormal cells on the cervix before they can develop into cervical cancer. The procedure usually takes only a few minutes and does not require admission to hospital. Early treatment is highly effective and helps keep your cervix healthy.',
        'After Thermal Ablation, it is normal to experience: Mild watery discharge — use pad or panty liner. Mild lower abdominal discomfort. These symptoms usually improve within a few days to weeks (about 2–6 weeks).',
        'Please return to the health facility immediately if you experience: Heavy vaginal bleeding. Foul-smelling vaginal discharge. Severe lower abdominal pain. Fever or high body temperature. Any symptoms that concern you.',
        'To allow your cervix to heal: Avoid sexual intercourse for 4 weeks or as advised by your healthcare provider. Avoid inserting anything into the vagina during the healing period (e.g. tampons). Attend all scheduled follow-up appointments.',
        'After Thermal Ablation, you should return for a Test of Cure (ToC) using HPV testing after 1 year. This helps confirm that treatment was successful and that your cervix remains healthy.',
    ];
}

/** @return list<string> */
function afya_counseling_messages_positive_sw(): array
{
    return [
        'Majibu yako ya HPV yalikuwa chanya (positive). Hii inamaanisha kuwa virusi vya HPV vimepatikana kwenye sampuli yako. HPV ni maambukizi ya kawaida sana, na takribani watu 8 kati ya 10 wanaoshiriki ngono hupata HPV wakati fulani maishani mwao. Maambukizi mengi ya HPV huisha yenyewe bila kusababisha matatizo ya kiafya. Hata hivyo, huduma ya ufuatiliaji ni muhimu ili kugundua na kutibu mabadiliko yoyote mapema na kusaidia kuzuia saratani ya mlango wa kizazi.',
        'Huduma ya ufuatiliaji husaidia wahudumu wa afya kugundua na kutibu mabadiliko mapema kabla hayajawa makubwa. Tafadhali hudhuria kliniki yako kama ulivyoelekezwa.',
        'Majibu chanya (Positive) ya HPV hayamaanishi kuwa una saratani ya mlango wa kizazi. Inamaanisha kuwa ufuatiliaji zaidi unahitajika ili kulinda afya yako.',
        'Una uwezo wa kuchukua hatua muhimu za kulinda afya yako. Kwa kuhudhuria miadi yako na kufuata ushauri wa mhudumu wa afya, unasaidia kuzuia saratani ya mlango wa kizazi.',
        'Ikiwa unajisikia huru kufanya hivyo, unaweza kumshirikisha mwanafamilia au rafiki unayemwamini ili akusaidie kuhudhuria miadi yako.',
        'Maambukizi mengi ya HPV huisha yenyewe. Hata hivyo, baadhi yanaweza kuendelea kwa muda mrefu na kusababisha mabadiliko kwenye mlango wa kizazi. Kuhudhuria miadi ya ufuatiliaji husaidia kuhakikisha kuwa mabadiliko yoyote yanagunduliwa na kushughulikiwa mapema.',
        'Kwa kuwa majibu yako ya HPV ni chanya (Positive), hatua inayofuata ni uchunguzi unaoitwa Visual Assessment with Acetic acid (VIA). Wakati wa VIA, mhudumu wa afya hupaka dawa maalum ya siki kwenye mlango wa kizazi na kuangalia kama kuna sehemu zisizo za kawaida zinazohitaji matibabu. Uchunguzi huu ni salama na huchukua dakika chache tu.',
        'Baada ya VIA, matokeo yako yanaweza kuwa: VIA Hasi (Negative): Hakuna mabadiliko yasiyo ya kawaida yaliyoonekana kwenye mlango wa kizazi. VIA Chanya (Positive): Mabadiliko yalionekana kwenye mlango wa kizazi ambayo yanaweza kuhitaji matibabu ili kuzuia saratani ya mlango wa kizazi.',
        'Majibu yako ya HPV yanaweza kuwa chanya (positive), lakini matokeo ya VIA yakawa hasi (negative). Hii inamaanisha kuwa virusi vya HPV vilipatikana, lakini hakuna mabadiliko yasiyo ya kawaida yaliyoonekana kwenye mlango wa kizazi kwa sasa. Maambukizi mengi ya HPV huisha yenyewe bila kusababisha matatizo ya kiafya. Ni wanawake wachache tu hupata mabadiliko yanayohitaji matibabu, ndiyo sababu uchunguzi wa ufuatiliaji ni muhimu. Huhitaji matibabu kwa sasa. Wanawake wanaoishi na HIV: Rudia uchunguzi wa HPV baada ya miaka 3. Wanawake wasio na HIV: Rudia uchunguzi wa HPV baada ya miaka 5. Tafadhali endelea kuhudhuria huduma za afya kama ulivyoelekezwa.',
        'Majibu yako ya HPV yanaweza kuwa chanya (positive), na matokeo ya VIA pia yakawa chanya. Hii inamaanisha kuwa virusi vya HPV vilipatikana na mabadiliko fulani yalionekana kwenye mlango wa kizazi ambayo yanaweza kuhitaji matibabu. Hii haimaanishi kuwa una saratani ya mlango wa kizazi. Matibabu katika hatua hii husaidia kuondoa seli zisizo za kawaida na kuzuia zisigeuke kuwa saratani baadaye. Ikiwa unafaa kupata matibabu, mhudumu wa afya anaweza kupendekeza Thermal Ablation, matibabu rahisi yanayotumia joto kuondoa seli zisizo za kawaida kwenye mlango wa kizazi na kusaidia kulinda afya yako.',
        'Majibu yako ya HPV yakiwa chanya (positive) na matokeo ya VIA yaonyeshe mabadiliko kwenye mlango wa kizazi (VIA Positive), mhudumu wa afya anaweza kupendekeza Thermal Ablation. Thermal Ablation ni matibabu rahisi yanayotumia joto kuondoa seli zisizo za kawaida kwenye mlango wa kizazi kabla hazijageuka kuwa saratani ya mlango wa kizazi. Matibabu haya huchukua dakika chache tu na kwa kawaida hayahitaji kulazwa hospitalini. Matibabu ya mapema yanafanikiwa sana na husaidia kudumisha afya ya mlango wa kizazi.',
        'Baada ya Thermal Ablation, ni kawaida kupata: Majimaji kutoka ukeni — tumia pad au panty liner. Maumivu madogo chini ya tumbo. Dalili hizi kwa kawaida hupungua ndani ya siku au wiki chache (2–6 weeks).',
        'Tafadhali rudi hospitalini mara moja ikiwa utapata: Kutokwa na damu nyingi ukeni. Majimaji yenye harufu mbaya kutoka ukeni. Maumivu makali chini ya tumbo. Homa au joto la mwili kuongezeka. Dalili nyingine zinazokusumbua.',
        'Ili kuruhusu mlango wa kizazi kupona: Epuka kufanya ngono kwa wiki 4 au kama ulivyoelekezwa na mhudumu wa afya. Epuka kuingiza kitu chochote ukeni wakati wa kupona (k.m. tampons). Hudhuria miadi yote ya ufuatiliaji.',
        'Baada ya Thermal Ablation, unapaswa kurudi kwa kipimo cha kuthibitisha mafanikio ya matibabu (Test of Cure) kwa kutumia kipimo cha HPV baada ya mwaka 1. Hii husaidia kuthibitisha kuwa matibabu yalifanikiwa na afya ya mlango wa kizazi inaendelea kuwa nzuri.',
    ];
}
