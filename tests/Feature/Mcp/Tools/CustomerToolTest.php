<?php

namespace Tests\Feature\Mcp\Tools;

use App\Mcp\Servers\QvtServer;
use App\Mcp\Tools\GetCustomerTool;
use App\Mcp\Tools\ListCustomersTool;
use App\Mcp\Tools\SearchCustomersTool;
use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CustomerToolTest extends TestCase
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

    public function test_list_customers_returns_paginated_results(): void
    {
        Customer::factory()->count(5)->create();

        $response = QvtServer::actingAs($this->admin)
            ->tool(ListCustomersTool::class, ['per_page' => 3]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $json->where('status', 'completed');
            $json->has('data', 3);
            $json->where('pagination.per_page', 3);
            $json->where('pagination.total', 5);
            $json->etc();
        });
    }

    public function test_list_customers_includes_url_per_item(): void
    {
        $customer = Customer::factory()->create();

        $response = QvtServer::actingAs($this->admin)
            ->tool(ListCustomersTool::class, []);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) use ($customer) {
            $json->has('data.0.url')
                ->where('data.0.url', fn (string $url) => str_contains($url, "/customers/{$customer->id}"));
            $json->etc();
        });
    }

    public function test_list_customers_filters_by_search(): void
    {
        Customer::factory()->create(['name' => 'John Smith']);
        Customer::factory()->create(['name' => 'Jane Doe']);

        $response = QvtServer::actingAs($this->admin)
            ->tool(ListCustomersTool::class, ['search' => 'John']);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $json->has('data', 1);
            $json->where('data.0.name', 'John Smith');
            $json->etc();
        });
    }

    public function test_list_customers_respects_max_per_page(): void
    {
        Customer::factory()->count(5)->create();

        $response = QvtServer::actingAs($this->admin)
            ->tool(ListCustomersTool::class, ['per_page' => 150]);

        $response->assertHasErrors();
    }

    public function test_get_customer_returns_full_record(): void
    {
        $customer = Customer::factory()->create(['name' => 'John Blackmore']);
        Vehicle::factory()->count(2)->create(['customer_id' => $customer->id]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(GetCustomerTool::class, ['id' => $customer->id]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) use ($customer) {
            $json->where('status', 'completed');
            $json->where('customer.name', 'John Blackmore');
            $json->where('customer.id', $customer->id);
            $json->has('customer.vehicles', 2);
            $json->has('url');
            $json->etc();
        });
    }

    public function test_get_customer_returns_error_for_missing_id(): void
    {
        $response = QvtServer::actingAs($this->admin)
            ->tool(GetCustomerTool::class, ['id' => 99999]);

        $response->assertHasErrors();
    }

    public function test_search_customers_matches_name_email_phone(): void
    {
        Customer::factory()->create([
            'name' => 'John Smith',
            'email' => 'john@example.com',
            'phone' => '0123456789',
        ]);
        Customer::factory()->create(['name' => 'Jane Doe']);

        $response = QvtServer::actingAs($this->admin)
            ->tool(SearchCustomersTool::class, ['query' => 'john']);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $json->has('data', 1);
            $json->where('data.0.name', 'John Smith');
            $json->etc();
        });
    }

    public function test_customer_tools_are_read_only(): void
    {
        Customer::factory()->count(3)->create();
        $countBefore = Customer::count();

        QvtServer::actingAs($this->admin)
            ->tool(ListCustomersTool::class, []);

        $this->assertEquals($countBefore, Customer::count());
    }

    public function test_customer_tools_gated_by_admin_role(): void
    {
        $installer = User::factory()->create();
        Role::create(['name' => 'installer', 'guard_name' => 'web']);
        $installer->assignRole('installer');

        $response = QvtServer::actingAs($installer)
            ->tool(ListCustomersTool::class, []);

        $response->assertHasErrors();
    }
}
