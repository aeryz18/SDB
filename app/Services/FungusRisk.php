<?php

namespace App\Services;

use App\Models\DeviceSetting;
use Illuminate\Support\Collection;

class FungusRisk
{
    const LOW      = 'Low';
    const MODERATE = 'Moderate';
    const HIGH     = 'High';

    // ── Knowledge base (tune thresholds here) ─────────────────────────────
    private const MOULD_TEMP_MIN = 20.0;
    private const MOULD_TEMP_MAX = 30.0;

    // Rule F1 — critical spike
    private const PCT_ABOVE_80_HIGH = 10;

    // Rule F2 — sustained high humidity in mould-growth temperature (both required)
    private const PCT_ABOVE_70_HIGH_RH   = 20;
    private const PCT_IN_MOULD_TEMP_HIGH = 50;

    // Rule F3 — elevated humidity sustained
    private const PCT_ABOVE_60_MODERATE = 30;

    // Rule F4 — frequent door-opens with elevated mean RH (both required)
    private const DOOR_EVENTS_MODERATE  = 10;
    private const MEAN_RH_DOOR_MODERATE = 55.0;

    // Rule F5 — any exposure above 70% in prime mould-growth temperature (both required)
    private const PCT_ABOVE_70_PRIME_RH   = 5;
    private const PCT_IN_MOULD_TEMP_PRIME = 70;

    private const LEVEL_ORDER = [self::LOW => 0, self::MODERATE => 1, self::HIGH => 2];

    /**
     * Evaluate fungus risk using named IF-THEN rules applied to aggregate
     * statistics computed from the supplied readings collection.
     *
     * Readings must be sorted chronologically (oldest first) so that
     * door-open event detection works correctly.
     *
     * Returns:
     *   level       — Low | Moderate | High
     *   score       — 0-100 (% of readings above 60% RH, clamped to level band)
     *   fired_rules — list of rule names that fired
     *   reasons     — matching human-readable explanations
     *   stats       — raw aggregate statistics (for display / debugging)
     */
    public function evaluate(Collection $readings, DeviceSetting $settings): array
    {
        if ($readings->isEmpty()) {
            return ['level' => self::LOW, 'score' => 0, 'fired_rules' => [], 'reasons' => []];
        }

        $readings = $readings->sortBy('recorded_at')->values();
        $stats    = $this->computeStats($readings);
        $fired    = $this->applyRules($stats);

        $level = array_reduce(
            $fired,
            fn ($carry, $r) => self::LEVEL_ORDER[$r['level']] > self::LEVEL_ORDER[$carry]
                ? $r['level']
                : $carry,
            self::LOW
        );

        // Score = % of readings above 60% RH, kept within the visual band for the level
        $raw   = (int) round($stats['pct_above_60_rh']);
        $score = match ($level) {
            self::HIGH     => max(60, min(100, $raw)),
            self::MODERATE => max(25, min(59,  $raw)),
            default        => min(24, $raw),
        };

        return [
            'level'       => $level,
            'score'       => $score,
            'fired_rules' => array_column($fired, 'name'),
            'reasons'     => array_column($fired, 'reason'),
            'stats'       => $stats,
        ];
    }

    // ── Step 1: compute aggregate statistics ──────────────────────────────

    private function computeStats(Collection $readings): array
    {
        $total = $readings->count();

        $above80 = $readings->filter(fn ($r) => (float) $r->humidity >= 80)->count();
        $above70 = $readings->filter(fn ($r) => (float) $r->humidity >= 70)->count();
        $above60 = $readings->filter(fn ($r) => (float) $r->humidity >= 60)->count();
        $meanRh  = round((float) $readings->avg(fn ($r) => (float) $r->humidity), 1);

        $inMould = $readings->filter(fn ($r) =>
            $r->temperature !== null
            && (float) $r->temperature >= self::MOULD_TEMP_MIN
            && (float) $r->temperature <= self::MOULD_TEMP_MAX
        )->count();

        // Door-open EVENT detection: count closed→open transitions only
        $doorEvents = 0;
        $prev       = null;
        foreach ($readings as $r) {
            $current = strtolower(trim($r->door_state ?? ''));
            if ($current === 'open' && $prev !== 'open') {
                $doorEvents++;
            }
            $prev = $current;
        }

        return [
            'pct_above_80_rh'   => round($above80 / $total * 100, 1),
            'pct_above_70_rh'   => round($above70 / $total * 100, 1),
            'pct_above_60_rh'   => round($above60 / $total * 100, 1),
            'mean_rh'           => $meanRh,
            'pct_in_mould_temp' => round($inMould / $total * 100, 1),
            'door_open_events'  => $doorEvents,
        ];
    }

    // ── Step 2: apply named rules ─────────────────────────────────────────

    private function applyRules(array $s): array
    {
        $fired = [];

        // F1 — critical RH spike
        if ($s['pct_above_80_rh'] >= self::PCT_ABOVE_80_HIGH) {
            $fired[] = [
                'name'   => 'critical_rh_spike',
                'level'  => self::HIGH,
                'reason' => "Above 80% RH for {$s['pct_above_80_rh']}% of readings (threshold: " . self::PCT_ABOVE_80_HIGH . "%).",
            ];
        }

        // F2 — sustained high humidity + mould-growth temperature
        if ($s['pct_above_70_rh'] >= self::PCT_ABOVE_70_HIGH_RH
            && $s['pct_in_mould_temp'] >= self::PCT_IN_MOULD_TEMP_HIGH) {
            $fired[] = [
                'name'   => 'sustained_high_humidity_mould_temp',
                'level'  => self::HIGH,
                'reason' => "Above 70% RH for {$s['pct_above_70_rh']}% of readings while {$s['pct_in_mould_temp']}% were in the optimal mould-growth temperature band (" . self::MOULD_TEMP_MIN . "–" . self::MOULD_TEMP_MAX . " °C).",
            ];
        }

        // F3 — elevated humidity sustained
        if ($s['pct_above_60_rh'] >= self::PCT_ABOVE_60_MODERATE) {
            $fired[] = [
                'name'   => 'elevated_humidity_sustained',
                'level'  => self::MODERATE,
                'reason' => "Above 60% RH for {$s['pct_above_60_rh']}% of readings — fungal growth may begin at sustained levels above 60% RH.",
            ];
        }

        // F4 — frequent door-opens with elevated mean RH
        if ($s['door_open_events'] >= self::DOOR_EVENTS_MODERATE
            && $s['mean_rh'] >= self::MEAN_RH_DOOR_MODERATE) {
            $fired[] = [
                'name'   => 'frequent_door_elevated_rh',
                'level'  => self::MODERATE,
                'reason' => "{$s['door_open_events']} door-open events with mean RH of {$s['mean_rh']}% — frequent exposure to ambient air at elevated humidity.",
            ];
        }

        // F5 — any exposure above 70% in prime mould-growth temperature
        if ($s['pct_above_70_rh'] >= self::PCT_ABOVE_70_PRIME_RH
            && $s['pct_in_mould_temp'] >= self::PCT_IN_MOULD_TEMP_PRIME) {
            $fired[] = [
                'name'   => 'moderate_humidity_prime_temp',
                'level'  => self::MODERATE,
                'reason' => "Above 70% RH for {$s['pct_above_70_rh']}% of readings while {$s['pct_in_mould_temp']}% were in the prime mould-growth temperature band.",
            ];
        }

        return $fired;
    }
}
