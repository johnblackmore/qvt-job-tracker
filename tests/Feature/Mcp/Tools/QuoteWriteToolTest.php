<?php

namespace Tests\Feature\Mcp\Tools;

use App\Mcp\Servers\QvtServer;
use App\Mcp\Tools\AddQuoteLineItemTool;
use App\Mcp\Tools\CreateQuoteFromTemplateTool;
use App\Mcp\Tools\CreateQuoteTool;
use App\Mcp\Tools\UpdateQuoteStatusTool;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\SampleQuote;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class QuoteWriteToolTest extends TestCase
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

    public function test_create_quote_preview_no_db_write(): void
    {
        $customer = Customer::factory()->create();
        $countBefore = Quote::count();

        $response = QvtServer::actingAs($this->admin)
            ->tool(CreateQuoteTool::class, [
                'customer_id' => $customer->id,
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

        $this->assertEquals($countBefore, Quote::count());
    }

    public function test_create_quote_confirmed_creates_record_with_reference(): void
    {
        $customer = Customer::factory()->create();

        $response = QvtServer::actingAs($this->admin)
            ->tool(CreateQuoteTool::class, [
                'customer_id' => $customer->id,
                'notes' => 'Test notes',
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
            $json->where('quote.notes', 'Test notes');
            $json->etc();
        });

        $this->assertDatabaseHas('quotes', [
            'customer_id' => $customer->id,
            'status' => 'draft',
            'notes' => 'Test notes',
        ]);
    }

    public function test_create_quote_sets_staff_user_id_from_auth_user(): void
    {
        $customer = Customer::factory()->create();

        $response = QvtServer::actingAs($this->admin)
            ->tool(CreateQuoteTool::class, [
                'customer_id' => $customer->id,
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('quotes', [
            'customer_id' => $customer->id,
            'staff_user_id' => $this->admin->id,
        ]);
    }

    public function test_create_quote_valid_until_defaults_to_thirty_days(): void
    {
        $customer = Customer::factory()->create();
        $expectedDate = now()->addDays(30)->format('Y-m-d');

        $response = QvtServer::actingAs($this->admin)
            ->tool(CreateQuoteTool::class, [
                'customer_id' => $customer->id,
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) use ($expectedDate) {
            $json->where('quote.valid_until', $expectedDate);
            $json->etc();
        });
    }

    public function test_create_quote_rejects_invalid_customer_id(): void
    {
        $response = QvtServer::actingAs($this->admin)
            ->tool(CreateQuoteTool::class, [
                'customer_id' => 99999,
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertHasErrors();
    }

    public function test_create_quote_from_template_preview_lists_line_items(): void
    {
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Test Solar Panel',
            'retail_price' => 299.99,
        ]);
        $supplier = Supplier::factory()->create();
        $product->suppliers()->attach($supplier->id, [
            'trade_price' => 180.00,
            'is_preferred' => true,
        ]);

        $sampleQuote = SampleQuote::factory()->create([
            'name' => 'Solar Kit',
            'line_items' => [
                [
                    'line_type' => 'product',
                    'product_id' => $product->id,
                    'description' => 'Test Solar Panel',
                    'quantity' => 2,
                    'unit_retail_price' => 250.00,
                    'unit_trade_price' => 150.00,
                ],
                [
                    'line_type' => 'labour',
                    'description' => 'Installation',
                    'quantity' => 1,
                    'unit_retail_price' => 200.00,
                    'unit_trade_price' => 0,
                ],
            ],
        ]);

        $customer = Customer::factory()->create();

        $response = QvtServer::actingAs($this->admin)
            ->tool(CreateQuoteFromTemplateTool::class, [
                'sample_quote_id' => $sampleQuote->id,
                'customer_id' => $customer->id,
                'preview' => true,
                'confirmed' => false,
            ]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) use ($customer) {
            $json->where('status', 'preview');
            $json->has('message');
            $json->has('data');
            $json->where('data.customer_id', $customer->id);
            $json->where('data.sample_quote_name', 'Solar Kit');
            $json->has('data.line_items', 2);
            $json->etc();
        });

        $this->assertEquals(0, Quote::count());
    }

    public function test_create_quote_from_template_confirmed_clones_with_current_prices(): void
    {
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Current Product',
            'retail_price' => 399.99,
        ]);
        $supplier = Supplier::factory()->create();
        $product->suppliers()->attach($supplier->id, [
            'trade_price' => 240.00,
            'is_preferred' => true,
        ]);

        $sampleQuote = SampleQuote::factory()->create([
            'name' => 'Updated Kit',
            'line_items' => [
                [
                    'line_type' => 'product',
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_retail_price' => 350.00,
                    'unit_trade_price' => 210.00,
                ],
            ],
        ]);

        $customer = Customer::factory()->create();

        $response = QvtServer::actingAs($this->admin)
            ->tool(CreateQuoteFromTemplateTool::class, [
                'sample_quote_id' => $sampleQuote->id,
                'customer_id' => $customer->id,
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertOk();

        $quote = Quote::latest()->first();
        $this->assertNotNull($quote);

        $lineItem = $quote->lineItems->first();
        $this->assertEquals(399.99, (float) $lineItem->unit_retail_price);
        $this->assertEquals(240.00, (float) $lineItem->unit_trade_price);
    }

    public function test_create_quote_from_template_recalculates_grand_total(): void
    {
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'retail_price' => 100.00,
        ]);

        $sampleQuote = SampleQuote::factory()->create([
            'line_items' => [
                [
                    'line_type' => 'product',
                    'product_id' => $product->id,
                    'quantity' => 3,
                    'unit_retail_price' => 100.00,
                    'unit_trade_price' => 60.00,
                ],
            ],
        ]);

        $customer = Customer::factory()->create();

        $response = QvtServer::actingAs($this->admin)
            ->tool(CreateQuoteFromTemplateTool::class, [
                'sample_quote_id' => $sampleQuote->id,
                'customer_id' => $customer->id,
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $json->where('quote.grand_total', '300.00');
            $json->where('quote.total_retail', '300.00');
            $json->where('quote.line_items_count', 1);
            $json->etc();
        });
    }

    public function test_add_quote_line_item_preview_shows_details(): void
    {
        $customer = Customer::factory()->create();
        $quote = Quote::factory()->create([
            'customer_id' => $customer->id,
            'total_retail' => 0,
            'total_trade' => 0,
            'labour_total' => 0,
            'grand_total' => 0,
        ]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(AddQuoteLineItemTool::class, [
                'quote_id' => $quote->id,
                'line_type' => 'labour',
                'description' => 'Fitting',
                'quantity' => 2,
                'unit_retail_price' => 150.00,
                'preview' => true,
                'confirmed' => false,
            ]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) use ($quote) {
            $json->where('status', 'preview');
            $json->has('message');
            $json->has('data');
            $json->where('data.quote_id', $quote->id);
            $json->where('data.line_type', 'labour');
            $json->where('data.new_grand_total', 300.00);
            $json->etc();
        });
    }

    public function test_add_quote_line_item_confirmed_persists_and_recomputes_totals(): void
    {
        $customer = Customer::factory()->create();
        $quote = Quote::factory()->create([
            'customer_id' => $customer->id,
            'total_retail' => 100.00,
            'total_trade' => 60.00,
            'labour_total' => 0,
            'grand_total' => 100.00,
        ]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(AddQuoteLineItemTool::class, [
                'quote_id' => $quote->id,
                'line_type' => 'ad_hoc',
                'description' => 'Custom cable',
                'quantity' => 2,
                'unit_retail_price' => 25.00,
                'unit_trade_price' => 15.00,
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $json->where('status', 'completed');
            $json->has('url');
            $json->where('quote.grand_total', 150.00);
            $json->where('quote.total_retail', 150.00);
            $json->where('quote.line_items_count', 1);
            $json->etc();
        });

        $quote->refresh();
        $this->assertEquals(150.00, (float) $quote->grand_total);
        $this->assertEquals(150.00, (float) $quote->total_retail);
        $this->assertEquals(90.00, (float) $quote->total_trade);
    }

    public function test_add_quote_line_item_auto_populates_prices_from_product(): void
    {
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Auto Product',
            'retail_price' => 199.99,
        ]);
        $supplier = Supplier::factory()->create();
        $product->suppliers()->attach($supplier->id, [
            'trade_price' => 120.00,
            'is_preferred' => true,
        ]);

        $customer = Customer::factory()->create();
        $quote = Quote::factory()->create([
            'customer_id' => $customer->id,
            'total_retail' => 0,
            'total_trade' => 0,
            'labour_total' => 0,
            'grand_total' => 0,
        ]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(AddQuoteLineItemTool::class, [
                'quote_id' => $quote->id,
                'line_type' => 'product',
                'product_id' => $product->id,
                'quantity' => 1,
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertOk();

        $quote->refresh();
        $lineItem = $quote->lineItems->first();
        $this->assertNotNull($lineItem);
        $this->assertEquals('Auto Product', $lineItem->description);
        $this->assertEquals(199.99, (float) $lineItem->unit_retail_price);
        $this->assertEquals(120.00, (float) $lineItem->unit_trade_price);
    }

    public function test_add_quote_line_item_explicit_price_overrides_auto_populated(): void
    {
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'retail_price' => 199.99,
        ]);

        $customer = Customer::factory()->create();
        $quote = Quote::factory()->create([
            'customer_id' => $customer->id,
            'total_retail' => 0,
            'total_trade' => 0,
            'labour_total' => 0,
            'grand_total' => 0,
        ]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(AddQuoteLineItemTool::class, [
                'quote_id' => $quote->id,
                'line_type' => 'product',
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_retail_price' => 250.00,
                'unit_trade_price' => 150.00,
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertOk();

        $quote->refresh();
        $lineItem = $quote->lineItems->first();
        $this->assertEquals(250.00, (float) $lineItem->unit_retail_price);
        $this->assertEquals(150.00, (float) $lineItem->unit_trade_price);
    }

    public function test_add_quote_line_item_rejects_invalid_line_type(): void
    {
        $customer = Customer::factory()->create();
        $quote = Quote::factory()->create(['customer_id' => $customer->id]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(AddQuoteLineItemTool::class, [
                'quote_id' => $quote->id,
                'line_type' => 'invalid_type',
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertHasErrors();
    }

    public function test_update_quote_status_preview_shows_transition(): void
    {
        $customer = Customer::factory()->create();
        $quote = Quote::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'draft',
        ]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(UpdateQuoteStatusTool::class, [
                'id' => $quote->id,
                'status' => 'sent',
                'preview' => true,
                'confirmed' => false,
            ]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) use ($quote) {
            $json->where('status', 'preview');
            $json->has('message');
            $json->has('data');
            $json->where('data.id', $quote->id);
            $json->where('data.old_status', 'draft');
            $json->where('data.new_status', 'sent');
            $json->etc();
        });
    }

    public function test_update_quote_status_confirmed_updates_and_stamps_sent_at(): void
    {
        $customer = Customer::factory()->create();
        $quote = Quote::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'draft',
            'sent_at' => null,
        ]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(UpdateQuoteStatusTool::class, [
                'id' => $quote->id,
                'status' => 'sent',
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $json->where('status', 'completed');
            $json->has('url');
            $json->where('quote.status', 'sent');
            $json->has('quote.sent_at');
            $json->etc();
        });

        $quote->refresh();
        $this->assertNotNull($quote->sent_at);
        $this->assertEquals('sent', $quote->status);
    }

    public function test_update_quote_status_confirmed_stamps_accepted_at(): void
    {
        $customer = Customer::factory()->create();
        $quote = Quote::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'sent',
            'accepted_at' => null,
        ]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(UpdateQuoteStatusTool::class, [
                'id' => $quote->id,
                'status' => 'accepted',
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertOk();

        $quote->refresh();
        $this->assertNotNull($quote->accepted_at);
        $this->assertEquals('accepted', $quote->status);
    }

    public function test_update_quote_status_idempotent_same_status_no_error(): void
    {
        $customer = Customer::factory()->create();
        $quote = Quote::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'sent',
        ]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(UpdateQuoteStatusTool::class, [
                'id' => $quote->id,
                'status' => 'sent',
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $json->where('status', 'completed');
            $json->etc();
        });
    }

    public function test_update_quote_status_rejects_invalid_status(): void
    {
        $customer = Customer::factory()->create();
        $quote = Quote::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'draft',
        ]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(UpdateQuoteStatusTool::class, [
                'id' => $quote->id,
                'status' => 'invalid_status',
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertHasErrors();
    }

    public function test_quote_write_tools_gated_by_admin_role(): void
    {
        $installer = User::factory()->create();
        Role::create(['name' => 'installer', 'guard_name' => 'web']);
        $installer->assignRole('installer');

        $customer = Customer::factory()->create();

        $response = QvtServer::actingAs($installer)
            ->tool(CreateQuoteTool::class, [
                'customer_id' => $customer->id,
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertHasErrors();
    }
}
