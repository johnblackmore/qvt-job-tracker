<?php

namespace Tests\Feature\Services;

use App\Models\EmailSent;
use App\Services\EmailWebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailWebhookServiceTest extends TestCase
{
    use RefreshDatabase;

    protected EmailWebhookService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(EmailWebhookService::class);
    }

    public function test_it_records_delivery(): void
    {
        $emailSent = EmailSent::factory()->create([
            'postmark_message_id' => 'delivery-msg-001',
            'status' => 'pending',
        ]);

        $payload = [
            'RecordType' => 'Delivery',
            'MessageID' => 'delivery-msg-001',
            'DeliveredAt' => '2026-07-16T10:00:00Z',
        ];

        $result = $this->service->handleEvent($payload);

        $this->assertEquals('delivery_recorded', $result['action']);

        $emailSent->refresh();
        $this->assertEquals('sent', $emailSent->status);
        $this->assertNotNull($emailSent->delivered_at);
    }

    public function test_it_records_hard_bounce(): void
    {
        $emailSent = EmailSent::factory()->create([
            'postmark_message_id' => 'bounce-msg-001',
            'status' => 'pending',
        ]);

        $payload = [
            'RecordType' => 'Bounce',
            'ID' => 42,
            'Type' => 'HardBounce',
            'TypeCode' => 1,
            'MessageID' => 'bounce-msg-001',
            'BouncedAt' => '2026-07-16T10:05:00Z',
            'Description' => '550 5.1.1 The email account does not exist.',
            'Details' => 'smtp;550 5.1.1',
        ];

        $result = $this->service->handleEvent($payload);

        $this->assertEquals('bounce_recorded', $result['action']);
        $this->assertEquals('HardBounce', $result['bounce_type']);

        $emailSent->refresh();
        $this->assertEquals('failed', $emailSent->status);
        $this->assertEquals('HardBounce', $emailSent->bounce_type);
        $this->assertNotNull($emailSent->bounced_at);
        $this->assertStringContainsString('550 5.1.1', $emailSent->error_message);
    }

    public function test_it_records_soft_bounce(): void
    {
        $emailSent = EmailSent::factory()->create([
            'postmark_message_id' => 'bounce-msg-002',
            'status' => 'pending',
        ]);

        $payload = [
            'RecordType' => 'Bounce',
            'Type' => 'SoftBounce',
            'MessageID' => 'bounce-msg-002',
            'BouncedAt' => '2026-07-16T10:10:00Z',
        ];

        $result = $this->service->handleEvent($payload);

        $this->assertEquals('bounce_recorded', $result['action']);
        $this->assertEquals('SoftBounce', $result['bounce_type']);

        $emailSent->refresh();
        $this->assertEquals('failed', $emailSent->status);
        $this->assertEquals('SoftBounce', $emailSent->bounce_type);
    }

    public function test_it_records_spam_complaint(): void
    {
        $emailSent = EmailSent::factory()->create([
            'postmark_message_id' => 'spam-msg-001',
            'status' => 'sent',
        ]);

        $payload = [
            'RecordType' => 'SpamComplaint',
            'MessageID' => 'spam-msg-001',
            'BouncedAt' => '2026-07-16T11:00:00Z',
        ];

        $result = $this->service->handleEvent($payload);

        $this->assertEquals('spam_complaint_recorded', $result['action']);

        $emailSent->refresh();
        $this->assertEquals('failed', $emailSent->status);
        $this->assertNotNull($emailSent->spam_complaint_at);
    }

    public function test_it_records_first_open_only(): void
    {
        $emailSent = EmailSent::factory()->create([
            'postmark_message_id' => 'open-msg-001',
            'status' => 'sent',
            'opened_at' => null,
        ]);

        $payload1 = [
            'RecordType' => 'Open',
            'MessageID' => 'open-msg-001',
            'ReceivedAt' => '2026-07-16T12:00:00Z',
        ];

        $this->service->handleEvent($payload1);
        $emailSent->refresh();
        $firstOpen = $emailSent->opened_at;

        $payload2 = [
            'RecordType' => 'Open',
            'MessageID' => 'open-msg-001',
            'ReceivedAt' => '2026-07-16T13:00:00Z',
        ];

        $this->service->handleEvent($payload2);
        $emailSent->refresh();

        $this->assertEquals($firstOpen, $emailSent->opened_at);
    }

    public function test_it_records_first_click_only(): void
    {
        $emailSent = EmailSent::factory()->create([
            'postmark_message_id' => 'click-msg-001',
            'status' => 'sent',
            'clicked_at' => null,
        ]);

        $payload1 = [
            'RecordType' => 'Click',
            'MessageID' => 'click-msg-001',
            'ReceivedAt' => '2026-07-16T14:00:00Z',
        ];

        $this->service->handleEvent($payload1);
        $emailSent->refresh();
        $firstClick = $emailSent->clicked_at;

        $payload2 = [
            'RecordType' => 'Click',
            'MessageID' => 'click-msg-001',
            'ReceivedAt' => '2026-07-16T15:00:00Z',
        ];

        $this->service->handleEvent($payload2);
        $emailSent->refresh();

        $this->assertEquals($firstClick, $emailSent->clicked_at);
    }

    public function test_it_handles_unknown_message_id(): void
    {
        $payload = [
            'RecordType' => 'Delivery',
            'MessageID' => 'nonexistent-msg',
            'DeliveredAt' => '2026-07-16T16:00:00Z',
        ];

        $result = $this->service->handleEvent($payload);

        $this->assertEquals('skipped', $result['action']);
        $this->assertStringContainsString('not found', $result['reason']);
    }

    public function test_it_handles_subscription_change(): void
    {
        $emailSent = EmailSent::factory()->create([
            'postmark_message_id' => 'sub-msg-001',
            'status' => 'sent',
        ]);

        $payload = [
            'RecordType' => 'SubscriptionChange',
            'MessageID' => 'sub-msg-001',
            'SuppressSending' => true,
            'SuppressionReason' => 'ManualSuppression',
        ];

        $result = $this->service->handleEvent($payload);

        $this->assertEquals('subscription_change_recorded', $result['action']);

        $emailSent->refresh();
        $this->assertEquals('failed', $emailSent->status);
    }

    public function test_it_returns_error_for_missing_record_type(): void
    {
        $payload = ['MessageID' => 'no-type'];

        $result = $this->service->handleEvent($payload);

        $this->assertEquals('error', $result['action']);
        $this->assertEquals('Missing RecordType', $result['reason']);
    }
}
