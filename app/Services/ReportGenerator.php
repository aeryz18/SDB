<?php

namespace App\Services;

use App\Models\Device;
use Carbon\Carbon;

class ReportGenerator
{
    /**
     * Build the CSV condition report for a device over a date range.
     */
    public function generate(Device $device, Carbon $from, Carbon $to): string
    {
        $humStats = $device->readings()
            ->whereBetween('recorded_at', [$from, $to])
            ->whereNotNull('humidity')
            ->selectRaw('AVG(humidity) as avg_h, MAX(humidity) as max_h, MIN(humidity) as min_h, COUNT(*) as cnt')
            ->first();

        $tempStats = $device->readings()
            ->whereBetween('recorded_at', [$from, $to])
            ->whereNotNull('temperature')
            ->selectRaw('AVG(temperature) as avg_t, MAX(temperature) as max_t, MIN(temperature) as min_t')
            ->first();

        $alertCount = $device->alerts()
            ->whereBetween('created_at', [$from, $to])
            ->count();

        $alerts = $device->alerts()
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at')
            ->get();

        $readingCursor = $device->readings()
            ->whereBetween('recorded_at', [$from, $to])
            ->orderBy('recorded_at')
            ->cursor();

        $out = fopen('php://memory', 'w+');

        fputcsv($out, ['DryBox AI — Condition Report']);
        fputcsv($out, ['Device',          $device->name . ' (' . $device->firebase_path . '/)']);
        fputcsv($out, ['Location',         $device->location ?? 'N/A']);
        fputcsv($out, ['Period',           $from->format('Y-m-d') . ' to ' . $to->format('Y-m-d')]);
        fputcsv($out, ['Generated',        now()->format('Y-m-d H:i:s')]);
        fputcsv($out, ['Total Readings',   (int) ($humStats->cnt ?? 0)]);
        fputcsv($out, ['Total Alerts',     $alertCount]);
        fputcsv($out, []);

        fputcsv($out, ['=== SENSOR READINGS ===']);
        fputcsv($out, ['Timestamp', 'Temperature (°C)', 'Humidity (%)', 'Status', 'Door State']);
        foreach ($readingCursor as $r) {
            fputcsv($out, [
                $r->recorded_at->format('Y-m-d H:i:s'),
                $r->temperature !== null ? number_format((float) $r->temperature, 2) : '',
                $r->humidity    !== null ? number_format((float) $r->humidity,    2) : '',
                $r->status      ?? '',
                $r->door_state  ?? '',
            ]);
        }
        fputcsv($out, []);

        fputcsv($out, ['=== ALERTS ===']);
        fputcsv($out, ['Timestamp', 'Type', 'Message', 'Value', 'Emailed At', 'Resolved At']);
        foreach ($alerts as $a) {
            fputcsv($out, [
                $a->created_at->format('Y-m-d H:i:s'),
                $a->type,
                $a->message,
                $a->value !== null ? number_format((float) $a->value, 2) : '',
                $a->emailed_at?->format('Y-m-d H:i:s')  ?? '',
                $a->resolved_at?->format('Y-m-d H:i:s') ?? '',
            ]);
        }
        fputcsv($out, []);

        if (($humStats->cnt ?? 0) > 0) {
            fputcsv($out, ['=== SUMMARY STATISTICS ===']);
            fputcsv($out, ['Metric',                    'Value']);
            fputcsv($out, ['Average Humidity (%)',      number_format((float) $humStats->avg_h, 2)]);
            fputcsv($out, ['Peak Humidity (%)',         number_format((float) $humStats->max_h, 2)]);
            fputcsv($out, ['Minimum Humidity (%)',      number_format((float) $humStats->min_h, 2)]);
            fputcsv($out, ['Average Temperature (°C)',  number_format((float) $tempStats->avg_t, 2)]);
            fputcsv($out, ['Peak Temperature (°C)',     number_format((float) $tempStats->max_t, 2)]);
            fputcsv($out, ['Minimum Temperature (°C)',  number_format((float) $tempStats->min_t, 2)]);
        }

        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    public function filename(Device $device, Carbon $from, Carbon $to): string
    {
        return sprintf(
            'drybox-report-%s-%s-to-%s.csv',
            str()->slug($device->name),
            $from->format('Y-m-d'),
            $to->format('Y-m-d')
        );
    }
}
