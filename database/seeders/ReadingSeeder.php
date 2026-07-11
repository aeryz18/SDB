<?php

namespace Database\Seeders;

use App\Models\Reading;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Backfills 5-minute-interval dummy readings for May and June 2026 against
 * the first user's first device, so the monthly report and the silica-drift
 * detector have real history to evaluate. May is deliberately calm (low,
 * stable humidity); June deliberately escalates into humid/stormy conditions
 * with sustained high-RH periods, so the two monthly reports look visibly
 * different. Run standalone:
 *   php artisan db:seed --class=ReadingSeeder
 */
class ReadingSeeder extends Seeder
{
    private const INTERVAL_MINUTES = 5;

    public function run(): void
    {
        $user = User::first();

        if (! $user) {
            $this->command?->warn('No user found — skipping ReadingSeeder.');

            return;
        }

        $device = $user->devices()->first();

        if (! $device) {
            $this->command?->warn('No device found for user — skipping ReadingSeeder.');

            return;
        }

        $rangeStart = Carbon::create(2026, 5, 1, 0, 0, 0);
        $rangeEnd = Carbon::create(2026, 6, 30, 23, 59, 59);

        Reading::where('device_id', $device->id)
            ->whereBetween('recorded_at', [$rangeStart, $rangeEnd])
            ->delete();

        $this->seedMonth($device->id, 2026, 5, calm: true);
        $this->seedMonth($device->id, 2026, 6, calm: false);

        $this->command?->info("Seeded dummy May+June 2026 readings for device #{$device->id} ({$device->name}).");
    }

    private function seedMonth(int $deviceId, int $year, int $month, bool $calm): void
    {
        $start = Carbon::create($year, $month, 1, 0, 0, 0);
        $end = $start->copy()->endOfMonth();

        // Spread a handful of "storm" days evenly through the humid month —
        // sustained 80-95% RH for a 10h window pushes readings into sustained
        // high-humidity territory, to exercise the humidity charts and the
        // silica drift detector with realistic extremes.
        $stormDays = [];
        if (! $calm) {
            $daysInMonth = $start->daysInMonth;
            $numStorms = 13;
            for ($i = 0; $i < $numStorms; $i++) {
                $stormDays[1 + intdiv($i * $daysInMonth, $numStorms)] = true;
            }
        }

        $doorEventsPerDay = $calm ? 2 : 5;

        $buffer = [];
        $cursor = $start->copy();
        $openUntil = null;

        while ($cursor->lte($end)) {
            $day = (int) $cursor->format('j');
            $hour = (int) $cursor->format('G');
            $isStorm = ! $calm && isset($stormDays[$day]) && $hour >= 10 && $hour < 20;

            // Temperature: gentle daily wave, always inside the 20-30C mould band
            // so risk differences come purely from humidity/door activity.
            $temp = 25 + sin(($hour / 24) * 2 * M_PI) * 2 + $this->noise(0.6);
            $temp = max(21.0, min(29.0, $temp));

            if ($calm) {
                // May: baseline well under the warn/crit humidity thresholds.
                $hum = 46 + sin((($hour + 6) / 24) * 2 * M_PI) * 5 + $this->noise(3);
                $hum = max(30.0, min(58.0, $hum));
            } elseif ($isStorm) {
                // June storm window: consistently >80% RH.
                $hum = 90 + $this->noise(4);
                $hum = max(80.0, min(98.0, $hum));
            } else {
                // June baseline: elevated, with a tail that crosses 70%.
                $hum = 65 + sin((($hour + 6) / 24) * 2 * M_PI) * 4 + $this->noise(5);
                $hum = max(50.0, min(78.0, $hum));
            }

            // Door state: short open windows spread through daytime hours.
            $doorState = 'closed';
            if ($openUntil !== null) {
                if ($cursor->lt($openUntil)) {
                    $doorState = 'open';
                } else {
                    $openUntil = null;
                }
            }
            if ($openUntil === null && $this->shouldOpenDoor($cursor, $doorEventsPerDay)) {
                $doorState = 'open';
                $openUntil = $cursor->copy()->addMinutes(15);
            }

            $status = $hum > 45 ? 'crit' : ($hum > 35 ? 'warn' : 'normal');

            $buffer[] = [
                'device_id' => $deviceId,
                'temperature' => round($temp, 2),
                'humidity' => round($hum, 2),
                'status' => $status,
                'door_state' => $doorState,
                'recorded_at' => $cursor->format('Y-m-d H:i:s'),
            ];

            if (count($buffer) >= 2000) {
                Reading::insert($buffer);
                $buffer = [];
            }

            $cursor->addMinutes(self::INTERVAL_MINUTES);
        }

        if (! empty($buffer)) {
            Reading::insert($buffer);
        }
    }

    private function noise(float $range): float
    {
        return (mt_rand(-1000, 1000) / 1000) * $range;
    }

    private function shouldOpenDoor(Carbon $cursor, int $perDay): bool
    {
        $hour = (int) $cursor->format('G');
        if ($hour < 7 || $hour >= 21) {
            return false;
        }

        $slotsPerDay = (21 - 7) * (60 / self::INTERVAL_MINUTES);
        $probability = $perDay / $slotsPerDay;

        return (mt_rand(0, 999) / 999) < $probability;
    }
}
