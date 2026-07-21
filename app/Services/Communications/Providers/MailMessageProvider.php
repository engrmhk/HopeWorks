<?php

namespace App\Services\Communications\Providers;

use App\Services\Communications\Contracts\MessageProvider;
use Illuminate\Support\Facades\Mail;

/**
 * Control Plane mail sender (not the church Communications module).
 * Uses Laravel's mailer so Postmark/SES/SMTP can be swapped via MAIL_*.
 */
class MailMessageProvider implements MessageProvider
{
    public function send(string $to, string $subject, string $body, array $context = []): void
    {
        Mail::raw($body, function ($message) use ($to, $subject): void {
            $message->to($to)->subject($subject);
        });
    }
}
