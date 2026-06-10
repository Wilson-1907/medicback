<?php
declare(strict_types=1);

/**
 * Pre-VIA HPV positive counseling drip — official study messages 1–10 only.
 * Messages 11–16 are sent after VIA / treatment, not in this drip.
 */

require_once __DIR__ . '/afya_counseling_positive.php';

function afya_pre_via_counseling_count(string $lang = 'en'): int
{
    return count(afya_pre_via_counseling_messages($lang));
}

/** @return list<string> */
function afya_pre_via_counseling_messages(string $lang = 'en'): array
{
    $all = afya_lang($lang) === 'sw'
        ? afya_counseling_messages_positive_sw()
        : afya_counseling_messages_positive_en();
    return array_slice($all, 0, 10);
}

function afya_pre_via_counseling_message_at(int $index, string $lang = 'en'): ?string
{
    $messages = afya_pre_via_counseling_messages($lang);
    if ($index < 0 || $index >= count($messages)) {
        return null;
    }
    return $messages[$index];
}

/** Study schedule: +3h, +5h, then +1 day between remaining tips. */
function afya_pre_via_counseling_delay_before_index(int $index): string
{
    return match ($index) {
        0 => '+3 hours',
        1 => '+5 hours',
        default => '+1 day',
    };
}
