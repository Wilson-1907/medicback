<?php
declare(strict_types=1);

/**
 * Short, encouraging Afya Rafiki tips — not the long clinical counseling script.
 * Used for post-registration and HPV-positive follow-up drips.
 *
 * @return list<string>
 */
function afya_simple_encouragement_drip_en(): array
{
    return [
        'What is HPV? HPV is a very common virus. Most infections clear on their own. Regular follow-up protects your health.',
        'A positive HPV result does not mean cervical cancer. Attend your clinic visit — we are here for you.',
        'HPV infections often clear naturally. Follow-up helps your provider spot any changes early.',
        'You have taken a good step by caring for your cervical health. Reply HELP if you have a question.',
        'Most people with HPV have no symptoms. Screening matters even when you feel well.',
        'Your next clinic visit is important. Please attend as scheduled — it helps protect your health.',
        'Feeling worried is normal. A positive result means follow-up care, not a cancer diagnosis.',
        'What is VIA? A quick clinic exam after HPV positive — your nurse will explain your result the same day.',
        'If you need help with transport or fear about your visit, reply DOCTOR — a health worker can call you.',
        'You are not alone on this journey. Afya Rafiki — Nyeri Town Health Center is here to support you.',
    ];
}

/** @return list<string> */
function afya_simple_encouragement_drip_sw(): array
{
    return [
        'HPV ni nini? HPV ni virusi vya kawaida sana. Maambukizi mengi huisha yenyewe. Ufuatiliaji wa mara kwa mara unalinda afya yako.',
        'Majibu chanya ya HPV hayamaanishi saratani. Hudhuria kliniki yako — tuko hapa kukusaidia.',
        'Maambukizi ya HPV mara nyingi hupotea yenyewe. Ufuatiliaji husaidia kugundua mabadiliko mapema.',
        'Umechukua hatua nzuri kwa kujali afya ya mlango wa kizazi. Jibu HELP ikiwa una swali.',
        'Watu wengi wenye HPV hawana dalili. Uchunguzi ni muhimu hata ukiwa na afya njema.',
        'Ziara yako ijayo ya kliniki ni muhimu. Tafadhali hudhuria kama ulivyopangiwa.',
        'Wasiwasi ni kawaida. Matokeo chanya yanamaanisha ufuatiliaji, si utambuzi wa saratani.',
        'VIA ni nini? Uchunguzi mfupi baada ya HPV chanya — mhudumu atakueleza matokeo siku hiyo hiyo.',
        'Ikiwa unahitaji msaada wa usafiri au una hofu, jibu DOCTOR — mhudumu anaweza kukupigia simu.',
        'Huko peke yako. Afya Rafiki — Nyeri Town Health Center iko pamoja nawe.',
    ];
}

/** @return list<string> */
function afya_simple_encouragement_drip(string $lang = 'en'): array
{
    return afya_lang($lang) === 'sw'
        ? afya_simple_encouragement_drip_sw()
        : afya_simple_encouragement_drip_en();
}
