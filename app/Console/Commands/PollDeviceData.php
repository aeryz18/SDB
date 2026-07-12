<?php

namespace App\Console\Commands;

use App\Jobs\SendGmailAlert;
use App\Models\Alert;
use App\Models\Device;
use App\Models\DeviceSetting;
use App\Models\Reading;
use App\Services\AlertEvaluator;
use App\Services\Firebase\FirebaseReader;
use App\Services\SilicaStatus;
use Illuminate\Console\Command;
use Throwable;

class PollDeviceData extends Command
{
    protected $signature = 'drybox:poll';

    protected $description = 'Poll all active devices from Firebase, store readings, and evaluate alerts';

    // Silica gel humidity-drift signal — compares closed-door humidity shortly
    // after replacement against the recent closed-door average, independent of
    // the fixed silica_interval_days countdown.
    private const SILICA_DRIFT_RH_THRESHOLD = 10.0;

    private const SILICA_BASELINE_WINDOW_DAYS = 3;

    private const SILICA_RECENT_WINDOW_HOURS = 24;

    private const SILICA_MIN_SAMPLES = 10;

    public function handle(FirebaseReader $firebase, AlertEvaluator $evaluator, SilicaStatus $silicaStatus): int
    {
        $devices = Device::where('is_active', true)->with('settings', 'alerts')->get();

        if ($devices->isEmpty()) {
            $this->info('No active devices found.');

            return self::SUCCESS;
        }

        foreach ($devices as $device) {
            try {
                $data = $firebase->read($device->firebase_path);

                if (empty($data)) {
                    $this->warn("No data at path: {$device->firebase_path}");

                    continue;
                }

                $doorField = $device->settings?->door_field ?? 'door';

                // Store reading
                $reading = Reading::create([
                    'device_id' => $device->id,
                    'temperature' => isset($data['temperature']) ? (float) $data['temperature'] : null,
                    'humidity' => isset($data['humidity']) ? (float) $data['humidity'] : null,
                    'status' => $data['status'] ?? null,
                    'door_state' => $data[$doorField] ?? null,
                    'recorded_at' => now(),
                ]);

                $this->info(sprintf(
                    'Polled [%s] temp=%.1f°C  hum=%.1f%%  status=%s',
                    $device->name,
                    $data['temperature'] ?? 0,
                    $data['humidity'] ?? 0,
                    $data['status'] ?? 'n/a',
                ));

                // Evaluate threshold + tamper alerts
                $triggered = $evaluator->evaluate($device, $reading);

                // Persist alerts and dispatch email jobs
                $recipients = $device->settings?->notify_emails ?? [$device->user->email ?? null];

                foreach ($triggered as $payload) {
                    $alert = Alert::create([
                        'device_id' => $device->id,
                        'type' => $payload['type'],
                        'message' => $payload['message'],
                        'value' => $payload['value'],
                    ]);

                    $this->warn("  Alert: [{$payload['type']}] {$payload['message']}");

                    foreach (array_filter($recipients) as $email) {
                        SendGmailAlert::dispatch($alert->id, $email);
                    }
                }

                // Silica gel daily checks. A device qualifies once it has either
                // an actual replacement logged OR a manual next-replacement-date
                // override set (a device that's only ever had its due date set
                // manually, never "replaced", still needs to be evaluated).
                $settings = $device->settings;
                if ($settings && ($settings->silica_last_replaced_at || $settings->silica_next_replacement_at)) {
                    $silica = $silicaStatus->evaluate($settings);

                    // Push the day count so the ESP32's OLED can show a "Replace in: N Days" countdown
                    try {
                        $firebase->set($device->firebase_path.'/silica_days_left', $silica['days_left']);
                    } catch (Throwable) {
                        // Firebase write failure is non-fatal — MySQL/SilicaStatus remains the source of truth
                    }

                    if ($silica['due']) {
                        $this->fireSilicaAlert($device, 'silica_due', sprintf(
                            'Silica gel overdue for %s by %d day(s). Replace immediately.',
                            $device->name,
                            abs($silica['days_left']),
                        ), $recipients);
                    } elseif ($silica['warning']) {
                        $this->fireSilicaAlert($device, 'silica_upcoming', sprintf(
                            'Silica gel for %s will need replacement in %d day(s).',
                            $device->name,
                            $silica['days_left'],
                        ), $recipients);
                    }

                    // Silica gel humidity-drift check — fires even if the fixed
                    // interval hasn't elapsed yet, when the gel is clearly saturating faster.
                    // Unaffected by the once-per-cycle change above (still daily-guarded).
                    if ($settings->silica_last_replaced_at) {
                        $this->checkSilicaDrift($device, $settings, $recipients);
                    }
                }
            } catch (Throwable $e) {
                $this->error("Failed [{$device->name}]: {$e->getMessage()}");
                report($e);
            }
        }

        return self::SUCCESS;
    }

    /**
     * Create a silica alert and dispatch emails. Fires once per replacement
     * cycle (guarded on an unresolved alert of this type already existing),
     * not once per day — markSilicaReplaced() and updateSilicaNextReplacementAt()
     * both resolve outstanding silica_due/silica_upcoming alerts, which re-arms
     * this guard for the next cycle.
     */
    private function fireSilicaAlert(Device $device, string $type, string $message, array $recipients): void
    {
        $alreadyOpen = $device->alerts()
            ->where('type', $type)
            ->whereNull('resolved_at')
            ->exists();

        if ($alreadyOpen) {
            return;
        }

        $alert = Alert::create([
            'device_id' => $device->id,
            'type' => $type,
            'message' => $message,
            'value' => null,
        ]);

        $this->warn("  Alert: [{$type}] {$alert->message}");

        foreach (array_filter($recipients) as $email) {
            SendGmailAlert::dispatch($alert->id, $email);
        }
    }

    /**
     * Compare closed-door humidity shortly after the last replacement (baseline)
     * against the recent closed-door average. A material rise means the gel is
     * saturating faster than the fixed interval assumes.
     */
    private function checkSilicaDrift(Device $device, DeviceSetting $settings, array $recipients): void
    {
        $replacedAt = $settings->silica_last_replaced_at;

        $baseline = $device->readings()
            ->whereRaw('LOWER(door_state) = ?', ['closed'])
            ->whereNotNull('humidity')
            ->whereBetween('recorded_at', [$replacedAt, $replacedAt->copy()->addDays(self::SILICA_BASELINE_WINDOW_DAYS)])
            ->selectRaw('AVG(humidity) as avg_h, COUNT(*) as cnt')
            ->first();

        $recent = $device->readings()
            ->whereRaw('LOWER(door_state) = ?', ['closed'])
            ->whereNotNull('humidity')
            ->where('recorded_at', '>=', now()->subHours(self::SILICA_RECENT_WINDOW_HOURS))
            ->selectRaw('AVG(humidity) as avg_h, COUNT(*) as cnt')
            ->first();

        if (($baseline->cnt ?? 0) < self::SILICA_MIN_SAMPLES || ($recent->cnt ?? 0) < self::SILICA_MIN_SAMPLES) {
            return;
        }

        $baselineAvg = (float) $baseline->avg_h;
        $recentAvg = (float) $recent->avg_h;
        $drift = $recentAvg - $baselineAvg;

        if ($drift < self::SILICA_DRIFT_RH_THRESHOLD) {
            return;
        }

        $alreadyToday = $device->alerts()
            ->where('type', 'silica_drift')
            ->whereDate('created_at', today())
            ->exists();

        if ($alreadyToday) {
            return;
        }

        $alert = Alert::create([
            'device_id' => $device->id,
            'type' => 'silica_drift',
            'message' => sprintf(
                'Silica gel losing effectiveness at %s: closed-door humidity baseline rose from %.1f%% to %.1f%% since last replacement (+%.1f pts). Consider replacing early.',
                $device->name,
                $baselineAvg,
                $recentAvg,
                $drift,
            ),
            'value' => $recentAvg,
        ]);

        $this->warn("  Alert: [silica_drift] {$alert->message}");

        foreach (array_filter($recipients) as $email) {
            SendGmailAlert::dispatch($alert->id, $email);
        }
    }
}
