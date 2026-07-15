<?php

namespace Tests\Feature\Mcp\Tools;

use App\Mcp\Servers\QvtServer;
use App\Mcp\Tools\DownloadQuotePdfTool;
use App\Models\Customer;
use App\Models\EmailSent;
use App\Models\Quote;
use App\Models\QuoteLineItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DownloadQuotePdfToolTest extends TestCase
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

    public function test_returns_valid_json_with_required_fields(): void
    {
        $customer = Customer::factory()->create();
        $quote = Quote::factory()->create([
            'customer_id' => $customer->id,
            'reference_number' => 'Q-PDF-001',
        ]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(DownloadQuotePdfTool::class, ['quote_id' => $quote->id]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) use ($quote) {
            $json->where('status', 'completed');
            $json->has('message');
            $json->where('quote_id', $quote->id);
            $json->where('quote_reference', 'Q-PDF-001');
            $json->where('filename', 'QVT-Quote-Q-PDF-001.pdf');
            $json->has('size_bytes');
            $json->has('url');
            $json->etc();
        });
    }

    public function test_generated_url_resolves_to_pdf_download_route(): void
    {
        $customer = Customer::factory()->create();
        $quote = Quote::factory()->create([
            'customer_id' => $customer->id,
            'reference_number' => 'Q-PDF-002',
        ]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(DownloadQuotePdfTool::class, ['quote_id' => $quote->id]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) use ($quote) {
            $json->where('url', fn (string $url) => str_contains($url, "/quotes/{$quote->id}/pdf"));
            $json->etc();
        });
    }

    public function test_filename_follows_naming_convention(): void
    {
        $customer = Customer::factory()->create();
        $quote = Quote::factory()->create([
            'customer_id' => $customer->id,
            'reference_number' => 'Q-20260711-ABCD',
        ]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(DownloadQuotePdfTool::class, ['quote_id' => $quote->id]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $json->where('filename', 'QVT-Quote-Q-20260711-ABCD.pdf');
            $json->etc();
        });
    }

    public function test_pdf_content_does_not_include_trade_prices(): void
    {
        $customer = Customer::factory()->create(['name' => 'John Blackmore']);
        $quote = Quote::factory()->create([
            'customer_id' => $customer->id,
            'reference_number' => 'Q-NOTRADE',
            'total_retail' => 500.00,
            'total_trade' => 300.00,
            'labour_total' => 0,
        ]);
        QuoteLineItem::factory()->create([
            'quote_id' => $quote->id,
            'line_type' => 'product',
            'description' => 'Solar Panel',
            'quantity' => 1,
            'unit_retail_price' => 500.00,
            'unit_trade_price' => 300.00,
            'line_total_retail' => 500.00,
            'line_total_trade' => 300.00,
        ]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(DownloadQuotePdfTool::class, ['quote_id' => $quote->id]);

        $response->assertOk();

        $url = route('quotes.pdf.download', $quote);
        $pdfResponse = $this->actingAs($this->admin)->get($url);
        $pdfResponse->assertOk();

        $body = $pdfResponse->getContent();
        $this->assertStringNotContainsString('300.00', $body);
        $this->assertStringNotContainsString('internal_trade', $body);
        $this->assertStringNotContainsString('total_trade', $body);
    }

    public function test_missing_quote_id_returns_error(): void
    {
        $response = QvtServer::actingAs($this->admin)
            ->tool(DownloadQuotePdfTool::class, ['quote_id' => 99999]);

        $response->assertHasErrors();
    }

    public function test_is_read_only_no_db_writes(): void
    {
        $customer = Customer::factory()->create();
        $quote = Quote::factory()->create(['customer_id' => $customer->id]);
        $countBefore = EmailSent::count();
        $quoteCountBefore = Quote::count();

        QvtServer::actingAs($this->admin)
            ->tool(DownloadQuotePdfTool::class, ['quote_id' => $quote->id]);

        $this->assertEquals($countBefore, EmailSent::count());
        $this->assertEquals($quoteCountBefore, Quote::count());
    }

    public function test_download_quote_pdf_tool_gated_by_admin_role(): void
    {
        $installer = User::factory()->create();
        Role::create(['name' => 'installer', 'guard_name' => 'web']);
        $installer->assignRole('installer');

        $customer = Customer::factory()->create();
        $quote = Quote::factory()->create(['customer_id' => $customer->id]);

        $response = QvtServer::actingAs($installer)
            ->tool(DownloadQuotePdfTool::class, ['quote_id' => $quote->id]);

        $response->assertHasErrors();
    }
}
