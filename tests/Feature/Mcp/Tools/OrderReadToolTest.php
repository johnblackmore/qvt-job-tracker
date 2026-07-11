<?php

namespace Tests\Feature\Mcp\Tools;

use App\Mcp\Servers\QvtServer;
use App\Mcp\Tools\GetOrderTool;
use App\Mcp\Tools\ListOrdersTool;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrderReadToolTest extends TestCase
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

    public function test_list_orders_returns_paginated_results(): void
    {
        Order::factory()->count(5)->create();

        $response = QvtServer::actingAs($this->admin)
            ->tool(ListOrdersTool::class, ['per_page' => 3]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $json->where('status', 'completed');
            $json->has('data', 3);
            $json->where('pagination.per_page', 3);
            $json->where('pagination.total', 5);
            $json->etc();
        });
    }

    public function test_list_orders_includes_url_per_item(): void
    {
        $order = Order::factory()->create();

        $response = QvtServer::actingAs($this->admin)
            ->tool(ListOrdersTool::class, []);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) use ($order) {
            $json->has('data.0.url')
                ->where('data.0.url', fn (string $url) => str_contains($url, "/orders/{$order->id}"));
            $json->etc();
        });
    }

    public function test_list_orders_filters_by_status(): void
    {
        Order::factory()->create(['status' => 'pending']);
        Order::factory()->create(['status' => 'completed']);

        $response = QvtServer::actingAs($this->admin)
            ->tool(ListOrdersTool::class, ['status' => 'completed']);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $json->has('data', 1);
            $json->where('data.0.status', 'completed');
            $json->etc();
        });
    }

    public function test_list_orders_filters_by_customer_id(): void
    {
        $customer = Customer::factory()->create();
        Order::factory()->create(['customer_id' => $customer->id]);
        Order::factory()->create();

        $response = QvtServer::actingAs($this->admin)
            ->tool(ListOrdersTool::class, ['customer_id' => $customer->id]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $json->has('data', 1);
            $json->etc();
        });
    }

    public function test_list_orders_filters_by_since(): void
    {
        Order::factory()->create(['created_at' => now()->subDays(10)]);
        Order::factory()->create(['created_at' => now()->subDays(2)]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(ListOrdersTool::class, ['since' => now()->subDays(5)->format('Y-m-d')]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $json->has('data', 1);
            $json->etc();
        });
    }

    public function test_get_order_returns_full_record_with_relations(): void
    {
        $customer = Customer::factory()->create();
        $quote = Quote::factory()->create(['customer_id' => $customer->id]);
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'quote_id' => $quote->id,
            'staff_user_id' => $this->admin->id,
        ]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(GetOrderTool::class, ['id' => $order->id]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) use ($order, $customer, $quote) {
            $json->where('status', 'completed');
            $json->where('order.id', $order->id);
            $json->where('order.reference_number', $order->reference_number);
            $json->where('order.customer.id', $customer->id);
            $json->where('order.customer.name', $customer->name);
            $json->where('order.quote.id', $quote->id);
            $json->where('order.quote.reference_number', $quote->reference_number);
            $json->where('order.staff.id', $this->admin->id);
            $json->where('order.staff.name', $this->admin->name);
            $json->has('url');
            $json->etc();
        });
    }

    public function test_get_order_returns_error_for_missing_id(): void
    {
        $response = QvtServer::actingAs($this->admin)
            ->tool(GetOrderTool::class, ['id' => 99999]);

        $response->assertHasErrors();
    }

    public function test_order_tools_are_read_only(): void
    {
        Order::factory()->count(3)->create();
        $countBefore = Order::count();

        QvtServer::actingAs($this->admin)
            ->tool(ListOrdersTool::class, []);

        $this->assertEquals($countBefore, Order::count());
    }
}
