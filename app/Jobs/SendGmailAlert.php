<?php

namespace App\Jobs;

use App\Models\Alert;
use App\Services\Mail\GmailApiSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendGmailAlert implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 60;

    public function __construct(
        private readonly int    $alertId,
        private readonly string $recipientEmail,
    ) {}

    public function handle(GmailApiSender $sender): void
    {
        $alert = Alert::with('device.user')->find($this->alertId);

        if (! $alert) {
            return;
        }

        $owner = $alert->device->user;

        if (! $owner->google_refresh_token) {
            Log::warning("No Gmail refresh token for user {$owner->id} — alert email skipped.");
            return;
        }

        $html = view('emails.alert', ['alert' => $alert])->render();

        try {
            $sender->send(
                fromEmail:    $owner->email,
                refreshToken: $owner->google_refresh_token,
                to:           $this->recipientEmail,
                subject:      '[DryBox AI] ' . $alert->message,
                htmlBody:     $html,
            );

            $alert->update(['emailed_at' => now()]);
        } catch (Throwable $e) {
            if (str_contains($e->getMessage(), 'invalid_grant')) {
                // Token was revoked — clear it so the UI can prompt reconnection
                $owner->update(['google_refresh_token' => null]);
                Log::warning("Gmail token revoked for user {$owner->id}. User must reconnect Google.");
                return;
            }
            throw $e;
        }
    }
}
