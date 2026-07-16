<?php

namespace Tests\Feature\Services;

use App\Models\Customer;
use App\Models\Enquiry;
use App\Models\EnquiryReply;
use App\Services\InboundEmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InboundEmailServiceTest extends TestCase
{
    use RefreshDatabase;

    protected InboundEmailService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(InboundEmailService::class);
    }

    public function test_it_creates_new_customer_and_enquiry_from_inbound_email(): void
    {
        $payload = [
            'From' => 'john@example.com',
            'FromName' => 'John Doe',
            'Subject' => 'Campervan Electrical Quote',
            'TextBody' => 'Hi, I need a full electrical system for my VW T6.',
            'StrippedTextReply' => 'Hi, I need a full electrical system for my VW T6.',
            'MessageID' => 'msg-001',
            'MailboxHash' => '',
            'Headers' => [],
        ];

        $result = $this->service->process($payload);

        $this->assertEquals('created', $result['action']);

        $customer = Customer::where('email', 'john@example.com')->first();
        $this->assertNotNull($customer);
        $this->assertEquals('John Doe', $customer->name);

        $enquiry = Enquiry::where('customer_id', $customer->id)->first();
        $this->assertNotNull($enquiry);
        $this->assertEquals('Campervan Electrical Quote', $enquiry->subject);
        $this->assertEquals('email', $enquiry->source);
        $this->assertEquals('new', $enquiry->status);
        $this->assertEquals('John Doe', $enquiry->from_name);
        $this->assertEquals('john@example.com', $enquiry->email);

        $reply = EnquiryReply::where('enquiry_id', $enquiry->id)->first();
        $this->assertNotNull($reply);
        $this->assertEquals('inbound', $reply->direction);
        $this->assertEquals('received', $reply->status);
        $this->assertEquals('msg-001', $reply->postmark_message_id);
    }

    public function test_it_threads_a_reply_via_mailbox_hash(): void
    {
        $enquiry = Enquiry::factory()->create();

        $payload = [
            'From' => 'customer@example.com',
            'FromName' => 'Jane Smith',
            'Subject' => 'Re: Your Enquiry',
            'TextBody' => 'Yes, please proceed with the quote.',
            'StrippedTextReply' => 'Yes, please proceed with the quote.',
            'MessageID' => 'msg-002',
            'MailboxHash' => 'enquiry-'.$enquiry->id,
            'Headers' => [],
        ];

        $result = $this->service->process($payload);

        $this->assertEquals('threaded', $result['action']);
        $this->assertEquals($enquiry->id, $result['enquiry_id']);

        $reply = EnquiryReply::where('enquiry_id', $enquiry->id)
            ->where('direction', 'inbound')
            ->first();
        $this->assertNotNull($reply);
        $this->assertEquals('msg-002', $reply->postmark_message_id);

        $enquiry->refresh();
        $this->assertEquals('responded', $enquiry->status);
    }

    public function test_it_threads_a_reply_via_in_reply_to_header(): void
    {
        $enquiry = Enquiry::factory()->create();
        $originalReply = EnquiryReply::factory()->create([
            'enquiry_id' => $enquiry->id,
            'direction' => 'outbound',
            'message_id' => 'enquiry.'.$enquiry->id.'.123456@qvt.quantockvantech.com',
        ]);

        $payload = [
            'From' => 'customer@example.com',
            'FromName' => 'Jane Smith',
            'Subject' => 'Re: Your Enquiry',
            'TextBody' => 'Thanks for the quote.',
            'StrippedTextReply' => 'Thanks for the quote.',
            'MessageID' => 'msg-003',
            'MailboxHash' => '',
            'Headers' => [
                ['Name' => 'In-Reply-To', 'Value' => $originalReply->message_id],
            ],
        ];

        $result = $this->service->process($payload);

        $this->assertEquals('threaded', $result['action']);
        $this->assertEquals($enquiry->id, $result['enquiry_id']);
    }

    public function test_it_threads_a_reply_via_references_header(): void
    {
        $enquiry = Enquiry::factory()->create();
        $originalReply = EnquiryReply::factory()->create([
            'enquiry_id' => $enquiry->id,
            'direction' => 'outbound',
            'message_id' => 'enquiry.'.$enquiry->id.'.789012@qvt.quantockvantech.com',
        ]);

        $payload = [
            'From' => 'customer@example.com',
            'FromName' => 'Jane Smith',
            'Subject' => 'Re: Your Enquiry',
            'TextBody' => 'Sounds good.',
            'StrippedTextReply' => 'Sounds good.',
            'MessageID' => 'msg-004',
            'MailboxHash' => '',
            'Headers' => [
                ['Name' => 'References', 'Value' => '<msg-xyz@other.com> <'.$originalReply->message_id.'>'],
            ],
        ];

        $result = $this->service->process($payload);

        $this->assertEquals('threaded', $result['action']);
        $this->assertEquals($enquiry->id, $result['enquiry_id']);
    }

    public function test_it_skips_duplicate_postmark_message_ids(): void
    {
        $enquiry = Enquiry::factory()->create();
        EnquiryReply::factory()->create([
            'enquiry_id' => $enquiry->id,
            'direction' => 'inbound',
            'postmark_message_id' => 'msg-005',
        ]);

        $payload = [
            'From' => 'customer@example.com',
            'Subject' => 'Duplicate test',
            'TextBody' => 'This should be skipped.',
            'MessageID' => 'msg-005',
            'MailboxHash' => '',
            'Headers' => [],
        ];

        $result = $this->service->process($payload);

        $this->assertEquals('skipped', $result['action']);
        $this->assertEquals(1, EnquiryReply::where('postmark_message_id', 'msg-005')->count());
    }

    public function test_it_creates_inbound_reply_with_correct_direction(): void
    {
        $payload = [
            'From' => 'test@example.com',
            'FromName' => 'Test User',
            'Subject' => 'Test',
            'TextBody' => 'Hello',
            'MessageID' => 'msg-006',
            'MailboxHash' => '',
            'Headers' => [],
        ];

        $this->service->process($payload);

        $reply = EnquiryReply::where('postmark_message_id', 'msg-006')->first();
        $this->assertEquals('inbound', $reply->direction);
        $this->assertEquals('received', $reply->status);
        $this->assertEquals('test@example.com', $reply->from_email);
        $this->assertEquals('Test User', $reply->from_name);
    }

    public function test_it_finds_existing_customer_by_email(): void
    {
        $customer = Customer::factory()->create([
            'email' => 'existing@example.com',
            'name' => 'Existing Customer',
        ]);

        $payload = [
            'From' => 'existing@example.com',
            'FromName' => 'Existing Customer',
            'Subject' => 'Another enquiry',
            'TextBody' => 'Following up on my previous message.',
            'MessageID' => 'msg-007',
            'MailboxHash' => '',
            'Headers' => [],
        ];

        $result = $this->service->process($payload);

        $this->assertEquals('created', $result['action']);
        $this->assertEquals($customer->id, $result['customer_id']);

        $enquiry = Enquiry::find($result['enquiry_id']);
        $this->assertEquals($customer->id, $enquiry->customer_id);
    }

    public function test_it_creates_customer_when_no_match(): void
    {
        $this->assertNull(Customer::where('email', 'new@example.com')->first());

        $payload = [
            'From' => 'new@example.com',
            'FromName' => 'New Person',
            'Subject' => 'New enquiry',
            'TextBody' => 'I am a new customer.',
            'MessageID' => 'msg-008',
            'MailboxHash' => '',
            'Headers' => [],
        ];

        $this->service->process($payload);

        $customer = Customer::where('email', 'new@example.com')->first();
        $this->assertNotNull($customer);
        $this->assertEquals('New Person', $customer->name);
    }

    public function test_it_logs_activity_for_new_enquiry(): void
    {
        $payload = [
            'From' => 'activity@example.com',
            'FromName' => 'Activity Test',
            'Subject' => 'Activity check',
            'TextBody' => 'Check activity log.',
            'MessageID' => 'msg-009',
            'MailboxHash' => '',
            'Headers' => [],
        ];

        $this->service->process($payload);

        $enquiry = Enquiry::where('email', 'activity@example.com')->first();
        $this->assertNotNull($enquiry);

        $log = $enquiry->activityLogs()->first();
        $this->assertNotNull($log);
        $this->assertEquals('created_from_email', $log->action);
        $this->assertEquals('activity@example.com', $log->metadata['from_email']);
    }

    public function test_it_logs_activity_for_threaded_reply(): void
    {
        $enquiry = Enquiry::factory()->create();

        $payload = [
            'From' => 'reply@example.com',
            'FromName' => 'Reply Person',
            'Subject' => 'Re: Original',
            'TextBody' => 'Here is my reply.',
            'MessageID' => 'msg-010',
            'MailboxHash' => 'enquiry-'.$enquiry->id,
            'Headers' => [],
        ];

        $this->service->process($payload);

        $log = $enquiry->activityLogs()->first();
        $this->assertNotNull($log);
        $this->assertEquals('reply_received', $log->action);
    }

    public function test_it_uses_stripped_text_reply_when_available(): void
    {
        $payload = [
            'From' => 'prefer@example.com',
            'Subject' => 'Stripped test',
            'TextBody' => 'Full email including quoted content...',
            'StrippedTextReply' => 'Just my reply.',
            'MessageID' => 'msg-011',
            'MailboxHash' => '',
            'Headers' => [],
        ];

        $this->service->process($payload);

        $enquiry = Enquiry::where('email', 'prefer@example.com')->first();
        $this->assertEquals('Just my reply.', $enquiry->message);
    }

    public function test_it_returns_error_for_missing_sender(): void
    {
        $payload = [
            'Subject' => 'No sender',
            'TextBody' => 'Missing from field.',
            'MessageID' => 'msg-012',
            'MailboxHash' => '',
            'Headers' => [],
        ];

        $result = $this->service->process($payload);

        $this->assertEquals('error', $result['action']);
        $this->assertEquals('Missing sender email', $result['reason']);
    }
}
