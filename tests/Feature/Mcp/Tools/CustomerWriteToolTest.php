<?php

namespace Tests\Feature\Mcp\Tools;

use App\Mcp\Servers\QvtServer;
use App\Mcp\Tools\CreateCustomerTool;
use App\Mcp\Tools\DeleteCustomerTool;
use App\Mcp\Tools\UpdateCustomerTool;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CustomerWriteToolTest extends TestCase
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

    public function test_create_customer_preview_returns_preview_data_without_db_write(): void
    {
        $countBefore = Customer::count();

        $response = QvtServer::actingAs($this->admin)
            ->tool(CreateCustomerTool::class, [
                'name' => 'John Blackmore',
                'email' => 'john@example.com',
                'preview' => true,
                'confirmed' => false,
            ]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $json->where('status', 'preview');
            $json->has('message');
            $json->has('data');
            $json->where('data.name', 'John Blackmore');
            $json->where('data.email', 'john@example.com');
            $json->etc();
        });

        $this->assertEquals($countBefore, Customer::count());
    }

    public function test_create_customer_confirmed_creates_record_with_url(): void
    {
        $response = QvtServer::actingAs($this->admin)
            ->tool(CreateCustomerTool::class, [
                'name' => 'Jane Doe',
                'email' => 'jane@example.com',
                'phone' => '01234567890',
                'address' => '123 Main St',
                'notes' => 'Important customer',
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $json->where('status', 'completed');
            $json->has('url');
            $json->has('customer.id');
            $json->where('customer.name', 'Jane Doe');
            $json->where('customer.email', 'jane@example.com');
            $json->where('customer.phone', '01234567890');
            $json->where('customer.address', '123 Main St');
            $json->where('customer.notes', 'Important customer');
            $json->etc();
        });

        $this->assertDatabaseHas('customers', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '01234567890',
        ]);
    }

    public function test_create_customer_requires_name(): void
    {
        $response = QvtServer::actingAs($this->admin)
            ->tool(CreateCustomerTool::class, [
                'email' => 'no-name@example.com',
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertHasErrors();
    }

    public function test_create_customer_missing_both_preview_and_confirmed_returns_error(): void
    {
        $response = QvtServer::actingAs($this->admin)
            ->tool(CreateCustomerTool::class, [
                'name' => 'Test Person',
                'preview' => false,
                'confirmed' => false,
            ]);

        $response->assertHasErrors();
    }

    public function test_update_customer_preview_returns_data_without_db_write(): void
    {
        $customer = Customer::factory()->create(['name' => 'Original Name']);

        $response = QvtServer::actingAs($this->admin)
            ->tool(UpdateCustomerTool::class, [
                'id' => $customer->id,
                'name' => 'New Name',
                'preview' => true,
                'confirmed' => false,
            ]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) use ($customer) {
            $json->where('status', 'preview');
            $json->has('message');
            $json->has('data');
            $json->where('data.id', $customer->id);
            $json->where('data.name', 'New Name');
            $json->etc();
        });

        $customer->refresh();
        $this->assertEquals('Original Name', $customer->name);
    }

    public function test_update_customer_confirmed_updates_record(): void
    {
        $customer = Customer::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
            'phone' => '0000000000',
        ]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(UpdateCustomerTool::class, [
                'id' => $customer->id,
                'name' => 'Updated Name',
                'email' => 'updated@example.com',
                'phone' => '0987654321',
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $json->where('status', 'completed');
            $json->has('url');
            $json->where('customer.name', 'Updated Name');
            $json->where('customer.email', 'updated@example.com');
            $json->where('customer.phone', '0987654321');
            $json->etc();
        });

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'phone' => '0987654321',
        ]);
    }

    public function test_update_customer_partial_update_preserves_unchanged_fields(): void
    {
        $customer = Customer::factory()->create([
            'name' => 'John Blackmore',
            'email' => 'john@example.com',
            'phone' => '01234567890',
        ]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(UpdateCustomerTool::class, [
                'id' => $customer->id,
                'name' => 'John B. Updated',
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertOk();

        $customer->refresh();
        $this->assertEquals('John B. Updated', $customer->name);
        $this->assertEquals('john@example.com', $customer->email);
        $this->assertEquals('01234567890', $customer->phone);
    }

    public function test_update_customer_rejects_invalid_id(): void
    {
        $response = QvtServer::actingAs($this->admin)
            ->tool(UpdateCustomerTool::class, [
                'id' => 99999,
                'name' => 'New Name',
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertHasErrors();
    }

    public function test_delete_customer_preview_shows_warning_with_linked_counts(): void
    {
        $customer = Customer::factory()->create();
        Vehicle::factory()->count(2)->create(['customer_id' => $customer->id]);
        Quote::factory()->count(1)->create(['customer_id' => $customer->id]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(DeleteCustomerTool::class, [
                'id' => $customer->id,
                'preview' => true,
                'confirmed' => false,
            ]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) use ($customer) {
            $json->where('status', 'preview');
            $json->has('message');
            $json->has('data');
            $json->where('data.id', $customer->id);
            $json->where('data.vehicles_count', 2);
            $json->where('data.quotes_count', 1);
            $json->etc();
        });
    }

    public function test_delete_customer_confirmed_deletes_record(): void
    {
        $customer = Customer::factory()->create();
        $id = $customer->id;

        $response = QvtServer::actingAs($this->admin)
            ->tool(DeleteCustomerTool::class, [
                'id' => $id,
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) use ($id) {
            $json->where('status', 'completed');
            $json->has('message');
            $json->where('deleted_id', $id);
            $json->etc();
        });

        $this->assertSoftDeleted('customers', ['id' => $id]);
    }

    public function test_write_customer_tools_gated_by_admin_role(): void
    {
        $installer = User::factory()->create();
        Role::create(['name' => 'installer', 'guard_name' => 'web']);
        $installer->assignRole('installer');

        $response = QvtServer::actingAs($installer)
            ->tool(CreateCustomerTool::class, [
                'name' => 'Should Fail',
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertHasErrors();
    }
}
