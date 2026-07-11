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

    public function sendWithAttachment(
        string $fromEmail,
        string $refreshToken,
        string $to,
        string $subject,
        string $htmlBody,
        string $attachmentFilename,
        string $attachmentContent,
        string $attachmentMimeType = 'text/csv',
    ): void {
        $accessToken = $this->getAccessToken($refreshToken);

        Http::withToken($accessToken)
            ->post('https://gmail.googleapis.com/gmail/v1/users/me/messages/send', [
                'raw' => $this->encodeMessageWithAttachment(
                    $fromEmail,
                    $to,
                    $subject,
                    $htmlBody,
                    $attachmentFilename,
                    $attachmentContent,
                    $attachmentMimeType,
                ),
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

    private function encodeMessageWithAttachment(
        string $from,
        string $to,
        string $subject,
        string $html,
        string $attachmentFilename,
        string $attachmentContent,
        string $attachmentMimeType,
    ): string {
        $boundary = 'drybox_' . bin2hex(random_bytes(16));

        $mime = implode("\r\n", [
            "From: DryBox AI <{$from}>",
            "To: {$to}",
            "Subject: {$subject}",
            "MIME-Version: 1.0",
            "Content-Type: multipart/mixed; boundary=\"{$boundary}\"",
            "",
            "--{$boundary}",
            "Content-Type: text/html; charset=UTF-8",
            "",
            $html,
            "",
            "--{$boundary}",
            "Content-Type: {$attachmentMimeType}; name=\"{$attachmentFilename}\"",
            "Content-Disposition: attachment; filename=\"{$attachmentFilename}\"",
            "Content-Transfer-Encoding: base64",
            "",
            chunk_split(base64_encode($attachmentContent)),
            "--{$boundary}--",
        ]);

        return rtrim(strtr(base64_encode($mime), '+/', '-_'), '=');
    }
}
