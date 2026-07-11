<?php

namespace App\Jobs;

use App\Models\Device;
use App\Services\Mail\GmailApiSender;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendMonthlyReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        private readonly int $deviceId,
        private readonly string $recipientEmail,
        private readonly string $from,
        private readonly string $to,
    ) {}

    public function handle(GmailApiSender $sender): void
    {
        $device = Device::with(['user', 'settings'])->find($this->deviceId);

        if (! $device) {
            return;
        }

        $owner = $device->user;

        if (! $owner->google_refresh_token) {
            Log::warning("No Gmail refresh token for user {$owner->id} — monthly report skipped.");

            return;
        }

        $from = Carbon::parse($this->from)->startOfDay();
        $to = Carbon::parse($this->to)->endOfDay();

        $readings = $device->readings()->whereBetween('recorded_at', [$from, $to])->orderBy('recorded_at')->get();

        $tempReadings = $readings->whereNotNull('temperature');
        $humReadings = $readings->whereNotNull('humidity');
        $doorEvents = $this->countDoorEvents($readings);

        $stats = [
            'avg_temp' => $tempReadings->isNotEmpty() ? round((float) $tempReadings->avg(fn ($r) => (float) $r->temperature), 1) : null,
            'peak_temp' => $tempReadings->isNotEmpty() ? round((float) $tempReadings->max(fn ($r) => (float) $r->temperature), 1) : null,
            'avg_humidity' => $humReadings->isNotEmpty() ? round((float) $humReadings->avg(fn ($r) => (float) $r->humidity), 1) : null,
            'peak_humidity' => $humReadings->isNotEmpty() ? round((float) $humReadings->max(fn ($r) => (float) $r->humidity), 1) : null,
            'avg_door_opens_per_day' => round($doorEvents / max(1, $from->daysInMonth), 1),
        ];

        $html = view('emails.monthly-report', [
            'device' => $device,
            'from' => $from,
            'to' => $to,
            'stats' => $stats,
        ])->render();

        try {
            $sender->send(
                fromEmail: $owner->email,
                refreshToken: $owner->google_refresh_token,
                to: $this->recipientEmail,
                subject: "[DryBox AI] Monthly Condition Report — {$device->name} ({$from->format('F Y')})",
                htmlBody: $html,
            );
        } catch (Throwable $e) {
            if (str_contains($e->getMessage(), 'invalid_grant')) {
                $owner->update(['google_refresh_token' => null]);
                Log::warning("Gmail token revoked for user {$owner->id}. User must reconnect Google.");

                return;
            }
            throw $e;
        }
    }

    /**
     * Count closed→open door transitions (not raw "open" reading count, so
     * one long open period only counts once).
     */
    private function countDoorEvents(Collection $readings): int
    {
        $events = 0;
        $prev = null;

        foreach ($readings->sortBy('recorded_at') as $r) {
            $current = strtolower(trim($r->door_state ?? ''));
            if ($current === 'open' && $prev !== 'open') {
                $events++;
            }
            $prev = $current;
        }

        return $events;
    }
}
