<?php

namespace App\Console\Commands;

use App\Jobs\SendMonthlyReport;
use App\Models\Device;
use Illuminate\Console\Command;

class SendMonthlyReports extends Command
{
    protected $signature = 'drybox:monthly-report';
    protected $description = "Email each active device's condition report for the previous calendar month";

    public function handle(): int
    {
        $devices = Device::where('is_active', true)->with('settings', 'user')->get();

        if ($devices->isEmpty()) {
            $this->info('No active devices found.');
            return self::SUCCESS;
        }

        $from = now()->subMonthNoOverflow()->startOfMonth();
        $to   = now()->subMonthNoOverflow()->endOfMonth();

        foreach ($devices as $device) {
            $recipients = $device->settings?->notify_emails ?? [$device->user->email ?? null];

            foreach (array_filter($recipients) as $email) {
                SendMonthlyReport::dispatch($device->id, $email, $from->toDateString(), $to->toDateString());
            }

            $this->info("Queued monthly report for [{$device->name}] ({$from->format('F Y')})");
        }

        return self::SUCCESS;
    }
}
