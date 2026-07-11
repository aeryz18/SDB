<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Device;
use App\Models\DeviceSetting;
use App\Services\Firebase\FirebaseReader;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    public function store(Request $request)
    {
        if ($request->user()->devices()->where('is_active', true)->exists()) {
            return back()->with('error', 'You already have a device connected. Remove it first to add a replacement.');
        }

        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:100'],
            'location'      => ['nullable', 'string', 'max:100'],
            'firebase_path' => ['required', 'string', 'max:100', 'unique:devices,firebase_path'],
        ]);

        $device = $request->user()->devices()->create([
            'name'          => $validated['name'],
            'location'      => $validated['location'] ?? null,
            'firebase_path' => $validated['firebase_path'],
            'is_active'     => true,
        ]);

        DeviceSetting::create([
            'device_id'     => $device->id,
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

    public function markSilicaReplaced(Device $device)
    {
        abort_unless($device->user_id === auth()->id(), 403);

        $device->settings->update(['silica_last_replaced_at' => now()]);

        // Resolve outstanding silica alerts
        $device->alerts()
            ->whereIn('type', ['silica_due', 'silica_drift'])
            ->whereNull('resolved_at')
            ->update(['resolved_at' => now()]);

        return response()->json([
            'success'      => true,
            'interval_days' => $device->settings->silica_interval_days,
        ]);
    }

    public function toggleProtection(Device $device, FirebaseReader $firebase)
    {
        abort_unless($device->user_id === auth()->id(), 403);

        $newState = ! $device->settings->protection_mode;
        $device->settings->update(['protection_mode' => $newState]);

        // Push to Firebase so the ESP32 picks it up within 30 seconds
        try {
            $firebase->set($device->firebase_path . '/protection_mode', $newState);
        } catch (\Throwable) {
            // Firebase write failure is non-fatal — MySQL is the source of truth
        }

        return response()->json([
            'success'         => true,
            'protection_mode' => $newState,
        ]);
    }
}
