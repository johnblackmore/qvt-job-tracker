<?php

namespace Tests\Feature\Mcp;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteLineItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TradePriceAuditTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The set of string fragments that must NEVER appear in any
     * customer-facing MCP response body. (Internal-only fields.)
     *
     * `get-product-tool` is the single allowed exception: it serves
     * the staff admin product view and explicitly exposes
     * `internal_trade_price` on supplier pivots. It is tested
     * separately in `ProductToolTest`.
     */
    private const FORBIDDEN_FRAGMENTS = [
        'unit_trade_price',
        'line_total_trade',
        'total_trade',
        'trade_price',
    ];

    protected User $admin;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'admin', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->token = $this->admin->createToken('test-token')->plainTextToken;
    }

    /**
     * Drive the JSON-RPC endpoint and return the structured payload.
     */
    protected function callTool(string $name, array $arguments = []): array
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/mcp/qvt', [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'tools/call',
                'params' => [
                    'name' => $name,
                    'arguments' => $arguments,
                ],
            ]);

        $response->assertOk();

        return $response->json();
    }

    /**
     * Walk an arbitrary array and return every string value found at
     * any depth. Keys that match a forbidden fragment are reported
     * as part of the path.
     */
    protected function collectStringValues(mixed $payload): array
    {
        $hits = [];

        $walker = function (mixed $node, string $path) use (&$walker, &$hits): void {
            if (is_array($node)) {
                foreach ($node as $key => $value) {
                    $walker($value, $path === '' ? (string) $key : $path.'.'.$key);
                }

                return;
            }

            if (is_string($node) && $node !== '') {
                $hits[] = ['path' => $path, 'value' => $node];
            }
        };

        $walker($payload, '');

        return $hits;
    }

    public function test_get_customer_does_not_leak_trade_prices(): void
    {
        $customer = Customer::factory()->create();
        $category = ProductCategory::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id, 'retail_price' => 100]);
        $product->suppliers()->attach($supplier->id, [
            'trade_price' => 60,
            'is_preferred' => true,
        ]);

        $quote = Quote::factory()->create([
            'customer_id' => $customer->id,
            'total_retail' => 1000,
            'total_trade' => 600,
            'grand_total' => 1000,
        ]);
        QuoteLineItem::factory()->create([
            'quote_id' => $quote->id,
            'product_id' => $product->id,
            'unit_retail_price' => 100,
            'unit_trade_price' => 60,
            'line_total_retail' => 100,
            'line_total_trade' => 60,
        ]);

        $payload = $this->callTool('get-customer-tool', ['id' => $customer->id]);

        $this->assertNoForbiddenFragments($payload);
    }

    public function test_list_customers_does_not_leak_trade_prices(): void
    {
        $customer = Customer::factory()->create();
        $quote = Quote::factory()->create([
            'customer_id' => $customer->id,
            'total_retail' => 500,
            'total_trade' => 300,
        ]);
        QuoteLineItem::factory()->create([
            'quote_id' => $quote->id,
            'unit_trade_price' => 50,
            'line_total_trade' => 50,
        ]);

        $payload = $this->callTool('list-customers-tool', ['per_page' => 50]);

        $this->assertNoForbiddenFragments($payload);
    }

    public function test_search_customers_does_not_leak_trade_prices(): void
    {
        $customer = Customer::factory()->create(['name' => 'Blackmore Trading Ltd']);
        $quote = Quote::factory()->create([
            'customer_id' => $customer->id,
            'total_trade' => 999.99,
        ]);

        $payload = $this->callTool('search-customers-tool', ['query' => 'Blackmore']);

        $this->assertNoForbiddenFragments($payload);
    }

    public function test_list_products_does_not_leak_trade_prices(): void
    {
        $category = ProductCategory::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);
        $product->suppliers()->attach($supplier->id, [
            'trade_price' => 99.99,
            'is_preferred' => true,
        ]);

        $payload = $this->callTool('list-products-tool', ['per_page' => 50]);

        $this->assertNoForbiddenFragments($payload);
    }

    public function test_search_products_does_not_leak_trade_prices(): void
    {
        $category = ProductCategory::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Victron Multiplus 2000',
        ]);
        $product->suppliers()->attach($supplier->id, [
            'trade_price' => 450.00,
        ]);

        $payload = $this->callTool('search-products-tool', ['query' => 'Victron']);

        $this->assertNoForbiddenFragments($payload);
    }

    public function test_list_orders_does_not_leak_trade_prices(): void
    {
        $customer = Customer::factory()->create();
        $quote = Quote::factory()->create([
            'customer_id' => $customer->id,
            'total_trade' => 250,
        ]);
        Order::factory()->create([
            'customer_id' => $customer->id,
            'quote_id' => $quote->id,
        ]);

        $payload = $this->callTool('list-orders-tool', ['per_page' => 50]);

        $this->assertNoForbiddenFragments($payload);
    }

    public function test_get_order_does_not_leak_trade_prices(): void
    {
        $customer = Customer::factory()->create();
        $quote = Quote::factory()->create([
            'customer_id' => $customer->id,
            'total_retail' => 1000,
            'total_trade' => 600,
        ]);
        QuoteLineItem::factory()->create([
            'quote_id' => $quote->id,
            'unit_trade_price' => 200,
            'line_total_trade' => 200,
        ]);
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'quote_id' => $quote->id,
        ]);

        $payload = $this->callTool('get-order-tool', ['id' => $order->id]);

        $this->assertNoForbiddenFragments($payload);
    }

    public function test_get_quote_activity_does_not_leak_trade_prices(): void
    {
        $customer = Customer::factory()->create();
        $quote = Quote::factory()->create([
            'customer_id' => $customer->id,
            'total_trade' => 750,
        ]);

        $payload = $this->callTool('get-quote-activity-tool', [
            'since' => now()->subDays(30)->toDateString(),
            'until' => now()->toDateString(),
        ]);

        $this->assertNoForbiddenFragments($payload);
    }

    public function test_get_dashboard_stats_does_not_leak_trade_prices(): void
    {
        $customer = Customer::factory()->create();
        Quote::factory()->create([
            'customer_id' => $customer->id,
            'total_trade' => 1234.56,
        ]);

        $payload = $this->callTool('get-dashboard-stats-tool');

        $this->assertNoForbiddenFragments($payload);
    }

    public function test_get_weekly_summary_does_not_leak_trade_prices(): void
    {
        $customer = Customer::factory()->create();
        Quote::factory()->create([
            'customer_id' => $customer->id,
            'total_trade' => 5000,
        ]);

        $payload = $this->callTool('get-weekly-summary-tool');

        $this->assertNoForbiddenFragments($payload);
    }

    public function test_customer_profile_resource_does_not_leak_trade_prices(): void
    {
        $customer = Customer::factory()->create();
        $quote = Quote::factory()->create([
            'customer_id' => $customer->id,
            'total_trade' => 999,
        ]);
        QuoteLineItem::factory()->create([
            'quote_id' => $quote->id,
            'unit_trade_price' => 80,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/mcp/qvt', [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'resources/read',
                'params' => [
                    'uri' => "qvt://customers/{$customer->id}",
                ],
            ]);

        $response->assertOk();
        $this->assertNoForbiddenFragments($response->json());
    }

    public function test_quote_details_resource_does_not_leak_trade_prices(): void
    {
        $customer = Customer::factory()->create();
        $quote = Quote::factory()->create([
            'customer_id' => $customer->id,
            'total_trade' => 700,
        ]);
        QuoteLineItem::factory()->create([
            'quote_id' => $quote->id,
            'unit_trade_price' => 70,
            'line_total_trade' => 70,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/mcp/qvt', [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'resources/read',
                'params' => [
                    'uri' => "qvt://quotes/{$quote->id}",
                ],
            ]);

        $response->assertOk();
        $this->assertNoForbiddenFragments($response->json());
    }

    public function test_order_details_resource_does_not_leak_trade_prices(): void
    {
        $customer = Customer::factory()->create();
        $quote = Quote::factory()->create([
            'customer_id' => $customer->id,
            'total_trade' => 850,
        ]);
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'quote_id' => $quote->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/mcp/qvt', [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'resources/read',
                'params' => [
                    'uri' => "qvt://orders/{$order->id}",
                ],
            ]);

        $response->assertOk();
        $this->assertNoForbiddenFragments($response->json());
    }

    /**
     * Assert that no key in the payload tree matches a forbidden
     * fragment. Values are checked too (defence in depth in case
     * some future tool embeds the string in a `message` field).
     */
    protected function assertNoForbiddenFragments(array $payload): void
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);

        foreach (self::FORBIDDEN_FRAGMENTS as $fragment) {
            $this->assertStringNotContainsString(
                $fragment,
                (string) $json,
                "Found forbidden trade-price fragment [{$fragment}] in MCP response."
            );
        }
    }
}
