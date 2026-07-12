<?php

namespace App\Services;

use App\Models\Device;
use App\Models\DeviceSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiSilicaAdvisor
{
    /**
     * Ask Gemini to suggest a next silica gel replacement date, from the
     * device's replacement log and recent sensor readings. On-demand only —
     * no scheduled job, no persistence; re-runs live each time it's called.
     */
    public function suggest(Device $device): array
    {
        $apiKey = config('services.gemini.api_key');
        $model = config('services.gemini.model');

        if (! $apiKey) {
            throw new RuntimeException('GEMINI_API_KEY is not configured.');
        }

        $settings = $device->settings;
        $history = $device->silicaReplacements()->orderByDesc('replaced_at')->limit(12)->get();

        $since = $settings?->silica_last_replaced_at ?? now()->subDays(90);
        $stats = $device->readings()
            ->where('recorded_at', '>=', $since)
            ->selectRaw('AVG(humidity) avg_h, MAX(humidity) max_h, AVG(temperature) avg_t, MAX(temperature) max_t, COUNT(*) cnt')
            ->first();

        $response = Http::timeout(20)
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}", [
                'contents' => [
                    ['role' => 'user', 'parts' => [['text' => $this->buildPrompt($device, $settings, $history, $stats)]]],
                ],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'temperature' => 0.3,
                ],
            ])
            ->throw()
            ->json();

        $text = $response['candidates'][0]['content']['parts'][0]['text'] ?? null;

        return $this->parseResponse($text);
    }

    public function buildPrompt(Device $device, ?DeviceSetting $settings, Collection $history, object $stats): string
    {
        $interval = $settings?->silica_interval_days ?? 90;

        $historyLines = $history->isEmpty()
            ? 'No replacement history logged yet.'
            : $history->map(fn ($h) => sprintf(
                '- %s%s',
                $h->replaced_at->format('Y-m-d'),
                $h->interval_days_actual !== null ? " (lasted {$h->interval_days_actual} days)" : ' (first recorded replacement)',
            ))->implode("\n");

        $avgH = $stats->avg_h !== null ? round((float) $stats->avg_h, 1) : 'n/a';
        $maxH = $stats->max_h !== null ? round((float) $stats->max_h, 1) : 'n/a';
        $avgT = $stats->avg_t !== null ? round((float) $stats->avg_t, 1) : 'n/a';
        $maxT = $stats->max_t !== null ? round((float) $stats->max_t, 1) : 'n/a';

        $today = now()->format('Y-m-d');

        $warnHumidity = $settings?->warn_humidity ?? 35;
        $critHumidity = $settings?->crit_humidity ?? 45;

        return <<<PROMPT
        You are assisting with silica gel desiccant replacement scheduling for an IoT dry-storage box named "{$device->name}".

        Today's date is {$today}. This suggestion is being requested while replacing the silica gel today, so the date you suggest is for the NEXT replacement after today's — it must be strictly after today, never today itself, even if the current replacement is overdue (being overdue explains why the box needed replacing today, not when the next one is due).

        The user's currently configured interval is {$interval} days — treat this only as a fallback reference point, not as the answer. Your job is to independently recommend the best date based on the evidence below, which may be shorter, longer, or the same as {$interval} days.

        Replacement history (most recent first):
        {$historyLines}

        Sensor summary since last replacement:
        - Average humidity: {$avgH}%
        - Peak humidity: {$maxH}%
        - Average temperature: {$avgT}°C
        - Peak temperature: {$maxT}°C
        - Readings analyzed: {$stats->cnt}
        - Configured thresholds: warning at {$warnHumidity}%, critical at {$critHumidity}%

        Weigh the evidence, don't just default to today + {$interval} days:
        - If replacement history shows a consistent pattern across multiple entries of the gel lasting notably shorter or longer than {$interval} days, factor that in.
        - Treat very sparse or clearly anomalous history (e.g., only 1-2 entries, or intervals of just a day or two) with skepticism — that's more likely leftover testing/demo activity than a real desiccant lifecycle pattern. Don't let it dominate your answer, but do mention the uncertainty in your reasoning.
        - If peak humidity is at or above the warning/critical threshold, that's a genuine physical signal the gel may be saturating faster than the interval assumes, even if the average looks fine — weigh peaks, not just the average.

        Based on this data, suggest the next silica gel replacement date and briefly explain why in 2-3 sentences.
        Respond with ONLY a JSON object in exactly this shape, no markdown fences, no extra text:
        {"suggested_date": "YYYY-MM-DD", "reasoning": "..."}
        PROMPT;
    }

    public function parseResponse(?string $text): array
    {
        if (! $text) {
            throw new RuntimeException('Gemini returned an empty response.');
        }

        $decoded = json_decode(trim($text), true);

        if (! is_array($decoded) || ! isset($decoded['suggested_date'])) {
            throw new RuntimeException('Gemini response was not in the expected JSON shape.');
        }

        return [
            'suggested_date' => $decoded['suggested_date'],
            'reasoning' => $decoded['reasoning'] ?? '',
        ];
    }
}
