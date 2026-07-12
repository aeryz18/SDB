<?php

namespace App\Http\Controllers;

use App\Jobs\SendOnDemandReport;
use App\Models\Device;
use App\Services\ReportGenerator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function generate(Request $request, ReportGenerator $generator): StreamedResponse
    {
        $validated = $request->validate([
            'device_id' => ['required', 'integer', 'exists:devices,id'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        $device = Device::where('id', $validated['device_id'])
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $displayTz = config('app.display_timezone');
        $from = Carbon::parse($validated['from'], $displayTz)->startOfDay();
        $to = Carbon::parse($validated['to'], $displayTz)->endOfDay();

        $csv = $generator->generate($device, $from, $to);
        $filename = $generator->filename($device, $from, $to);

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function emailReport(Request $request)
    {
        $validated = $request->validate([
            'device_id' => ['required', 'integer', 'exists:devices,id'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'include_csv' => ['nullable', 'boolean'],
        ]);

        $device = Device::where('id', $validated['device_id'])
            ->where('user_id', auth()->id())
            ->firstOrFail();

        if (! auth()->user()->google_refresh_token) {
            return response()->json([
                'success' => false,
                'message' => 'Connect your Google account (sign in with Google) before requesting an emailed report.',
            ], 422);
        }

        SendOnDemandReport::dispatch(
            $device->id,
            auth()->user()->email,
            $validated['from'],
            $validated['to'],
            (bool) ($validated['include_csv'] ?? false),
        );

        return response()->json(['success' => true]);
    }
}
