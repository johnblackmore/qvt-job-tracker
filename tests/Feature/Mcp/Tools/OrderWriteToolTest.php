<?php

namespace Tests\Feature\Mcp\Tools;

use App\Mcp\Servers\QvtServer;
use App\Mcp\Tools\CreateOrderTool;
use App\Mcp\Tools\ScheduleInstallationTool;
use App\Mcp\Tools\UpdateDepositTool;
use App\Mcp\Tools\UpdateOrderStatusTool;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrderWriteToolTest extends TestCase
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

    public function test_create_order_preview_no_db_write(): void
    {
        $customer = Customer::factory()->create();
        $countBefore = Order::count();

        $response = QvtServer::actingAs($this->admin)
            ->tool(CreateOrderTool::class, [
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

        $this->assertEquals($countBefore, Order::count());
    }

    public function test_create_order_confirmed_creates_record(): void
    {
        $customer = Customer::factory()->create();

        $response = QvtServer::actingAs($this->admin)
            ->tool(CreateOrderTool::class, [
                'customer_id' => $customer->id,
                'notes' => 'Test order notes',
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) use ($customer) {
            $json->where('status', 'completed');
            $json->has('url');
            $json->has('order.id');
            $json->has('order.reference_number');
            $json->where('order.customer_id', $customer->id);
            $json->where('order.customer_name', $customer->name);
            $json->where('order.status', 'pending');
            $json->where('order.notes', 'Test order notes');
            $json->etc();
        });

        $this->assertDatabaseHas('orders', [
            'customer_id' => $customer->id,
            'status' => 'pending',
            'notes' => 'Test order notes',
        ]);
    }

    public function test_create_order_auto_generates_reference(): void
    {
        $customer = Customer::factory()->create();

        $response = QvtServer::actingAs($this->admin)
            ->tool(CreateOrderTool::class, [
                'customer_id' => $customer->id,
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $json->has('order.reference_number');
            $json->where('order.reference_number', fn (string $ref) => str_starts_with($ref, 'ORD-'));
            $json->etc();
        });
    }

    public function test_create_order_sets_staff_user_id_from_auth_user(): void
    {
        $customer = Customer::factory()->create();

        $response = QvtServer::actingAs($this->admin)
            ->tool(CreateOrderTool::class, [
                'customer_id' => $customer->id,
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('orders', [
            'customer_id' => $customer->id,
            'staff_user_id' => $this->admin->id,
        ]);
    }

    public function test_create_order_from_quote_prefills_totals(): void
    {
        $customer = Customer::factory()->create();
        $quote = Quote::factory()->create([
            'customer_id' => $customer->id,
            'grand_total' => 1000.00,
            'status' => 'accepted',
        ]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(CreateOrderTool::class, [
                'customer_id' => $customer->id,
                'quote_id' => $quote->id,
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertOk();

        $order = Order::latest()->first();
        $this->assertNotNull($order);
        $this->assertEquals(1000.00, (float) $order->total_amount);
        $this->assertEquals(300.00, (float) $order->deposit_required);
        $this->assertEquals(1000.00, (float) $order->balance_due);
        $this->assertEquals($quote->id, $order->quote_id);
    }

    public function test_create_order_from_quote_sets_converted_order_id(): void
    {
        $customer = Customer::factory()->create();
        $quote = Quote::factory()->create([
            'customer_id' => $customer->id,
            'grand_total' => 500.00,
            'status' => 'accepted',
        ]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(CreateOrderTool::class, [
                'customer_id' => $customer->id,
                'quote_id' => $quote->id,
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertOk();

        $order = Order::latest()->first();
        $quote->refresh();
        $this->assertEquals($order->id, $quote->converted_order_id);
    }

    public function test_create_order_rejects_invalid_customer_id(): void
    {
        $response = QvtServer::actingAs($this->admin)
            ->tool(CreateOrderTool::class, [
                'customer_id' => 99999,
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertHasErrors();
    }

    public function test_update_order_status_preview_shows_transition(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'pending',
        ]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(UpdateOrderStatusTool::class, [
                'id' => $order->id,
                'status' => 'in_progress',
                'preview' => true,
                'confirmed' => false,
            ]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) use ($order) {
            $json->where('status', 'preview');
            $json->has('message');
            $json->has('data');
            $json->where('data.id', $order->id);
            $json->where('data.old_status', 'pending');
            $json->where('data.new_status', 'in_progress');
            $json->etc();
        });
    }

    public function test_update_order_status_confirmed_updates_and_stamps_completed_at(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'in_progress',
            'completed_at' => null,
        ]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(UpdateOrderStatusTool::class, [
                'id' => $order->id,
                'status' => 'completed',
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $json->where('status', 'completed');
            $json->has('url');
            $json->where('order.status', 'completed');
            $json->has('order.completed_at');
            $json->etc();
        });

        $order->refresh();
        $this->assertNotNull($order->completed_at);
        $this->assertEquals('completed', $order->status);
    }

    public function test_update_order_status_rejects_invalid_status(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'pending',
        ]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(UpdateOrderStatusTool::class, [
                'id' => $order->id,
                'status' => 'invalid_status',
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertHasErrors();
    }

    public function test_update_deposit_preview_shows_balance(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'total_amount' => 1000.00,
            'deposit_required' => 300.00,
            'deposit_paid' => 0,
            'balance_due' => 1000.00,
            'status' => 'pending',
        ]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(UpdateDepositTool::class, [
                'id' => $order->id,
                'deposit_paid' => 150.00,
                'preview' => true,
                'confirmed' => false,
            ]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) use ($order) {
            $json->where('status', 'preview');
            $json->has('message');
            $json->has('data');
            $json->where('data.id', $order->id);
            $json->where('data.deposit_paid', 150.00);
            $json->where('data.balance_due', 850.00);
            $json->etc();
        });
    }

    public function test_update_deposit_confirmed_updates_and_recalculates_balance(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'total_amount' => 1000.00,
            'deposit_required' => 300.00,
            'deposit_paid' => 0,
            'balance_due' => 1000.00,
            'status' => 'pending',
        ]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(UpdateDepositTool::class, [
                'id' => $order->id,
                'deposit_paid' => 150.00,
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertOk();

        $order->refresh();
        $this->assertEquals(150.00, (float) $order->deposit_paid);
        $this->assertEquals(850.00, (float) $order->balance_due);
    }

    public function test_update_deposit_auto_advances_status_from_pending(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'total_amount' => 1000.00,
            'deposit_required' => 300.00,
            'deposit_paid' => 0,
            'balance_due' => 1000.00,
            'status' => 'pending',
        ]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(UpdateDepositTool::class, [
                'id' => $order->id,
                'deposit_paid' => 150.00,
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertOk();

        $order->refresh();
        $this->assertEquals('deposit_paid', $order->status);
    }

    public function test_update_deposit_rejects_amount_exceeding_total(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'total_amount' => 500.00,
            'deposit_required' => 150.00,
            'deposit_paid' => 0,
            'balance_due' => 500.00,
        ]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(UpdateDepositTool::class, [
                'id' => $order->id,
                'deposit_paid' => 600.00,
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertHasErrors();
    }

    public function test_schedule_installation_preview_shows_new_date(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'scheduled_date' => null,
            'status' => 'pending',
        ]);

        $newDate = now()->addDays(7)->format('Y-m-d');

        $response = QvtServer::actingAs($this->admin)
            ->tool(ScheduleInstallationTool::class, [
                'id' => $order->id,
                'scheduled_date' => $newDate,
                'preview' => true,
                'confirmed' => false,
            ]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) use ($order, $newDate) {
            $json->where('status', 'preview');
            $json->has('message');
            $json->has('data');
            $json->where('data.id', $order->id);
            $json->where('data.new_scheduled_date', $newDate);
            $json->etc();
        });
    }

    public function test_schedule_installation_confirmed_updates_and_advances_status(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'scheduled_date' => null,
            'status' => 'deposit_paid',
        ]);

        $newDate = now()->addDays(14)->format('Y-m-d');

        $response = QvtServer::actingAs($this->admin)
            ->tool(ScheduleInstallationTool::class, [
                'id' => $order->id,
                'scheduled_date' => $newDate,
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertOk();

        $order->refresh();
        $this->assertEquals($newDate, $order->scheduled_date?->toDateString());
        $this->assertEquals('scheduled', $order->status);
    }

    public function test_schedule_installation_rejects_past_date(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'pending',
        ]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(ScheduleInstallationTool::class, [
                'id' => $order->id,
                'scheduled_date' => now()->subDays(1)->format('Y-m-d'),
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertHasErrors();
    }

    public function test_write_order_tools_gated_by_admin_role(): void
    {
        $installer = User::factory()->create();
        Role::create(['name' => 'installer', 'guard_name' => 'web']);
        $installer->assignRole('installer');

        $customer = Customer::factory()->create();

        $response = QvtServer::actingAs($installer)
            ->tool(CreateOrderTool::class, [
                'customer_id' => $customer->id,
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertHasErrors();
    }
}
