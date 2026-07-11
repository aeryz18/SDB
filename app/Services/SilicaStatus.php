<?php

namespace App\Services;

use App\Models\DeviceSetting;

class SilicaStatus
{
    // Fallback when a device has no silica_notify_days_before set yet
    // (e.g. pre-existing rows from before this column existed).
    private const DEFAULT_NOTIFY_DAYS_BEFORE = 7;

    private const DEFAULT_INTERVAL_DAYS = 90;

    /**
     * Evaluate silica gel replacement status from a device's settings.
     *
     * Resolves a single due date: a manual silica_next_replacement_at override
     * takes precedence when set, otherwise it falls back to
     * silica_last_replaced_at + silica_interval_days.
     *
     * "warning" (and, correspondingly, the once-per-cycle silica_upcoming
     * email in PollDeviceData) is driven by the single per-device
     * silica_notify_days_before setting — there is no separate UI vs. email
     * threshold anymore; the user sets one "notify me N days before" value
     * on the replace form and it drives both.
     *
     * Returns:
     *   replaced           — bool: has a replacement ever been logged
     *   replaced_at        — ?Carbon
     *   interval            — int: configured interval in days
     *   notify_days_before  — int: days-before-due lead time that drives `warning`
     *   days_since          — int: days since last replacement (interval+1 if never replaced) — always interval-derived, for the progress bar
     *   days_left           — int: days until the resolved due date; negative when overdue (signed, not clamped)
     *   due                 — bool: days_left <= 0
     *   warning             — bool: !due && days_left <= notify_days_before
     *   bar_pct             — int 0-100: progress bar fill (always interval-derived)
     *   due_date            — ?Carbon: the resolved due date, for display
     *   source              — 'manual' | 'interval' | 'unset'
     */
    public function evaluate(?DeviceSetting $settings): array
    {
        $replacedAt = $settings?->silica_last_replaced_at;
        $nextReplacementAt = $settings?->silica_next_replacement_at;
        $interval = (int) ($settings?->silica_interval_days ?: self::DEFAULT_INTERVAL_DAYS);
        $notifyDaysBefore = (int) ($settings?->silica_notify_days_before ?: self::DEFAULT_NOTIFY_DAYS_BEFORE);

        if ($nextReplacementAt) {
            $dueDate = $nextReplacementAt;
            $source = 'manual';
        } elseif ($replacedAt) {
            $dueDate = $replacedAt->copy()->addDays($interval);
            $source = 'interval';
        } else {
            $dueDate = null;
            $source = 'unset';
        }

        // days_since / bar_pct stay interval-based & informational — the
        // progress bar reflects elapsed time since last replacement even
        // while a manual override date is active.
        // Carbon 3 made diffInDays() signed by default — force absolute so a
        // past $replacedAt yields a positive "days since", not a negative one.
        $daysSince = $replacedAt ? (int) now()->diffInDays($replacedAt, absolute: true) : $interval + 1;
        $barPct = $replacedAt ? min(100, max(0, (int) round($daysSince / $interval * 100))) : 100;

        // Unsigned Carbon 3 convention: $a->diffInDays($b) = $b - $a, so
        // now()->diffInDays($dueDate, absolute: false) is positive when
        // $dueDate is in the future, negative when past — exactly "days
        // remaining". absolute:false is explicit so a future Carbon default
        // change can't silently flip this again.
        $daysLeft = $dueDate ? (int) now()->diffInDays($dueDate, absolute: false) : -1;
        $due = $daysLeft <= 0;
        $warning = ! $due && $daysLeft <= $notifyDaysBefore;

        return [
            'replaced' => (bool) $replacedAt,
            'replaced_at' => $replacedAt,
            'interval' => $interval,
            'notify_days_before' => $notifyDaysBefore,
            'days_since' => $daysSince,
            'days_left' => $daysLeft,
            'due' => $due,
            'warning' => $warning,
            'bar_pct' => $barPct,
            'due_date' => $dueDate,
            'source' => $source,
        ];
    }
}
