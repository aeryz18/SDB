<?php

namespace App\Services;

use App\Models\Device;
use App\Models\Reading;

class AlertEvaluator
{
    /**
     * Evaluate sensor thresholds and tamper state for the latest reading.
     * Returns an array of alert payloads ready to be persisted.
     */
    public function evaluate(Device $device, Reading $reading): array
    {
        $settings = $device->settings;
        $triggered = [];

        // Humidity
        if ($reading->humidity !== null) {
            if ((float) $reading->humidity > (float) $settings->crit_humidity) {
                if ($this->shouldAlert($device, 'humidity_crit')) {
                    $triggered[] = [
                        'type' => 'humidity_crit',
                        'message' => "Humidity critical at {$device->name}: {$reading->humidity}% (critical: {$settings->crit_humidity}%)",
                        'value' => $reading->humidity,
                    ];
                }
            } elseif ((float) $reading->humidity > (float) $settings->warn_humidity) {
                if ($this->shouldAlert($device, 'humidity_warn')) {
                    $triggered[] = [
                        'type' => 'humidity_warn',
                        'message' => "Humidity warning at {$device->name}: {$reading->humidity}% (warning: {$settings->warn_humidity}%)",
                        'value' => $reading->humidity,
                    ];
                }
            }
        }

        // Temperature
        if ($reading->temperature !== null) {
            if ($settings->temp_max !== null && (float) $reading->temperature > (float) $settings->temp_max) {
                if ($this->shouldAlert($device, 'temp')) {
                    $triggered[] = [
                        'type' => 'temp',
                        'message' => "Temperature too high at {$device->name}: {$reading->temperature}°C (max: {$settings->temp_max}°C)",
                        'value' => $reading->temperature,
                    ];
                }
            } elseif ($settings->temp_min !== null && (float) $reading->temperature < (float) $settings->temp_min) {
                if ($this->shouldAlert($device, 'temp')) {
                    $triggered[] = [
                        'type' => 'temp',
                        'message' => "Temperature too low at {$device->name}: {$reading->temperature}°C (min: {$settings->temp_min}°C)",
                        'value' => $reading->temperature,
                    ];
                }
            }
        }

        // Tamper / protection mode
        if ($settings->protection_mode && strtolower($reading->door_state ?? '') === 'open') {
            if ($this->shouldAlert($device, 'tamper')) {
                $triggered[] = [
                    'type' => 'tamper',
                    'message' => "Security alert: {$device->name} was opened without authorization!",
                    'value' => null,
                ];
            }
        }

        return $triggered;
    }

    private function shouldAlert(Device $device, string $type): bool
    {
        $cooldown = $device->settings?->alert_cooldown_minutes ?? 30;

        return ! $device->alerts()
            ->where('type', $type)
            ->where('created_at', '>=', now()->subMinutes($cooldown))
            ->exists();
    }
}
