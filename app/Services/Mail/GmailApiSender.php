<?php

namespace App\Services\Mail;

use Illuminate\Support\Facades\Http;

class GmailApiSender
{
    public function send(string $fromEmail, string $refreshToken, string $to, string $subject, string $htmlBody): void
    {
        $accessToken = $this->getAccessToken($refreshToken);

        Http::withToken($accessToken)
            ->post('https://gmail.googleapis.com/gmail/v1/users/me/messages/send', [
                'raw' => $this->encodeMessage($fromEmail, $to, $subject, $htmlBody),
            ])
            ->throw();
    }

    private function getAccessToken(string $refreshToken): string
    {
        $response = Http::asForm()
            ->post('https://oauth2.googleapis.com/token', [
                'client_id'     => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'refresh_token' => $refreshToken,
                'grant_type'    => 'refresh_token',
            ])
            ->throw()
            ->json();

        return $response['access_token'];
    }

    private function encodeMessage(string $from, string $to, string $subject, string $html): string
    {
        $mime = implode("\r\n", [
            "From: DryBox AI <{$from}>",
            "To: {$to}",
            "Subject: {$subject}",
            "MIME-Version: 1.0",
            "Content-Type: text/html; charset=UTF-8",
            "",
            $html,
        ]);

        return rtrim(strtr(base64_encode($mime), '+/', '-_'), '=');
    }
}
