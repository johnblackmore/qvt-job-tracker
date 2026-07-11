<?php

namespace Tests\Feature\Mcp\Tools;

use App\Mcp\Servers\QvtServer;
use App\Mcp\Tools\SendQuoteEmailTool;
use App\Models\Customer;
use App\Models\EmailSent;
use App\Models\EmailTemplate;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\Fluent\AssertableJson;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SendQuoteEmailToolTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'admin', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    public function test_preview_no_db_write_no_mail_sent(): void
    {
        Mail::fake();
        $countBefore = EmailSent::count();
        $customer = Customer::factory()->create();
        $quote = Quote::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'draft',
        ]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(SendQuoteEmailTool::class, [
                'quote_id' => $quote->id,
                'preview' => true,
                'confirmed' => false,
            ]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $json->where('status', 'preview');
            $json->has('message');
            $json->has('data');
            $json->etc();
        });

        $this->assertEquals($countBefore, EmailSent::count());
        Mail::assertNothingSent();
    }

    public function test_preview_message_includes_customer_template_and_total(): void
    {
        $customer = Customer::factory()->create(['email' => 'john@example.com', 'name' => 'John Blackmore']);
        $template = EmailTemplate::factory()->create(['name' => 'Standard Quote Email']);
        $quote = Quote::factory()->create([
            'customer_id' => $customer->id,
            'reference_number' => 'Q-TEST-001',
            'grand_total' => 1500.00,
            'status' => 'draft',
        ]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(SendQuoteEmailTool::class, [
                'quote_id' => $quote->id,
                'template_id' => $template->id,
                'preview' => true,
                'confirmed' => false,
            ]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $json->where('status', 'preview');
            $json->has('data');
            $json->where('data.to_email', 'john@example.com');
            $json->where('data.customer_name', 'John Blackmore');
            $json->where('data.template_name', 'Standard Quote Email');
            $json->where('data.grand_total', 1500.00);
            $json->where('data.quote_reference', 'Q-TEST-001');
            $json->etc();
        });
    }

    public function test_preview_rejects_invalid_quote_id(): void
    {
        $response = QvtServer::actingAs($this->admin)
            ->tool(SendQuoteEmailTool::class, [
                'quote_id' => 99999,
                'preview' => true,
                'confirmed' => false,
            ]);

        $response->assertHasErrors();
    }

    public function test_preview_rejects_invalid_template_id(): void
    {
        $customer = Customer::factory()->create();
        $quote = Quote::factory()->create(['customer_id' => $customer->id]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(SendQuoteEmailTool::class, [
                'quote_id' => $quote->id,
                'template_id' => 99999,
                'preview' => true,
                'confirmed' => false,
            ]);

        $response->assertHasErrors();
    }

    public function test_confirmed_creates_email_sent_record_with_status_sent(): void
    {
        Mail::fake();
        $customer = Customer::factory()->create(['email' => 'jane@example.com']);
        $quote = Quote::factory()->create([
            'customer_id' => $customer->id,
            'reference_number' => 'Q-TEST-002',
            'status' => 'draft',
        ]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(SendQuoteEmailTool::class, [
                'quote_id' => $quote->id,
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $json->where('status', 'completed');
            $json->has('url');
            $json->has('email_sent.id');
            $json->where('email_sent.to_email', 'jane@example.com');
            $json->where('email_sent.status', 'sent');
            $json->has('email_sent.sent_at');
            $json->etc();
        });

        $this->assertDatabaseHas('emails_sent', [
            'quote_id' => $quote->id,
            'to_email' => 'jane@example.com',
            'status' => 'sent',
        ]);
    }

    public function test_confirmed_auto_advances_quote_from_draft_to_sent(): void
    {
        Mail::fake();
        $customer = Customer::factory()->create(['email' => 'jane@example.com']);
        $quote = Quote::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'draft',
            'sent_at' => null,
        ]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(SendQuoteEmailTool::class, [
                'quote_id' => $quote->id,
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertOk();

        $quote->refresh();
        $this->assertEquals('sent', $quote->status);
        $this->assertNotNull($quote->sent_at);
    }

    public function test_confirmed_works_without_template(): void
    {
        Mail::fake();
        $customer = Customer::factory()->create(['email' => 'jane@example.com']);
        $quote = Quote::factory()->create(['customer_id' => $customer->id]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(SendQuoteEmailTool::class, [
                'quote_id' => $quote->id,
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $json->where('status', 'completed');
            $json->where('email_sent.status', 'sent');
            $json->etc();
        });

        $this->assertNull(EmailSent::latest()->first()->template_id);
    }

    public function test_customer_with_no_email_returns_error(): void
    {
        $customer = Customer::factory()->create(['email' => null]);
        $quote = Quote::factory()->create(['customer_id' => $customer->id]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(SendQuoteEmailTool::class, [
                'quote_id' => $quote->id,
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertHasErrors();
    }

    public function test_mail_send_failure_returns_error_and_marks_failed(): void
    {
        Mail::shouldReceive('html')->once()->andThrow(new \Exception('Postmark API unreachable'));
        $customer = Customer::factory()->create(['email' => 'jane@example.com']);
        $quote = Quote::factory()->create(['customer_id' => $customer->id]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(SendQuoteEmailTool::class, [
                'quote_id' => $quote->id,
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertHasErrors();

        $this->assertDatabaseHas('emails_sent', [
            'quote_id' => $quote->id,
            'status' => 'failed',
        ]);
    }

    public function test_send_quote_email_tool_gated_by_admin_role(): void
    {
        $installer = User::factory()->create();
        Role::create(['name' => 'installer', 'guard_name' => 'web']);
        $installer->assignRole('installer');

        $customer = Customer::factory()->create(['email' => 'jane@example.com']);
        $quote = Quote::factory()->create(['customer_id' => $customer->id]);

        $response = QvtServer::actingAs($installer)
            ->tool(SendQuoteEmailTool::class, [
                'quote_id' => $quote->id,
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertHasErrors();
    }
}
