<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Resources\CustomerProfileResource;
use App\Mcp\Resources\OrderDetailsResource;
use App\Mcp\Resources\QuoteDetailsResource;
use App\Mcp\Servers\QvtServer;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Quote;
use App\Models\QuoteLineItem;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ResourcesTest extends TestCase
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

    public function test_customer_profile_resource_returns_full_record(): void
    {
        $customer = Customer::factory()->create(['name' => 'John Blackmore']);
        Vehicle::factory()->count(2)->create(['customer_id' => $customer->id]);
        Quote::factory()->create(['customer_id' => $customer->id]);

        $response = QvtServer::actingAs($this->admin)
            ->resource(CustomerProfileResource::class, ['id' => $customer->id]);

        $response->assertOk();
        $response->assertSee(['John Blackmore', '"vehicles"', '"quotes"']);
    }

    public function test_quote_details_resource_returns_retail_prices_only(): void
    {
        $customer = Customer::factory()->create();
        $quote = Quote::factory()->create([
            'customer_id' => $customer->id,
            'total_retail' => 500.00,
            'total_trade' => 300.00,
            'grand_total' => 500.00,
        ]);
        QuoteLineItem::factory()->create([
            'quote_id' => $quote->id,
            'unit_retail_price' => 500.00,
            'unit_trade_price' => 300.00,
            'line_total_retail' => 500.00,
            'line_total_trade' => 300.00,
        ]);

        $response = QvtServer::actingAs($this->admin)
            ->resource(QuoteDetailsResource::class, ['id' => $quote->id]);

        $response->assertOk();
        $response->assertSee([
            '"grand_total":',
            '"total_retail":',
            '"unit_retail_price":',
        ]);
        $response->assertDontSee([
            'unit_trade_price',
            'total_trade',
            'line_total_trade',
            'internal_trade',
        ]);
    }

    public function test_order_details_resource_returns_full_record_with_deposit_percent(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'total_amount' => 1000.00,
            'deposit_required' => 300.00,
            'deposit_paid' => 150.00,
            'balance_due' => 850.00,
        ]);

        $response = QvtServer::actingAs($this->admin)
            ->resource(OrderDetailsResource::class, ['id' => $order->id]);

        $response->assertOk();
        $response->assertSee([
            '"total_amount":',
            '"deposit_required":',
            '"deposit_paid":',
            '"deposit_percent":',
            '"balance_due":',
        ]);
    }

    public function test_invalid_resource_uri_returns_error(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->admin->createToken('test')->plainTextToken)
            ->postJson('/mcp/qvt', [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'resources/read',
                'params' => [
                    'uri' => 'qvt://customers/99999',
                ],
            ]);

        $response->assertOk();
        $this->assertArrayHasKey('error', $response->json());
    }

    public function test_resources_are_admin_gated(): void
    {
        $installer = User::factory()->create();
        Role::create(['name' => 'installer', 'guard_name' => 'web']);
        $installer->assignRole('installer');

        $response = QvtServer::actingAs($installer)
            ->resource(CustomerProfileResource::class, ['id' => 1]);

        $response->assertHasErrors();
    }
}
