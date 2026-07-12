<?php

namespace App\Jobs;

use App\Models\Device;
use App\Services\ChartRenderer;
use App\Services\GeminiMonthlyReportAdvisor;
use App\Services\Mail\GmailApiSender;
use App\Services\ReportGenerator;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendOnDemandReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        private readonly int $deviceId,
        private readonly string $recipientEmail,
        private readonly string $from,
        private readonly string $to,
        private readonly bool $includeCsv,
    ) {}

    public function handle(GmailApiSender $sender, ReportGenerator $reportGenerator, ChartRenderer $chartRenderer, GeminiMonthlyReportAdvisor $advisor): void
    {
        $device = Device::with(['user', 'settings'])->find($this->deviceId);

        if (! $device) {
            return;
        }

        $owner = $device->user;

        if (! $owner->google_refresh_token) {
            Log::warning("No Gmail refresh token for user {$owner->id} — on-demand report skipped.");

            return;
        }

        $displayTz = config('app.display_timezone');
        $from = Carbon::parse($this->from, $displayTz)->startOfDay();
        $to = Carbon::parse($this->to, $displayTz)->endOfDay();
        $fromUtc = $from->copy()->setTimezone('UTC');
        $toUtc = $to->copy()->setTimezone('UTC');

        $readings = $device->readings()
            ->whereBetween('recorded_at', [$fromUtc, $toUtc])
            ->orderBy('recorded_at')
            ->get();

        $tempReadings = $readings->whereNotNull('temperature');
        $humReadings = $readings->whereNotNull('humidity');

        $stats = [
            'avg_temp' => $tempReadings->isNotEmpty() ? round((float) $tempReadings->avg(fn ($r) => (float) $r->temperature), 1) : null,
            'peak_temp' => $tempReadings->isNotEmpty() ? round((float) $tempReadings->max(fn ($r) => (float) $r->temperature), 1) : null,
            'avg_humidity' => $humReadings->isNotEmpty() ? round((float) $humReadings->avg(fn ($r) => (float) $r->humidity), 1) : null,
            'peak_humidity' => $humReadings->isNotEmpty() ? round((float) $humReadings->max(fn ($r) => (float) $r->humidity), 1) : null,
            'count' => $readings->count(),
        ];

        // Hourly averages, same aggregation the Analytics page's chart uses —
        // plotting every raw reading makes for a much noisier line.
        $chartPoints = $readings
            ->groupBy(fn ($r) => $r->recorded_at->copy()->timezone($displayTz)->format('Y-m-d H:00:00'))
            ->map(function ($group, $hourKey) use ($displayTz) {
                $hourHum = $group->whereNotNull('humidity');
                $hourTemp = $group->whereNotNull('temperature');

                return [
                    'time' => Carbon::parse($hourKey, $displayTz),
                    'humidity' => $hourHum->isNotEmpty() ? round((float) $hourHum->avg(fn ($r) => (float) $r->humidity), 1) : null,
                    'temperature' => $hourTemp->isNotEmpty() ? round((float) $hourTemp->avg(fn ($r) => (float) $r->temperature), 1) : null,
                ];
            })
            ->values();

        $chartPng = $chartRenderer->humidityTemperatureChart(
            $chartPoints,
            $device->settings?->warn_humidity !== null ? (float) $device->settings->warn_humidity : null,
            $device->settings?->crit_humidity !== null ? (float) $device->settings->crit_humidity : null,
        );

        try {
            $ai = $advisor->summarize($device, $from, $to);
        } catch (Throwable $e) {
            Log::warning("AI summary unavailable for on-demand report, device {$device->id}: {$e->getMessage()}");
            $ai = null;
        }

        $html = view('emails.on-demand-report', [
            'device' => $device,
            'from' => $from,
            'to' => $to,
            'stats' => $stats,
            'chartDataUri' => 'data:image/png;base64,'.base64_encode($chartPng),
            'includeCsv' => $this->includeCsv,
            'aiSummary' => $ai['summary'] ?? null,
            'aiSuggestions' => $ai['suggestions'] ?? [],
        ])->render();

        $subject = "[Incognito] Condition Report — {$device->name} ({$from->format('d M Y')} to {$to->format('d M Y')})";

        try {
            if ($this->includeCsv) {
                $sender->sendWithAttachment(
                    fromEmail: $owner->email,
                    refreshToken: $owner->google_refresh_token,
                    to: $this->recipientEmail,
                    subject: $subject,
                    htmlBody: $html,
                    attachmentFilename: $reportGenerator->filename($device, $from, $to),
                    attachmentContent: $reportGenerator->generate($device, $from, $to),
                    attachmentMimeType: 'text/csv',
                );
            } else {
                $sender->send(
                    fromEmail: $owner->email,
                    refreshToken: $owner->google_refresh_token,
                    to: $this->recipientEmail,
                    subject: $subject,
                    htmlBody: $html,
                );
            }
        } catch (Throwable $e) {
            if (str_contains($e->getMessage(), 'invalid_grant')) {
                $owner->update(['google_refresh_token' => null]);
                Log::warning("Gmail token revoked for user {$owner->id}. User must reconnect Google.");

                return;
            }
            throw $e;
        }
    }
}
