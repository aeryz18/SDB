<?php

namespace App\Console\Commands;

use App\Jobs\SendGmailAlert;
use App\Models\Alert;
use App\Models\Device;
use App\Models\Reading;
use App\Services\AlertEvaluator;
use App\Services\Firebase\FirebaseReader;
use App\Services\FungusRisk;
use Illuminate\Console\Command;
use Throwable;

class PollDeviceData extends Command
{
    protected $signature = 'drybox:poll';
    protected $description = 'Poll all active devices from Firebase, store readings, and evaluate alerts';

    public function handle(FirebaseReader $firebase, AlertEvaluator $evaluator, FungusRisk $fungus): int
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
                    'device_id'   => $device->id,
                    'temperature' => isset($data['temperature']) ? (float) $data['temperature'] : null,
                    'humidity'    => isset($data['humidity'])    ? (float) $data['humidity']    : null,
                    'status'      => $data['status']             ?? null,
                    'door_state'  => $data[$doorField]           ?? null,
                    'recorded_at' => now(),
                ]);

                $this->info(sprintf(
                    'Polled [%s] temp=%.1f°C  hum=%.1f%%  status=%s',
                    $device->name,
                    $data['temperature'] ?? 0,
                    $data['humidity']    ?? 0,
                    $data['status']      ?? 'n/a',
                ));

                // Evaluate threshold + tamper alerts
                $triggered = $evaluator->evaluate($device, $reading);

                // Evaluate fungus risk from last 60 readings (~1 hour at 1/min)
                $recentReadings = $device->readings()
                    ->orderByDesc('recorded_at')
                    ->limit(60)
                    ->get();

                $riskResult = $fungus->evaluate($recentReadings, $device->settings);
                $triggered  = array_merge($triggered, $evaluator->evaluateFungus($device, $riskResult['level']));

                // Persist alerts and dispatch email jobs
                $recipients = $device->settings?->notify_emails ?? [$device->user->email ?? null];

                foreach ($triggered as $payload) {
                    $alert = Alert::create([
                        'device_id' => $device->id,
                        'type'      => $payload['type'],
                        'message'   => $payload['message'],
                        'value'     => $payload['value'],
                    ]);

                    $this->warn("  Alert: [{$payload['type']}] {$payload['message']}");

                    foreach (array_filter($recipients) as $email) {
                        SendGmailAlert::dispatch($alert->id, $email);
                    }
                }

                // Silica gel daily check (once per day per device)
                $settings = $device->settings;
                if ($settings && $settings->silica_last_replaced_at) {
                    $dueDate = $settings->silica_last_replaced_at->copy()->addDays($settings->silica_interval_days);
                    if ($dueDate->isPast()) {
                        $alreadyToday = $device->alerts()
                            ->where('type', 'silica_due')
                            ->whereDate('created_at', today())
                            ->exists();

                        if (! $alreadyToday) {
                            $daysPast    = (int) $dueDate->diffInDays(now());
                            $silicaAlert = Alert::create([
                                'device_id' => $device->id,
                                'type'      => 'silica_due',
                                'message'   => "Silica gel overdue for {$device->name} by {$daysPast} day(s). Replace immediately.",
                                'value'     => null,
                            ]);

                            $this->warn("  Alert: [silica_due] {$silicaAlert->message}");

                            foreach (array_filter($recipients) as $email) {
                                SendGmailAlert::dispatch($silicaAlert->id, $email);
                            }
                        }
                    }
                }
            } catch (Throwable $e) {
                $this->error("Failed [{$device->name}]: {$e->getMessage()}");
                report($e);
            }
        }

        return self::SUCCESS;
    }
}
