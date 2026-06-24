<?php
declare(strict_types=1);

/**
 * Pre-VIA HPV positive counseling drip — official study messages 1–8 only.
 * Stops at "Understanding VIA results". Outcome-specific and TA messages follow VIA.
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
    return array_slice($all, 0, 8);
}

function afya_pre_via_counseling_message_at(int $index, string $lang = 'en'): ?string
{
    $messages = afya_pre_via_counseling_messages($lang);
    if ($index < 0 || $index >= count($messages)) {
        return null;
    }
    return $messages[$index];
}

/** All 8 pre-VIA counseling messages must complete within 6.5 days of HPV+ confirm. */
const AFYA_PRE_VIA_COUNSELING_MAX_SPAN_DAYS = 6.5;

/**
 * Study schedule after HPV+ confirm:
 * msg1 immediate on confirm, msg2 +2min after msg1, msg3 +1h after msg2,
 * msgs 4–8 +21h each.
 */
function afya_pre_via_counseling_delay_before_index(int $index): string
{
    return match ($index) {
        0 => '+2 minutes',
        1 => '+2 minutes',
        2 => '+1 hour',
        default => '+21 hours',
    };
}

/** Minutes from HPV+ confirm until message at $index is due (sum of prior delays). */
function afya_pre_via_counseling_delay_to_minutes(string $delayExpression): int
{
    $base = strtotime('2026-01-01 00:00:00');
    $ts = strtotime($delayExpression, $base);
    if ($ts === false || $base === false) {
        return 0;
    }

    return (int) max(0, round(($ts - $base) / 60));
}

/** Total minutes from confirm until message 10 is scheduled (must be ≤ 6.5 days). */
function afya_pre_via_counseling_total_span_minutes(): int
{
    $total = 0;
    $count = afya_pre_via_counseling_count('en');
    for ($i = 0; $i < $count; $i++) {
        $total += afya_pre_via_counseling_delay_to_minutes(
            afya_pre_via_counseling_delay_before_index($i)
        );
    }

    return $total;
}

function afya_pre_via_counseling_within_max_span(): bool
{
    $maxMinutes = (int) round(AFYA_PRE_VIA_COUNSELING_MAX_SPAN_DAYS * 24 * 60);

    return afya_pre_via_counseling_total_span_minutes() <= $maxMinutes;
}
