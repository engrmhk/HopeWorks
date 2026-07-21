<?php

namespace Tests\Feature;

use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use App\Models\Church;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Services\Communications\Providers\MailMessageProvider;
use App\Services\SupportTicketNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SupportTicketApiTest extends TestCase
{
    use RefreshDatabase;

    protected string $apiKey = 'hw_support_test_key';

    protected Church $church;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'hopeworks.messaging.driver' => 'mail',
            'hopeworks.messaging.staff_notify_to' => 'ops@hopeworks.test',
        ]);

        $this->church = Church::create([
            'name' => 'Support Church',
            'subdomain' => 'support-church',
            'contact_email' => 'pastor@church.test',
        ]);
        $this->church->setInstanceApiKey($this->apiKey);
    }

    public function test_church_can_create_and_fetch_ticket_thread(): void
    {
        $spy = Mockery::spy(MailMessageProvider::class);
        $this->app->instance(MailMessageProvider::class, $spy);

        $create = $this->postJson('/api/v1/support-tickets', [
            'subject' => 'Need help',
            'body' => 'First message from parish',
            'priority' => 'high',
        ], [
            'Authorization' => 'Bearer '.$this->apiKey,
        ]);

        $create->assertCreated()
            ->assertJsonPath('subject', 'Need help')
            ->assertJsonPath('priority', 'high')
            ->assertJsonPath('messages.0.is_staff', false);

        $ticketId = $create->json('id');

        $this->getJson('/api/v1/support-tickets/'.$ticketId, [
            'Authorization' => 'Bearer '.$this->apiKey,
        ])->assertOk()
            ->assertJsonPath('messages.0.body', 'First message from parish');

        $this->getJson('/api/v1/support-tickets', [
            'Authorization' => 'Bearer '.$this->apiKey,
        ])->assertOk()
            ->assertJsonCount(1, 'data');

        $spy->shouldHaveReceived('send')
            ->withArgs(fn (string $to) => $to === 'ops@hopeworks.test');
    }

    public function test_church_cannot_read_another_churchs_ticket(): void
    {
        $other = Church::create([
            'name' => 'Other',
            'subdomain' => 'other-church',
        ]);

        $ticket = SupportTicket::create([
            'church_id' => $other->id,
            'subject' => 'Secret',
            'status' => SupportTicketStatus::Open,
            'priority' => SupportTicketPriority::Normal,
        ]);

        $this->getJson('/api/v1/support-tickets/'.$ticket->id, [
            'Authorization' => 'Bearer '.$this->apiKey,
        ])->assertNotFound();
    }

    public function test_staff_reply_notifies_church_contact(): void
    {
        $ticket = SupportTicket::create([
            'church_id' => $this->church->id,
            'subject' => 'Billing question',
            'status' => SupportTicketStatus::Open,
            'priority' => SupportTicketPriority::Normal,
        ]);

        $staff = User::factory()->create();

        $spy = Mockery::spy(MailMessageProvider::class);
        $this->app->instance(MailMessageProvider::class, $spy);

        $message = SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'author_id' => $staff->id,
            'is_staff' => true,
            'body' => 'We looked into this — here is the fix.',
        ]);

        app(SupportTicketNotificationService::class)->notifyChurchOfStaffReply($ticket, $message);

        $spy->shouldHaveReceived('send')
            ->once()
            ->withArgs(fn (string $to, string $subject, string $body) => $to === 'pastor@church.test'
                && str_contains($subject, (string) $ticket->id)
                && str_contains($body, 'here is the fix'));
    }

    public function test_invalid_api_key_rejected(): void
    {
        $this->postJson('/api/v1/support-tickets', [
            'subject' => 'x',
            'body' => 'y',
        ], [
            'Authorization' => 'Bearer wrong',
        ])->assertUnauthorized();
    }
}
