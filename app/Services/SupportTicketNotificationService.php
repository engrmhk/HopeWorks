<?php

namespace App\Services;

use App\Models\Church;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Services\Communications\MessageProviderManager;
use Illuminate\Foundation\Auth\User as Authenticatable;

class SupportTicketNotificationService
{
    public function __construct(
        protected MessageProviderManager $messageProviderManager,
    ) {}

    public function notifyStaffOfNewTicket(SupportTicket $ticket): void
    {
        $to = config('hopeworks.messaging.staff_notify_to');

        if (! is_string($to) || $to === '') {
            return;
        }

        $ticket->loadMissing('church');

        $this->messageProviderManager->driver()->send(
            $to,
            'New support ticket #'.$ticket->id.': '.$ticket->subject,
            "Church: ".($ticket->church?->name ?? 'n/a')."\nPriority: {$ticket->priority->value}\n\nOpen in Control Plane admin to reply.",
            ['type' => 'support_ticket_created', 'ticket_id' => $ticket->id],
        );
    }

    public function notifyChurchOfStaffReply(SupportTicket $ticket, SupportTicketMessage $message): void
    {
        $ticket->loadMissing('church.synod');
        $church = $ticket->church;

        if (! $church instanceof Church) {
            return;
        }

        $to = $church->contact_email
            ?? $church->billing_email
            ?? $church->synod?->contact_email;

        if (! is_string($to) || $to === '') {
            return;
        }

        $this->messageProviderManager->driver()->send(
            $to,
            'Reply on Hope Works ticket #'.$ticket->id.': '.$ticket->subject,
            "Hope Works staff replied:\n\n{$message->body}\n\nView the thread in your church admin support area.",
            [
                'type' => 'support_ticket_staff_reply',
                'ticket_id' => $ticket->id,
                'message_id' => $message->id,
            ],
        );
    }
}
