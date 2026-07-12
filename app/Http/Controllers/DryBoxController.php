<?php

namespace App\Http\Controllers;

use App\Services\Firebase\FirebaseReader;
use App\Services\SilicaStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Throwable;

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

        // Seeds the live humidity chart with recent history so it doesn't
        // start blank on every page load — the live Firebase listener then
        // appends fresh points on top of this in the browser.
        $recentReadings = $device
            ? $device->readings()->whereNotNull('humidity')->orderByDesc('recorded_at')->limit(30)->get()->reverse()->values()
            : collect();

        return view('dashboard', compact('device', 'settings', 'silica', 'recentReadings'));
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

        // "Today" and any user-picked date must be interpreted in the
        // display timezone (not UTC) so the date range matches the user's
        // actual calendar day — recorded_at is stored in UTC, so DB queries
        // convert these back to UTC at query time (see $fromUtc/$toUtc).
        $displayTz = config('app.display_timezone');

        $from = $request->filled('from')
            ? Carbon::parse($request->from, $displayTz)->startOfDay()
            : now($displayTz)->subDays(6)->startOfDay();

        $to = $request->filled('to')
            ? Carbon::parse($request->to, $displayTz)->endOfDay()
            : now($displayTz)->endOfDay();

        $fromUtc = $from->copy()->setTimezone('UTC');
        $toUtc = $to->copy()->setTimezone('UTC');

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
                ->selectRaw("DATE_FORMAT(CONVERT_TZ(recorded_at, '+00:00', '+08:00'), '%Y-%m-%d %H:00:00') as hour,
                              ROUND(AVG(humidity), 1)    as avg_humidity,
                              ROUND(AVG(temperature), 1) as avg_temperature,
                              COUNT(*) as reading_count")
                ->whereBetween('recorded_at', [$fromUtc, $toUtc])
                ->groupByRaw("DATE_FORMAT(CONVERT_TZ(recorded_at, '+00:00', '+08:00'), '%Y-%m-%d %H:00:00')")
                ->orderBy('hour')
                ->get();

            if ($historyData->isNotEmpty()) {
                $rangeStats = [
                    'avg_humidity' => round($historyData->avg('avg_humidity'), 1),
                    'max_humidity' => $primary->readings()->whereBetween('recorded_at', [$fromUtc, $toUtc])->max('humidity'),
                    'avg_temp' => round($historyData->avg('avg_temperature'), 1),
                    'count' => $historyData->sum('reading_count'),
                ];
            }
        }

        return view('analytics', compact('primary', 'settings', 'dbStats', 'historyData', 'rangeStats', 'from', 'to'));
    }

    public function saveSettings(Request $request, FirebaseReader $firebase)
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
            'notify_emails' => ['nullable', 'string', 'max:500'],
            'temp_min' => ['nullable', 'numeric', 'min:-10', 'max:60'],
            'temp_max' => ['nullable', 'numeric', 'min:-10', 'max:60'],
            'alert_cooldown_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
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
            'notify_emails' => $emails,
            'temp_min' => filled($validated['temp_min'] ?? null) ? $validated['temp_min'] : null,
            'temp_max' => filled($validated['temp_max'] ?? null) ? $validated['temp_max'] : null,
            'alert_cooldown_minutes' => $validated['alert_cooldown_minutes'],
        ]);

        // Push to Firebase so the ESP32 picks up the new thresholds within one poll cycle.
        // Cast to int explicitly — $validated values are raw HTML form strings (the
        // 'integer' validation rule only checks the format, it doesn't cast the type),
        // and pushing a JSON string here makes the ESP32's getFloat() silently read 0.0.
        try {
            $firebase->set($device->firebase_path.'/warn_humidity', (int) $validated['warn_humidity']);
            $firebase->set($device->firebase_path.'/crit_humidity', (int) $validated['crit_humidity']);
        } catch (Throwable) {
            // Firebase write failure is non-fatal — MySQL is the source of truth
        }

        return back()->with('success', 'Settings saved.')
            ->with('saved_crit', $validated['crit_humidity'])
            ->with('saved_warn', $validated['warn_humidity']);
    }
}
