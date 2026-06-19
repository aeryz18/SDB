<?php

namespace App\Services;

use App\Models\DeviceSetting;
use Illuminate\Support\Collection;

class FungusRisk
{
    const LOW      = 'Low';
    const MODERATE = 'Moderate';
    const HIGH     = 'High';

    /**
     * Score based on how many recent readings sit above the humidity thresholds
     * while the temperature is in the prime mould-growth band (20–35 °C).
     * Returns ['level' => Low|Moderate|High, 'score' => 0-100].
     */
    public function evaluate(Collection $readings, DeviceSetting $settings): array
    {
        if ($readings->isEmpty()) {
            return ['level' => self::LOW, 'score' => 0];
        }

        $total     = $readings->count();
        $aboveWarn = $readings->filter(fn ($r) => (float) $r->humidity > $settings->warn_humidity)->count();
        $aboveCrit = $readings->filter(fn ($r) => (float) $r->humidity > $settings->crit_humidity)->count();
        $moldyTemp = $readings->filter(fn ($r) => (float) $r->temperature >= 20 && (float) $r->temperature <= 35)->count();

        $humidityScore  = ($aboveCrit / $total) * 60 + ($aboveWarn / $total) * 30;
        $tempMultiplier = 0.5 + ($moldyTemp / $total) * 0.5;
        $score          = (int) min(100, $humidityScore * $tempMultiplier);

        return [
            'level' => match (true) {
                $score >= 60 => self::HIGH,
                $score >= 25 => self::MODERATE,
                default      => self::LOW,
            },
            'score' => $score,
        ];
    }
}
