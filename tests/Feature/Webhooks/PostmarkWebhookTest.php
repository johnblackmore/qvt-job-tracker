<?php

namespace Tests\Feature\Webhooks;

use App\Jobs\ProcessInboundEmailJob;
use App\Models\EmailSent;
use App\Models\Enquiry;
use App\Models\EnquiryReply;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PostmarkWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected string $secret = 'test-webhook-secret';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.postmark.webhook_secret', $this->secret);
        Config::set('services.netlify.notification_email', null);
    }

    public function test_it_returns_200_for_valid_inbound_payload(): void
    {
        Config::set('queue.default', 'sync');

        $payload = [
            'From' => 'test@example.com',
            'FromName' => 'Test User',
            'To' => 'enquiries@qvt.quantockvantech.com',
            'Subject' => 'Test enquiry',
            'TextBody' => 'Hello, I need a quote.',
            'StrippedTextReply' => 'Hello, I need a quote.',
            'MessageID' => 'webhook-test-001',
        ];

        $response = $this->postJson('/webhooks/postmark', $payload, [
            'X-Postmark-Secret' => $this->secret,
        ]);

        $response->assertOk();
        $response->assertSee('OK');
    }

    public function test_it_rejects_requests_without_valid_secret(): void
    {
        $payload = [
            'From' => 'test@example.com',
            'Subject' => 'Unauthorized',
            'TextBody' => 'Should be rejected.',
        ];

        $response = $this->postJson('/webhooks/postmark', $payload, [
            'X-Postmark-Secret' => 'wrong-secret',
        ]);

        $response->assertStatus(401);
    }

    public function test_it_rejects_requests_without_secret_header(): void
    {
        $payload = [
            'From' => 'test@example.com',
            'Subject' => 'No secret',
            'TextBody' => 'Should be rejected.',
        ];

        $response = $this->postJson('/webhooks/postmark', $payload);

        $response->assertStatus(401);
    }

    public function test_it_processes_inbound_payload_and_creates_enquiry(): void
    {
        Config::set('queue.default', 'sync');

        $payload = [
            'From' => 'new-customer@example.com',
            'FromName' => 'New Customer',
            'To' => 'enquiries@qvt.quantockvantech.com',
            'Subject' => 'Campervan Electrical System',
            'TextBody' => 'I need a full system install.',
            'StrippedTextReply' => 'I need a full system install.',
            'MessageID' => 'webhook-test-002',
        ];

        $this->postJson('/webhooks/postmark', $payload, [
            'X-Postmark-Secret' => $this->secret,
        ]);

        $reply = EnquiryReply::where('postmark_message_id', 'webhook-test-002')->first();
        $this->assertNotNull($reply);
        $this->assertEquals('inbound', $reply->direction);
        $this->assertNotNull($reply->enquiry);
        $this->assertEquals('email', $reply->enquiry->source);
    }

    public function test_it_dispatches_job_for_inbound_payload(): void
    {
        Queue::fake();

        $payload = [
            'From' => 'job-test@example.com',
            'To' => 'enquiries@qvt.quantockvantech.com',
            'Subject' => 'Job test',
            'TextBody' => 'Testing job dispatch.',
            'MessageID' => 'webhook-test-003',
        ];

        $this->postJson('/webhooks/postmark', $payload, [
            'X-Postmark-Secret' => $this->secret,
        ]);

        Queue::assertPushed(ProcessInboundEmailJob::class, function ($job) {
            return ($job->payload['MessageID'] ?? '') === 'webhook-test-003';
        });
    }

    public function test_it_routes_outbound_events_to_email_webhook_service(): void
    {
        $emailSent = EmailSent::factory()->create([
            'postmark_message_id' => 'outbound-test-001',
            'status' => 'pending',
        ]);

        $payload = [
            'RecordType' => 'Delivery',
            'MessageID' => 'outbound-test-001',
            'DeliveredAt' => '2026-07-16T10:00:00Z',
        ];

        $this->postJson('/webhooks/postmark', $payload, [
            'X-Postmark-Secret' => $this->secret,
        ]);

        $emailSent->refresh();
        $this->assertEquals('sent', $emailSent->status);
        $this->assertNotNull($emailSent->delivered_at);
    }

    public function test_it_handles_malformed_payload_gracefully(): void
    {
        $payload = [
            'RandomField' => 'garbage',
            'NoFromField' => true,
        ];

        $response = $this->postJson('/webhooks/postmark', $payload, [
            'X-Postmark-Secret' => $this->secret,
        ]);

        $response->assertOk();
    }

    public function test_it_threads_reply_via_webhook(): void
    {
        Config::set('queue.default', 'sync');

        $enquiry = Enquiry::factory()->create([
            'email' => 'existing@example.com',
        ]);

        $payload = [
            'From' => 'existing@example.com',
            'FromName' => 'Existing Customer',
            'To' => 'enquiries+qvt.quantockvantech.com',
            'Subject' => 'Re: Your Quote',
            'TextBody' => 'Yes, please proceed.',
            'StrippedTextReply' => 'Yes, please proceed.',
            'MessageID' => 'webhook-test-thread-001',
            'MailboxHash' => 'enquiry-'.$enquiry->id,
        ];

        $this->postJson('/webhooks/postmark', $payload, [
            'X-Postmark-Secret' => $this->secret,
        ]);

        $reply = EnquiryReply::where('postmark_message_id', 'webhook-test-thread-001')->first();
        $this->assertNotNull($reply);
        $this->assertEquals($enquiry->id, $reply->enquiry_id);
        $this->assertEquals('inbound', $reply->direction);
    }
}
