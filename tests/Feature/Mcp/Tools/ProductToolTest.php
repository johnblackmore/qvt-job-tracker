<?php

namespace Tests\Feature\Mcp\Tools;

use App\Mcp\Servers\QvtServer;
use App\Mcp\Tools\GetProductTool;
use App\Mcp\Tools\ListProductsTool;
use App\Mcp\Tools\SearchProductsTool;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductToolTest extends TestCase
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

    public function test_list_products_returns_paginated_results(): void
    {
        Product::factory()->count(5)->create();

        $response = QvtServer::actingAs($this->admin)
            ->tool(ListProductsTool::class, ['per_page' => 3]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $json->where('status', 'completed');
            $json->has('data', 3);
            $json->where('pagination.total', 5);
            $json->etc();
        });
    }

    public function test_list_products_filters_by_category_id(): void
    {
        $category = ProductCategory::factory()->create();
        Product::factory()->create(['category_id' => $category->id, 'name' => 'Solar Panel']);
        Product::factory()->create(['name' => 'Battery']);

        $response = QvtServer::actingAs($this->admin)
            ->tool(ListProductsTool::class, ['category_id' => $category->id]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $json->has('data', 1);
            $json->where('data.0.name', 'Solar Panel');
            $json->etc();
        });
    }

    public function test_list_products_filters_by_is_active(): void
    {
        Product::factory()->create(['name' => 'Active Product', 'is_active' => true]);
        Product::factory()->create(['name' => 'Inactive Product', 'is_active' => false]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(ListProductsTool::class, ['is_active' => true]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $json->has('data', 1);
            $json->where('data.0.name', 'Active Product');
            $json->etc();
        });
    }

    public function test_get_product_returns_product_with_category_and_suppliers(): void
    {
        $category = ProductCategory::factory()->create(['name' => 'Solar']);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'name' => '100W Solar Panel',
            'sku' => 'SOL-100',
        ]);
        $supplier = Supplier::factory()->create(['name' => 'Victron Energy']);
        $product->suppliers()->attach($supplier->id, [
            'trade_price' => 250.00,
            'supplier_sku' => 'VIC-SOL100',
            'is_preferred' => true,
            'lead_time_days' => 7,
        ]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(GetProductTool::class, ['id' => $product->id]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $json->where('product.name', '100W Solar Panel');
            $json->where('product.sku', 'SOL-100');
            $json->where('product.category', 'Solar');
            $json->has('product.suppliers', 1);
            $json->where('product.suppliers.0.internal_trade_price', 250);
            $json->where('product.suppliers.0.name', 'Victron Energy');
            $json->has('url');
            $json->etc();
        });
    }

    public function test_get_product_includes_trade_price_in_supplier_pivots(): void
    {
        $product = Product::factory()->create();
        $supplier = Supplier::factory()->create();
        $product->suppliers()->attach($supplier->id, [
            'trade_price' => 199.99,
            'is_preferred' => true,
        ]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(GetProductTool::class, ['id' => $product->id]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $json->has('product.suppliers', 1);
            $json->where('product.suppliers.0.internal_trade_price', 199.99);
            $json->etc();
        });
    }

    public function test_search_products_matches_name_sku_description(): void
    {
        Product::factory()->create(['name' => 'Lithium Battery', 'sku' => 'BAT-LI-100', 'description' => 'High capacity']);
        Product::factory()->create(['name' => 'Solar Panel', 'sku' => 'SOL-200']);

        $response = QvtServer::actingAs($this->admin)
            ->tool(SearchProductsTool::class, ['query' => 'lithium']);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $json->has('data', 1);
            $json->where('data.0.name', 'Lithium Battery');
            $json->etc();
        });
    }

    public function test_product_tools_are_read_only(): void
    {
        Product::factory()->count(3)->create();
        $countBefore = Product::count();

        QvtServer::actingAs($this->admin)
            ->tool(ListProductsTool::class, []);

        $this->assertEquals($countBefore, Product::count());
    }
}
