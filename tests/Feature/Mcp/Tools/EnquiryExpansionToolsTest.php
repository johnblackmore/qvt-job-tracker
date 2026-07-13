<?php

namespace Tests\Feature\Mcp\Tools;

use App\Mcp\Servers\QvtServer;
use App\Mcp\Tools\CreateEnquiryReplyTool;
use App\Mcp\Tools\CreateQuoteFromEnquiryTool;
use App\Mcp\Tools\GenerateEnquiryDraftTool;
use App\Mcp\Tools\GetEnquiryTool;
use App\Mcp\Tools\ListEnquiryRepliesTool;
use App\Mcp\Tools\SaveEnquiryDraftTool;
use App\Models\Customer;
use App\Models\Enquiry;
use App\Models\EnquiryReply;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\Fluent\AssertableJson;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EnquiryExpansionToolsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        Role::create(['name' => 'admin', 'guard_name' => 'web']);
        Role::create(['name' => 'installer', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    public function test_get_enquiry_returns_enquiry_with_relationships(): void
    {
        $customer = Customer::factory()->create(['name' => 'Test Customer']);
        $enquiry = Enquiry::factory()->create([
            'customer_id' => $customer->id,
            'subject' => 'Test enquiry',
            'message' => 'I need a battery.',
        ]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(GetEnquiryTool::class, [
                'enquiry_id' => $enquiry->id,
            ]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) use ($enquiry) {
            $json->where('status', 'completed')
                ->where('enquiry.id', $enquiry->id)
                ->where('enquiry.customer_name', 'Test Customer')
                ->where('enquiry.subject', 'Test enquiry')
                ->has('enquiry.reply_count')
                ->has('enquiry.quote_count')
                ->etc();
        });
    }

    public function test_get_enquiry_rejects_invalid_id(): void
    {
        $response = QvtServer::actingAs($this->admin)
            ->tool(GetEnquiryTool::class, [
                'enquiry_id' => 99999,
            ]);

        $response->assertHasErrors();
    }

    public function test_create_enquiry_reply_preview_no_changes(): void
    {
        $customer = Customer::factory()->create(['email' => 'customer@test.com']);
        $enquiry = Enquiry::factory()->create([
            'customer_id' => $customer->id,
            'subject' => 'Battery enquiry',
        ]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(CreateEnquiryReplyTool::class, [
                'enquiry_id' => $enquiry->id,
                'subject' => 'Re: Battery enquiry',
                'body' => 'Thank you for your enquiry.',
                'preview' => true,
                'confirmed' => false,
            ]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $json->where('status', 'preview')
                ->etc();
        });

        $this->assertEquals(0, EnquiryReply::count());
    }

    public function test_create_enquiry_reply_confirmed_sends(): void
    {
        $customer = Customer::factory()->create(['email' => 'customer@test.com']);
        $enquiry = Enquiry::factory()->create([
            'customer_id' => $customer->id,
            'subject' => 'Battery enquiry',
        ]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(CreateEnquiryReplyTool::class, [
                'enquiry_id' => $enquiry->id,
                'subject' => 'Re: Battery enquiry',
                'body' => 'Thank you for your enquiry.',
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $json->where('status', 'completed')
                ->where('reply.subject', 'Re: Battery enquiry')
                ->where('reply.status', 'sent')
                ->etc();
        });

        $this->assertDatabaseHas('enquiry_replies', [
            'enquiry_id' => $enquiry->id,
            'subject' => 'Re: Battery enquiry',
            'status' => 'sent',
        ]);

        $enquiry->refresh();
        $this->assertEquals('responded', $enquiry->status);
    }

    public function test_create_enquiry_reply_rejects_missing_enquiry(): void
    {
        $response = QvtServer::actingAs($this->admin)
            ->tool(CreateEnquiryReplyTool::class, [
                'enquiry_id' => 99999,
                'body' => 'Thank you.',
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertHasErrors();
    }

    public function test_create_enquiry_reply_rejects_no_email(): void
    {
        $enquiry = Enquiry::factory()->create([
            'customer_id' => null,
            'email' => null,
        ]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(CreateEnquiryReplyTool::class, [
                'enquiry_id' => $enquiry->id,
                'body' => 'Thank you.',
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertHasErrors();
    }

    public function test_list_enquiry_replies_returns_thread(): void
    {
        $enquiry = Enquiry::factory()->create();
        EnquiryReply::factory()->count(3)->create([
            'enquiry_id' => $enquiry->id,
            'staff_user_id' => $this->admin->id,
            'direction' => 'outbound',
            'status' => 'sent',
        ]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(ListEnquiryRepliesTool::class, [
                'enquiry_id' => $enquiry->id,
            ]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $json->where('status', 'completed')
                ->has('replies', 3)
                ->etc();
        });
    }

    public function test_list_enquiry_replies_empty(): void
    {
        $enquiry = Enquiry::factory()->create();

        $response = QvtServer::actingAs($this->admin)
            ->tool(ListEnquiryRepliesTool::class, [
                'enquiry_id' => $enquiry->id,
            ]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $json->where('status', 'completed')
                ->has('replies', 0)
                ->etc();
        });
    }

    public function test_create_quote_from_enquiry_preview_no_changes(): void
    {
        $customer = Customer::factory()->create();
        $enquiry = Enquiry::factory()->create([
            'customer_id' => $customer->id,
            'subject' => 'Solar panel enquiry',
        ]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(CreateQuoteFromEnquiryTool::class, [
                'enquiry_id' => $enquiry->id,
                'preview' => true,
                'confirmed' => false,
            ]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $json->where('status', 'preview')
                ->etc();
        });

        $this->assertEquals(0, Quote::count());
    }

    public function test_create_quote_from_enquiry_confirmed_creates_quote(): void
    {
        $customer = Customer::factory()->create();
        $enquiry = Enquiry::factory()->create([
            'customer_id' => $customer->id,
            'subject' => 'Solar panel enquiry',
        ]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(CreateQuoteFromEnquiryTool::class, [
                'enquiry_id' => $enquiry->id,
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $json->where('status', 'completed')
                ->has('quote.reference_number')
                ->etc();
        });

        $this->assertDatabaseHas('quotes', [
            'enquiry_id' => $enquiry->id,
            'customer_id' => $customer->id,
        ]);

        $this->assertDatabaseHas('enquiry_activity_logs', [
            'enquiry_id' => $enquiry->id,
            'action' => 'quote_created',
        ]);
    }

    public function test_create_quote_from_enquiry_rejects_no_customer(): void
    {
        $enquiry = Enquiry::factory()->create([
            'customer_id' => null,
        ]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(CreateQuoteFromEnquiryTool::class, [
                'enquiry_id' => $enquiry->id,
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertHasErrors();
    }

    public function test_generate_enquiry_draft_returns_draft_structure(): void
    {
        $customer = Customer::factory()->create(['name' => 'John']);
        $enquiry = Enquiry::factory()->create([
            'customer_id' => $customer->id,
            'subject' => 'Battery query',
            'message' => 'I need a 200Ah lithium battery for my campervan.',
        ]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(GenerateEnquiryDraftTool::class, [
                'enquiry_id' => $enquiry->id,
                'tone' => 'professional',
            ]);

        $response->assertOk();
    }

    public function test_save_enquiry_draft_preview_no_changes(): void
    {
        $enquiry = Enquiry::factory()->create();

        $response = QvtServer::actingAs($this->admin)
            ->tool(SaveEnquiryDraftTool::class, [
                'enquiry_id' => $enquiry->id,
                'subject' => 'Draft subject',
                'body' => 'Draft body text',
                'preview' => true,
                'confirmed' => false,
            ]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $json->where('status', 'preview')
                ->etc();
        });

        $this->assertEquals(0, EnquiryReply::count());
    }

    public function test_save_enquiry_draft_confirmed_creates_draft(): void
    {
        $enquiry = Enquiry::factory()->create();

        $response = QvtServer::actingAs($this->admin)
            ->tool(SaveEnquiryDraftTool::class, [
                'enquiry_id' => $enquiry->id,
                'subject' => 'Draft subject',
                'body' => 'Draft body text',
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $json->where('status', 'completed')
                ->where('reply.subject', 'Draft subject')
                ->where('reply.status', 'draft')
                ->etc();
        });

        $this->assertDatabaseHas('enquiry_replies', [
            'enquiry_id' => $enquiry->id,
            'subject' => 'Draft subject',
            'status' => 'draft',
        ]);
    }

    public function test_new_tools_gated_by_admin_role(): void
    {
        $installer = User::factory()->create();
        $installer->assignRole('installer');

        $enquiry = Enquiry::factory()->create();

        $toolClasses = [
            GetEnquiryTool::class,
            CreateEnquiryReplyTool::class,
            ListEnquiryRepliesTool::class,
            CreateQuoteFromEnquiryTool::class,
            GenerateEnquiryDraftTool::class,
            SaveEnquiryDraftTool::class,
        ];

        foreach ($toolClasses as $toolClass) {
            $response = QvtServer::actingAs($installer)
                ->tool($toolClass, [
                    'enquiry_id' => $enquiry->id,
                    'body' => 'test',
                    'preview' => false,
                    'confirmed' => true,
                ]);

            $response->assertHasErrors();
        }
    }
}
