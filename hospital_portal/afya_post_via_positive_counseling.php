<?php
declare(strict_types=1);

/**
 * Post-VIA positive counseling drip — official study messages 5–9 (Thermal Ablation series).
 * Sent after VIA positive result notification; not before the VIA test.
 */

require_once __DIR__ . '/afya_counseling_positive.php';

/** Study counseling messages 5–9 = PHP indices 10–14. */
const AFYA_POST_VIA_POSITIVE_COUNSELING_START_INDEX = 10;
const AFYA_POST_VIA_POSITIVE_COUNSELING_COUNT = 5;

function afya_post_via_positive_counseling_count(string $lang = 'en'): int
{
    return count(afya_post_via_positive_counseling_messages($lang));
}

/** @return list<string> */
function afya_post_via_positive_counseling_messages(string $lang = 'en'): array
{
    $all = afya_lang($lang) === 'sw'
        ? afya_counseling_messages_positive_sw()
        : afya_counseling_messages_positive_en();
    return array_slice(
        $all,
        AFYA_POST_VIA_POSITIVE_COUNSELING_START_INDEX,
        AFYA_POST_VIA_POSITIVE_COUNSELING_COUNT
    );
}

function afya_post_via_positive_counseling_message_at(int $index, string $lang = 'en'): ?string
{
    $messages = afya_post_via_positive_counseling_messages($lang);
    if ($index < 0 || $index >= count($messages)) {
        return null;
    }
    return $messages[$index];
}

/** +2 min after prior message, then +21 h between TA education steps. */
function afya_post_via_positive_counseling_delay_before_index(int $index): string
{
    return $index <= 1 ? '+2 minutes' : '+21 hours';
}
