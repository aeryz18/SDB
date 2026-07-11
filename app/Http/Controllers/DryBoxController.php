<?php

namespace App\Http\Controllers;

use App\Services\SilicaStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DryBoxController extends Controller
{
    public function dashboard()
    {
        $device = auth()->user()->devices()
            ->with('settings')
            ->where('is_active', true)
            ->orderBy('created_at')
            ->first();

        $settings = $device?->settings;
        $silica = app(SilicaStatus::class)->evaluate($settings);

        return view('dashboard', compact('device', 'settings', 'silica'));
    }

    public function device()
    {
        $device = auth()->user()->devices()
            ->with('settings')
            ->where('is_active', true)
            ->orderBy('created_at')
            ->first();

        $settings = $device?->settings;
        $silica = app(SilicaStatus::class)->evaluate($settings);
        $silicaAvgLifespan = null;

        if ($device) {
            $completedIntervals = $device->silicaReplacements()->whereNotNull('interval_days_actual');
            if ((clone $completedIntervals)->count() >= 2) {
                $silicaAvgLifespan = (int) round((clone $completedIntervals)->avg('interval_days_actual'));
            }
        }

        return view('device', compact('device', 'settings', 'silica', 'silicaAvgLifespan'));
    }

    public function silicaLog()
    {
        $device = auth()->user()->devices()
            ->with('settings')
            ->where('is_active', true)
            ->orderBy('created_at')
            ->first();

        $silica = app(SilicaStatus::class)->evaluate($device?->settings);
        $silicaAvgLifespan = null;
        $silicaHistory = collect();

        if ($device) {
            $silicaHistory = $device->silicaReplacements()->orderByDesc('replaced_at')->paginate(15);

            $completedIntervals = $device->silicaReplacements()->whereNotNull('interval_days_actual');
            if ((clone $completedIntervals)->count() >= 2) {
                $silicaAvgLifespan = (int) round((clone $completedIntervals)->avg('interval_days_actual'));
            }
        }

        return view('silica-log', compact('device', 'silica', 'silicaAvgLifespan', 'silicaHistory'));
    }

    public function analytics(Request $request)
    {
        $primary = auth()->user()->devices()
            ->with('settings')
            ->where('is_active', true)
            ->orderBy('created_at')
            ->first();

        $settings = $primary?->settings;

        $from = $request->filled('from')
            ? Carbon::parse($request->from)->startOfDay()
            : now()->subDays(6)->startOfDay();

        $to = $request->filled('to')
            ? Carbon::parse($request->to)->endOfDay()
            : now()->endOfDay();

        $dbStats = [];
        $historyData = collect();
        $rangeStats = [];

        if ($primary) {
            $dbStats = [
                'total_readings' => $primary->readings()->count(),
                'total_alerts' => $primary->alerts()->count(),
                'earliest' => $primary->readings()->min('recorded_at'),
                'latest' => $primary->readings()->max('recorded_at'),
            ];

            $historyData = $primary->readings()
                ->selectRaw("DATE_FORMAT(recorded_at, '%Y-%m-%d %H:00:00') as hour,
                              ROUND(AVG(humidity), 1)    as avg_humidity,
                              ROUND(AVG(temperature), 1) as avg_temperature,
                              COUNT(*) as reading_count")
                ->whereBetween('recorded_at', [$from, $to])
                ->groupByRaw("DATE_FORMAT(recorded_at, '%Y-%m-%d %H:00:00')")
                ->orderBy('hour')
                ->get();

            if ($historyData->isNotEmpty()) {
                $rangeStats = [
                    'avg_humidity' => round($historyData->avg('avg_humidity'), 1),
                    'max_humidity' => $primary->readings()->whereBetween('recorded_at', [$from, $to])->max('humidity'),
                    'avg_temp' => round($historyData->avg('avg_temperature'), 1),
                    'count' => $historyData->sum('reading_count'),
                ];
            }
        }

        return view('analytics', compact('primary', 'settings', 'dbStats', 'historyData', 'rangeStats', 'from', 'to'));
    }

    public function saveSettings(Request $request)
    {
        $device = auth()->user()->devices()
            ->with('settings')
            ->where('is_active', true)
            ->orderBy('created_at')
            ->first();

        if (! $device || ! $device->settings) {
            return back()->with('error', 'No active device found. Add a device first.');
        }

        $validated = $request->validate([
            'warn_humidity' => ['required', 'integer', 'min:10', 'max:60'],
            'crit_humidity' => ['required', 'integer', 'min:20', 'max:80'],
            'silica_interval_days' => ['required', 'integer', 'min:1', 'max:365'],
            'notify_emails' => ['nullable', 'string', 'max:500'],
        ]);

        $emails = collect(explode(',', $validated['notify_emails'] ?? ''))
            ->map(fn ($e) => trim($e))
            ->filter(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))
            ->values()
            ->all();

        if (empty($emails)) {
            $emails = [$device->user->email ?? auth()->user()->email];
        }

        $device->settings->update([
            'warn_humidity' => $validated['warn_humidity'],
            'crit_humidity' => $validated['crit_humidity'],
            'silica_interval_days' => $validated['silica_interval_days'],
            'notify_emails' => $emails,
        ]);

        return back()->with('success', 'Settings saved.')
            ->with('saved_crit', $validated['crit_humidity'])
            ->with('saved_warn', $validated['warn_humidity']);
    }
}
