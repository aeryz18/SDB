<?php

namespace App\Services;

use App\Models\Device;
use App\Models\DeviceSetting;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiMonthlyReportAdvisor
{
    /**
     * Ask Gemini for a plain-language summary of a device's month and any
     * evidence-backed improvement suggestions (relocation, replacement
     * cadence, etc). On-demand only — no persistence; re-runs live each
     * time the monthly report is sent.
     */
    public function summarize(Device $device, Carbon $from, Carbon $to): array
    {
        $apiKey = config('services.gemini.api_key');
        $model = config('services.gemini.model');

        if (! $apiKey) {
            throw new RuntimeException('GEMINI_API_KEY is not configured.');
        }

        $settings = $device->settings;

        // $from/$to carry the display timezone (used for the human-readable
        // prompt text below) — recorded_at/created_at are stored in UTC, so
        // queries need their own UTC-converted copies, same pattern as
        // ReportGenerator::generate().
        $fromUtc = $from->copy()->setTimezone('UTC');
        $toUtc = $to->copy()->setTimezone('UTC');

        $stats = $device->readings()
            ->whereBetween('recorded_at', [$fromUtc, $toUtc])
            ->selectRaw('AVG(humidity) avg_h, MAX(humidity) max_h, MIN(humidity) min_h, AVG(temperature) avg_t, MAX(temperature) max_t, MIN(temperature) min_t, COUNT(*) cnt')
            ->first();

        $alertCounts = $device->alerts()
            ->whereBetween('created_at', [$fromUtc, $toUtc])
            ->selectRaw('type, COUNT(*) c')
            ->groupBy('type')
            ->pluck('c', 'type');

        $history = $device->silicaReplacements()->orderByDesc('replaced_at')->limit(12)->get();

        $response = Http::timeout(20)
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}", [
                'contents' => [
                    ['role' => 'user', 'parts' => [['text' => $this->buildPrompt($device, $settings, $from, $to, $stats, $alertCounts, $history)]]],
                ],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'temperature' => 0.4,
                ],
            ])
            ->throw()
            ->json();

        $text = $response['candidates'][0]['content']['parts'][0]['text'] ?? null;

        return $this->parseResponse($text);
    }

    public function buildPrompt(Device $device, ?DeviceSetting $settings, Carbon $from, Carbon $to, object $stats, Collection $alertCounts, Collection $history): string
    {
        $avgH = $stats->avg_h !== null ? round((float) $stats->avg_h, 1) : 'n/a';
        $maxH = $stats->max_h !== null ? round((float) $stats->max_h, 1) : 'n/a';
        $minH = $stats->min_h !== null ? round((float) $stats->min_h, 1) : 'n/a';
        $avgT = $stats->avg_t !== null ? round((float) $stats->avg_t, 1) : 'n/a';
        $maxT = $stats->max_t !== null ? round((float) $stats->max_t, 1) : 'n/a';
        $minT = $stats->min_t !== null ? round((float) $stats->min_t, 1) : 'n/a';

        $warnH = $settings?->warn_humidity ?? 'n/a';
        $critH = $settings?->crit_humidity ?? 'n/a';
        $tempMin = $settings?->temp_min ?? 'n/a';
        $tempMax = $settings?->temp_max ?? 'n/a';
        $interval = $settings?->silica_interval_days ?? 90;

        $alertLines = $alertCounts->isEmpty()
            ? 'No alerts fired this month.'
            : $alertCounts->map(fn ($count, $type) => "- {$type}: {$count}")->implode("\n");

        $historyLines = $history->isEmpty()
            ? 'No replacement history logged yet.'
            : $history->map(fn ($h) => sprintf(
                '- %s%s',
                $h->replaced_at->format('Y-m-d'),
                $h->interval_days_actual !== null ? " (lasted {$h->interval_days_actual} days)" : ' (first recorded replacement)',
            ))->implode("\n");

        return <<<PROMPT
        You are writing the narrative section of a condition report for an IoT smart camera dry-storage box named "{$device->name}"{$this->locationSuffix($device)}. The reporting period may be any length (a scheduled month, or an on-demand range the user picked) — don't assume it's a calendar month.

        Reporting period: {$from->format('d M Y')} to {$to->format('d M Y')}.

        Configured thresholds: humidity warn {$warnH}%, humidity critical {$critH}%, temperature range {$tempMin}–{$tempMax}°C, silica replacement interval {$interval} days.

        Sensor summary for this period ({$stats->cnt} readings):
        - Humidity: avg {$avgH}%, min {$minH}%, peak {$maxH}%
        - Temperature: avg {$avgT}°C, min {$minT}°C, peak {$maxT}°C

        Alerts fired this period, by type:
        {$alertLines}

        Silica gel replacement history (most recent first):
        {$historyLines}

        Write a 2-4 sentence plain-language summary of how the box performed this month, aimed at the device owner (not an engineer).

        Then list 0-4 short, specific, actionable improvement suggestions — but only ones actually supported by the data above (e.g. sustained humidity near/above the warn threshold might suggest relocating away from a humid area; silica replacements consistently lasting fewer days than the configured interval might suggest shortening the interval; frequent tamper alerts might suggest checking the door seal or placement). Do not invent generic advice that isn't backed by evidence in the data. If nothing notable stands out, return an empty suggestions array rather than padding it with filler.

        Respond with ONLY a JSON object in exactly this shape, no markdown fences, no extra text:
        {"summary": "...", "suggestions": ["...", "..."]}
        PROMPT;
    }

    private function locationSuffix(Device $device): string
    {
        return $device->location ? " located in {$device->location}" : '';
    }

    public function parseResponse(?string $text): array
    {
        if (! $text) {
            throw new RuntimeException('Gemini returned an empty response.');
        }

        $decoded = json_decode(trim($text), true);

        if (! is_array($decoded) || ! isset($decoded['summary'])) {
            throw new RuntimeException('Gemini response was not in the expected JSON shape.');
        }

        $suggestions = $decoded['suggestions'] ?? [];

        return [
            'summary' => $decoded['summary'],
            'suggestions' => is_array($suggestions) ? array_values($suggestions) : [],
        ];
    }
}
