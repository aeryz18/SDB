<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\DeviceSetting;
use App\Models\SilicaReplacement;
use App\Services\Firebase\FirebaseReader;
use App\Services\GeminiSilicaAdvisor;
use App\Services\SilicaStatus;
use Illuminate\Http\Request;
use Throwable;

class DeviceController extends Controller
{
    public function store(Request $request)
    {
        if ($request->user()->devices()->where('is_active', true)->exists()) {
            return back()->with('error', 'You already have a device connected. Remove it first to add a replacement.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:100'],
            'firebase_path' => ['required', 'string', 'max:100', 'unique:devices,firebase_path'],
        ]);

        $device = $request->user()->devices()->create([
            'name' => $validated['name'],
            'location' => $validated['location'] ?? null,
            'firebase_path' => $validated['firebase_path'],
            'is_active' => true,
        ]);

        DeviceSetting::create([
            'device_id' => $device->id,
            'notify_emails' => [$request->user()->email],
        ]);

        return back()->with('success', "Device \"{$device->name}\" added successfully.");
    }

    public function destroy(Device $device)
    {
        abort_unless($device->user_id === auth()->id(), 403);
        $name = $device->name;
        $device->delete();

        return back()->with('success', "Device \"{$name}\" removed.");
    }

    public function markSilicaReplaced(Request $request, Device $device)
    {
        abort_unless($device->user_id === auth()->id(), 403);

        $validated = $request->validate([
            'next_replacement_at' => ['required', 'date', 'after_or_equal:today'],
            'notify_days_before' => ['required', 'integer', 'min:1', 'max:365'],
        ]);

        $previousReplacedAt = $device->settings->silica_last_replaced_at;
        $now = now();

        $replacement = SilicaReplacement::create([
            'device_id' => $device->id,
            'replaced_at' => $now,
            'interval_days_actual' => $previousReplacedAt ? $previousReplacedAt->diffInDays($now, absolute: true) : null,
        ]);

        $device->settings->update([
            'silica_last_replaced_at' => $now,
            'silica_next_replacement_at' => $validated['next_replacement_at'],
            'silica_notify_days_before' => $validated['notify_days_before'],
        ]);

        // Resolve outstanding silica alerts
        $device->alerts()
            ->whereIn('type', ['silica_due', 'silica_drift', 'silica_upcoming'])
            ->whereNull('resolved_at')
            ->update(['resolved_at' => now()]);

        $completedIntervals = $device->silicaReplacements()->whereNotNull('interval_days_actual');
        $avgLifespanDays = (clone $completedIntervals)->count() >= 2
            ? (int) round((clone $completedIntervals)->avg('interval_days_actual'))
            : null;

        return response()->json([
            'success' => true,
            'interval_days' => $device->settings->silica_interval_days,
            'avg_lifespan_days' => $avgLifespanDays,
            'replacement_id' => $replacement->id,
        ]);
    }

    public function undoSilicaReplacement(Device $device, SilicaReplacement $replacement)
    {
        abort_unless($device->user_id === auth()->id(), 403);
        abort_unless($replacement->device_id === $device->id, 403);

        $latest = $device->silicaReplacements()->orderByDesc('replaced_at')->first();
        abort_unless($latest && $latest->id === $replacement->id, 409);

        $previous = $device->silicaReplacements()
            ->where('id', '!=', $replacement->id)
            ->orderByDesc('replaced_at')
            ->first();

        $replacement->delete();
        $device->settings->update([
            'silica_last_replaced_at' => $previous?->replaced_at,
            // The forward-looking plan was set alongside this replacement —
            // undo it too, back to the automatic interval-based estimate.
            'silica_next_replacement_at' => null,
        ]);

        return response()->json(['success' => true]);
    }

    public function updateSilicaNextReplacementAt(Request $request, Device $device)
    {
        abort_unless($device->user_id === auth()->id(), 403);

        $validated = $request->validate([
            'next_replacement_at' => ['nullable', 'date'],
            'notify_days_before' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $update = ['silica_next_replacement_at' => $validated['next_replacement_at'] ?? null];
        if (! empty($validated['notify_days_before'])) {
            $update['silica_notify_days_before'] = $validated['notify_days_before'];
        }

        $device->settings->update($update);

        // The due date just moved (or reverted to automatic) — resolve any
        // outstanding silica_due/silica_upcoming alerts so PollDeviceData's
        // fire-once-per-cycle guard re-arms against the new due date instead
        // of staying silent because an alert from the old cycle is still open.
        $device->alerts()
            ->whereIn('type', ['silica_due', 'silica_upcoming'])
            ->whereNull('resolved_at')
            ->update(['resolved_at' => now()]);

        return response()->json(['success' => true] + app(SilicaStatus::class)->evaluate($device->settings->refresh()));
    }

    public function getSilicaAiSuggestion(Device $device, GeminiSilicaAdvisor $advisor)
    {
        abort_unless($device->user_id === auth()->id(), 403);

        try {
            return response()->json(['success' => true] + $advisor->suggest($device));
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'AI suggestion is temporarily unavailable. Please try again in a moment.',
            ], 502);
        }
    }

    public function toggleProtection(Device $device, FirebaseReader $firebase)
    {
        abort_unless($device->user_id === auth()->id(), 403);

        $newState = ! $device->settings->protection_mode;
        $device->settings->update(['protection_mode' => $newState]);

        // Push to Firebase so the ESP32 picks it up within 30 seconds
        try {
            $firebase->set($device->firebase_path.'/protection_mode', $newState);
        } catch (Throwable) {
            // Firebase write failure is non-fatal — MySQL is the source of truth
        }

        return response()->json([
            'success' => true,
            'protection_mode' => $newState,
        ]);
    }
}
