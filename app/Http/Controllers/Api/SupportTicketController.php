<?php

namespace App\Http\Controllers\Api;

use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Services\SupportTicketNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Support ticketing API for HopeWorks-church (SP1 / P7-3 companion).
 *
 * Auth: Authorization: Bearer {church instance API key} (middleware church.api)
 *
 * POST   /api/v1/support-tickets
 *   Body: { "subject": string, "body": string, "priority"?: "low|normal|high|urgent" }
 *   201: { id, subject, status, priority, created_at, messages: [...] }
 *
 * GET    /api/v1/support-tickets
 *   200: { data: [ { id, subject, status, priority, created_at, updated_at } ] }
 *
 * GET    /api/v1/support-tickets/{id}
 *   200: { id, subject, status, priority, messages: [ { id, body, is_staff, created_at } ] }
 *   404 if ticket is not owned by the authenticated church
 *
 * POST   /api/v1/support-tickets/{id}/messages
 *   Body: { "body": string }
 *   201: { id, body, is_staff: false, created_at }
 *
 * Notification model: Control Plane emails the church contact on staff replies.
 * Church UI may also poll GET /support-tickets/{id}; no push webhook to the instance yet.
 */
class SupportTicketController extends Controller
{
    public function __construct(
        protected SupportTicketNotificationService $notificationService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var Church $church */
        $church = $request->attributes->get('church');

        $tickets = SupportTicket::query()
            ->where('church_id', $church->id)
            ->latest()
            ->get(['id', 'subject', 'status', 'priority', 'created_at', 'updated_at']);

        return response()->json(['data' => $tickets]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var Church $church */
        $church = $request->attributes->get('church');

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'priority' => ['nullable', 'string', 'in:low,normal,high,urgent'],
        ]);

        $ticket = SupportTicket::query()->create([
            'church_id' => $church->id,
            'subject' => $data['subject'],
            'status' => SupportTicketStatus::Open,
            'priority' => SupportTicketPriority::from($data['priority'] ?? SupportTicketPriority::Normal->value),
            'created_by' => null,
        ]);

        $message = SupportTicketMessage::query()->create([
            'support_ticket_id' => $ticket->id,
            'author_id' => null,
            'body' => $data['body'],
            'is_staff' => false,
        ]);

        $this->notificationService->notifyStaffOfNewTicket($ticket);

        return response()->json([
            'id' => $ticket->id,
            'subject' => $ticket->subject,
            'status' => $ticket->status->value,
            'priority' => $ticket->priority->value,
            'created_at' => $ticket->created_at?->toIso8601String(),
            'messages' => [$this->serializeMessage($message)],
        ], 201);
    }

    public function show(Request $request, SupportTicket $supportTicket): JsonResponse
    {
        $this->assertOwned($request, $supportTicket);

        $supportTicket->load(['messages' => fn ($q) => $q->orderBy('created_at')]);

        return response()->json([
            'id' => $supportTicket->id,
            'subject' => $supportTicket->subject,
            'status' => $supportTicket->status->value,
            'priority' => $supportTicket->priority->value,
            'created_at' => $supportTicket->created_at?->toIso8601String(),
            'updated_at' => $supportTicket->updated_at?->toIso8601String(),
            'messages' => $supportTicket->messages->map(fn (SupportTicketMessage $m) => $this->serializeMessage($m))->values(),
        ]);
    }

    public function storeMessage(Request $request, SupportTicket $supportTicket): JsonResponse
    {
        $this->assertOwned($request, $supportTicket);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
        ]);

        $message = SupportTicketMessage::query()->create([
            'support_ticket_id' => $supportTicket->id,
            'author_id' => null,
            'body' => $data['body'],
            'is_staff' => false,
        ]);

        if ($supportTicket->status === SupportTicketStatus::Resolved
            || $supportTicket->status === SupportTicketStatus::Closed) {
            $supportTicket->update(['status' => SupportTicketStatus::Open]);
        }

        $this->notificationService->notifyStaffOfNewTicket($supportTicket);

        return response()->json($this->serializeMessage($message), 201);
    }

    protected function assertOwned(Request $request, SupportTicket $ticket): void
    {
        /** @var Church $church */
        $church = $request->attributes->get('church');

        abort_if((int) $ticket->church_id !== (int) $church->id, 404);
    }

    /**
     * @return array{id: int, body: string, is_staff: bool, created_at: string|null}
     */
    protected function serializeMessage(SupportTicketMessage $message): array
    {
        return [
            'id' => $message->id,
            'body' => $message->body,
            'is_staff' => (bool) $message->is_staff,
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }
}
