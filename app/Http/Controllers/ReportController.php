<?php

namespace App\Http\Controllers;

use App\Models\Device;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function generate(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'device_id' => ['required', 'integer', 'exists:devices,id'],
            'from'      => ['required', 'date'],
            'to'        => ['required', 'date', 'after_or_equal:from'],
        ]);

        $device = Device::where('id', $validated['device_id'])
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $from = Carbon::parse($validated['from'])->startOfDay();
        $to   = Carbon::parse($validated['to'])->endOfDay();

        // Pre-compute aggregate stats via DB (avoids loading all rows into memory twice)
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

        // Cursor for readings — avoids loading everything into one Collection
        $readingCursor = $device->readings()
            ->whereBetween('recorded_at', [$from, $to])
            ->orderBy('recorded_at')
            ->cursor();

        $filename = sprintf(
            'drybox-report-%s-%s-to-%s.csv',
            str()->slug($device->name),
            $from->format('Y-m-d'),
            $to->format('Y-m-d')
        );

        return response()->streamDownload(function () use (
            $device, $from, $to, $humStats, $tempStats, $alertCount, $alerts, $readingCursor
        ) {
            $out = fopen('php://output', 'w');

            // ── Report header ──
            fputcsv($out, ['DryBox AI — Condition Report']);
            fputcsv($out, ['Device',          $device->name . ' (' . $device->firebase_path . '/)']);
            fputcsv($out, ['Location',         $device->location ?? 'N/A']);
            fputcsv($out, ['Period',           $from->format('Y-m-d') . ' to ' . $to->format('Y-m-d')]);
            fputcsv($out, ['Generated',        now()->format('Y-m-d H:i:s')]);
            fputcsv($out, ['Total Readings',   (int) ($humStats->cnt ?? 0)]);
            fputcsv($out, ['Total Alerts',     $alertCount]);
            fputcsv($out, []);

            // ── Sensor Readings ──
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

            // ── Alerts ──
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

            // ── Summary Statistics ──
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

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
