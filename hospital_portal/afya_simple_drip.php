<?php
declare(strict_types=1);

/**
 * Option A — pre-VIA encouragement drip mapped to approved WhatsApp FAQ templates:
 * afya_faq_hpv → afya_faq_cancer → afya_faq_treat → afya_engagement_tip (×7).
 * VIA results use official counsel_pos_09/10 separately (not this drip).
 */

/** @return list<string> */
function afya_simple_encouragement_drip_en(): array
{
    $engagement = 'Your health matters. You have taken a good step by following up on your health. '
        . 'Reply HELP if you have a question or DOCTOR to speak with a provider. Afya Rafiki — Nyeri Town Health Center.';

    return [
        'HPV is a common virus that can affect the cervix. Some types may cause cervical cancer if not treated early. Follow-up care helps protect your health.',
        'A positive HPV result does not mean you have cervical cancer. It means you have HPV virus. Additional follow-up care is needed. Please attend your clinic appointment.',
        'HPV infections often clear naturally. Follow-up care helps health providers monitor and manage any cervical changes early.',
        $engagement,
        $engagement,
        $engagement,
        $engagement,
        $engagement,
        $engagement,
        $engagement,
    ];
}

/** @return list<string> */
function afya_simple_encouragement_drip_sw(): array
{
    $engagement = 'Afya yako ni muhimu. Umechukua hatua nzuri kwa kufuatilia afya yako. '
        . 'Jibu HELP kwa maswali au DOCTOR kwa mhudumu wa afya. Afya Rafiki — Nyeri Town Health Center.';

    return [
        'HPV ni virusi vya kawaida vinavyoweza kuathiri mlango wa kizazi. Aina zingine zinaweza kusababisha saratani ya mlango wa kizazi zisipotibiwa mapema. Huduma ya ufuatiliaji husaidia kulinda afya yako.',
        'Majibu chanya ya HPV hayamaanishi kuwa una saratani. Inamaanisha una virusi vya HPV. Huduma zaidi ya ufuatiliaji inahitajika. Tafadhali hudhuria miadi yako ya kliniki.',
        'Maambukizi ya HPV mara nyingi hupotea yenyewe. Huduma ya ufuatiliaji husaidia wahudumu wa afya kufuatilia na kutibu mabadiliko mapema.',
        $engagement,
        $engagement,
        $engagement,
        $engagement,
        $engagement,
        $engagement,
        $engagement,
    ];
}

/** @return list<string> */
function afya_simple_encouragement_drip(string $lang = 'en'): array
{
    return afya_lang($lang) === 'sw'
        ? afya_simple_encouragement_drip_sw()
        : afya_simple_encouragement_drip_en();
}
