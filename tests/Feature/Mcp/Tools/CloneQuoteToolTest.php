<?php

namespace Tests\Feature\Mcp\Tools;

use App\Mcp\Servers\QvtServer;
use App\Mcp\Tools\CloneQuoteTool;
use App\Models\Customer;
use App\Models\Enquiry;
use App\Models\Quote;
use App\Models\QuoteLineItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CloneQuoteToolTest extends TestCase
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

    public function test_preview_returns_correct_data_no_db_write(): void
    {
        $customer = Customer::factory()->create();
        $quote = Quote::factory()->create([
            'customer_id' => $customer->id,
            'reference_number' => 'Q-ORIG-001',
        ]);

        QuoteLineItem::factory()->create([
            'quote_id' => $quote->id,
            'line_type' => 'product',
            'description' => 'Widget',
            'quantity' => 2,
            'unit_retail_price' => 50.00,
            'unit_trade_price' => 30.00,
            'line_total_retail' => 100.00,
            'line_total_trade' => 60.00,
        ]);

        $countBefore = Quote::count();

        $response = QvtServer::actingAs($this->admin)
            ->tool(CloneQuoteTool::class, [
                'quote_id' => $quote->id,
                'preview' => true,
                'confirmed' => false,
            ]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) use ($customer, $quote) {
            $json->where('status', 'preview');
            $json->has('message');
            $json->has('data');
            $json->where('data.source_quote_id', $quote->id);
            $json->where('data.source_reference', 'Q-ORIG-001');
            $json->where('data.customer_id', $customer->id);
            $json->where('data.customer_name', $customer->name);
            $json->where('data.customer_overridden', false);
            $json->has('data.line_items', 1);
            $json->etc();
        });

        $this->assertEquals($countBefore, Quote::count());
    }

    public function test_execute_creates_cloned_quote_with_copied_line_items(): void
    {
        $customer = Customer::factory()->create();
        $quote = Quote::factory()->create([
            'customer_id' => $customer->id,
            'notes' => 'Original notes for cloning',
        ]);

        QuoteLineItem::factory()->create([
            'quote_id' => $quote->id,
            'line_type' => 'product',
            'description' => 'Widget A',
            'quantity' => 2,
            'unit_retail_price' => 50.00,
            'unit_trade_price' => 30.00,
            'line_total_retail' => 100.00,
            'line_total_trade' => 60.00,
        ]);

        QuoteLineItem::factory()->create([
            'quote_id' => $quote->id,
            'line_type' => 'labour',
            'description' => 'Fitting',
            'quantity' => 1,
            'unit_retail_price' => 150.00,
            'unit_trade_price' => 0,
            'line_total_retail' => 150.00,
            'line_total_trade' => 0,
        ]);

        $countBefore = Quote::count();

        $response = QvtServer::actingAs($this->admin)
            ->tool(CloneQuoteTool::class, [
                'quote_id' => $quote->id,
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) use ($customer) {
            $json->where('status', 'completed');
            $json->has('url');
            $json->has('quote.id');
            $json->has('quote.reference_number');
            $json->where('quote.customer_id', $customer->id);
            $json->where('quote.customer_name', $customer->name);
            $json->where('quote.status', 'draft');
            $json->where('quote.grand_total', '250.00');
            $json->where('quote.total_retail', '100.00');
            $json->where('quote.labour_total', '150.00');
            $json->where('quote.line_items_count', 2);
            $json->etc();
        });

        $this->assertEquals($countBefore + 1, Quote::count());

        $newQuote = Quote::where('id', '!=', $quote->id)->latest()->first();
        $this->assertNotNull($newQuote);
        $this->assertNotEquals($newQuote->reference_number, $quote->reference_number);
        $this->assertEquals($customer->id, $newQuote->customer_id);
        $this->assertEquals('draft', $newQuote->status);
        $this->assertEquals('Original notes for cloning', $newQuote->notes);

        $this->assertEquals(2, $newQuote->lineItems->count());

        $productLine = $newQuote->lineItems->where('line_type', 'product')->first();
        $this->assertEquals('Widget A', $productLine->description);
        $this->assertEquals(50.00, (float) $productLine->unit_retail_price);
        $this->assertEquals(30.00, (float) $productLine->unit_trade_price);
        $this->assertEquals(100.00, (float) $productLine->line_total_retail);
    }

    public function test_execute_resets_reference_status_valid_until(): void
    {
        $customer = Customer::factory()->create();
        $quote = Quote::factory()->create([
            'customer_id' => $customer->id,
            'reference_number' => 'Q-OLD-REF',
            'status' => 'sent',
            'valid_until' => now()->subDays(10)->format('Y-m-d'),
        ]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(CloneQuoteTool::class, [
                'quote_id' => $quote->id,
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertOk();

        $newQuote = Quote::where('id', '!=', $quote->id)->latest()->first();
        $this->assertNotEquals('Q-OLD-REF', $newQuote->reference_number);
        $this->assertEquals('draft', $newQuote->status);
        $this->assertEquals(now()->addDays(30)->format('Y-m-d'), $newQuote->valid_until->format('Y-m-d'));
    }

    public function test_execute_copies_prices_verbatim(): void
    {
        $customer = Customer::factory()->create();
        $quote = Quote::factory()->create(['customer_id' => $customer->id]);

        QuoteLineItem::factory()->create([
            'quote_id' => $quote->id,
            'line_type' => 'product',
            'description' => 'Custom Price Item',
            'quantity' => 3,
            'unit_retail_price' => 123.45,
            'unit_trade_price' => 78.90,
            'line_total_retail' => 370.35,
            'line_total_trade' => 236.70,
        ]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(CloneQuoteTool::class, [
                'quote_id' => $quote->id,
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertOk();

        $newQuote = Quote::where('id', '!=', $quote->id)->latest()->first();
        $lineItem = $newQuote->lineItems->first();

        $this->assertEquals(123.45, (float) $lineItem->unit_retail_price);
        $this->assertEquals(78.90, (float) $lineItem->unit_trade_price);
        $this->assertEquals(370.35, (float) $lineItem->line_total_retail);
    }

    public function test_execute_sets_staff_user_id_from_auth(): void
    {
        $customer = Customer::factory()->create();
        $quote = Quote::factory()->create(['customer_id' => $customer->id]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(CloneQuoteTool::class, [
                'quote_id' => $quote->id,
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertOk();

        $newQuote = Quote::where('id', '!=', $quote->id)->latest()->first();
        $this->assertEquals($this->admin->id, $newQuote->staff_user_id);
    }

    public function test_execute_with_optional_customer_override(): void
    {
        $originalCustomer = Customer::factory()->create();
        $newCustomer = Customer::factory()->create();
        $quote = Quote::factory()->create(['customer_id' => $originalCustomer->id]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(CloneQuoteTool::class, [
                'quote_id' => $quote->id,
                'customer_id' => $newCustomer->id,
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) use ($newCustomer) {
            $json->where('status', 'completed');
            $json->where('quote.customer_id', $newCustomer->id);
            $json->where('quote.customer_name', $newCustomer->name);
            $json->etc();
        });
    }

    public function test_execute_with_notes_override(): void
    {
        $customer = Customer::factory()->create();
        $quote = Quote::factory()->create([
            'customer_id' => $customer->id,
            'notes' => 'Old notes',
        ]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(CloneQuoteTool::class, [
                'quote_id' => $quote->id,
                'notes' => 'New notes',
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertOk();

        $newQuote = Quote::where('id', '!=', $quote->id)->latest()->first();
        $this->assertEquals('New notes', $newQuote->notes);
    }

    public function test_preview_with_customer_override_shows_flag(): void
    {
        $original = Customer::factory()->create();
        $override = Customer::factory()->create();
        $quote = Quote::factory()->create(['customer_id' => $original->id]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(CloneQuoteTool::class, [
                'quote_id' => $quote->id,
                'customer_id' => $override->id,
                'preview' => true,
                'confirmed' => false,
            ]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) use ($override) {
            $json->where('status', 'preview');
            $json->where('data.customer_id', $override->id);
            $json->where('data.customer_overridden', true);
            $json->etc();
        });
    }

    public function test_validation_error_with_invalid_quote_id(): void
    {
        $response = QvtServer::actingAs($this->admin)
            ->tool(CloneQuoteTool::class, [
                'quote_id' => 99999,
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertHasErrors();
    }

    public function test_validation_error_without_preview_or_confirmed(): void
    {
        $customer = Customer::factory()->create();
        $quote = Quote::factory()->create(['customer_id' => $customer->id]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(CloneQuoteTool::class, [
                'quote_id' => $quote->id,
                'preview' => false,
                'confirmed' => false,
            ]);

        $response->assertHasErrors();
    }

    public function test_unauthenticated_request_returns_empty(): void
    {
        $customer = Customer::factory()->create();
        $quote = Quote::factory()->create(['customer_id' => $customer->id]);

        $installer = User::factory()->create();
        Role::create(['name' => 'installer', 'guard_name' => 'web']);
        $installer->assignRole('installer');

        $response = QvtServer::actingAs($installer)
            ->tool(CloneQuoteTool::class, [
                'quote_id' => $quote->id,
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertHasErrors();
    }

    public function test_execute_carries_over_enquiry_link(): void
    {
        $customer = Customer::factory()->create();
        $enquiry = Enquiry::factory()->create(['customer_id' => $customer->id]);
        $quote = Quote::factory()->create([
            'customer_id' => $customer->id,
            'enquiry_id' => $enquiry->id,
        ]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(CloneQuoteTool::class, [
                'quote_id' => $quote->id,
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertOk();

        $newQuote = Quote::where('id', '!=', $quote->id)->latest()->first();
        $this->assertEquals($enquiry->id, $newQuote->enquiry_id);
    }
}
