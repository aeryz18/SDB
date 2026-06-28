<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\DeviceSetting;
use Illuminate\Http\Request;

class SetupController extends Controller
{
    /** Step 1 — add your device */
    public function wizard()
    {
        $device = auth()->user()->devices()->where('is_active', true)->first();

        if ($device) {
            return redirect()->route('setup.firmware', $device);
        }

        return view('setup.wizard');
    }

    /** POST step 1 — create the device, go to firmware step */
    public function storeDevice(Request $request)
    {
        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:100'],
            'location'      => ['nullable', 'string', 'max:100'],
            'firebase_path' => ['required', 'string', 'max:100', 'unique:devices,firebase_path'],
        ]);

        $device = $request->user()->devices()->create([
            'name'          => $validated['name'],
            'location'      => $validated['location'] ?? null,
            'firebase_path' => ltrim($validated['firebase_path'], '/'),
            'is_active'     => true,
        ]);

        DeviceSetting::create([
            'device_id'     => $device->id,
            'notify_emails' => [$request->user()->email],
        ]);

        return redirect()->route('setup.firmware', $device);
    }

    /** Step 2 — firmware flashing instructions */
    public function firmware(Device $device)
    {
        abort_unless($device->user_id === auth()->id(), 403);

        return view('setup.firmware', [
            'device' => $device,
            'apiKey' => config('firebase.api_key'),
            'dbUrl'  => config('firebase.database_url'),
        ]);
    }

    /** Step 3 — email alert setup */
    public function alerts()
    {
        $device = auth()->user()->devices()->where('is_active', true)->first();

        if (! $device) {
            return redirect()->route('setup');
        }

        $hasGmail = (bool) auth()->user()->google_refresh_token;

        return view('setup.alerts', compact('hasGmail', 'device'));
    }
}
