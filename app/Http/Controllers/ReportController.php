<?php

namespace App\Http\Controllers;

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

        $from = Carbon::parse($validated['from'])->startOfDay();
        $to = Carbon::parse($validated['to'])->endOfDay();

        $csv = $generator->generate($device, $from, $to);
        $filename = $generator->filename($device, $from, $to);

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
