<?php

namespace Tests\Feature\Mcp\Tools;

use App\Mcp\Servers\QvtServer;
use App\Mcp\Tools\ListEnquiriesTool;
use App\Models\Enquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EnquiryReadToolTest extends TestCase
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

    public function test_list_enquiries_returns_paginated_results(): void
    {
        Enquiry::factory()->count(5)->create();

        $response = QvtServer::actingAs($this->admin)
            ->tool(ListEnquiriesTool::class, ['per_page' => 3]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $json->where('status', 'completed');
            $json->has('data', 3);
            $json->where('pagination.per_page', 3);
            $json->where('pagination.total', 5);
            $json->etc();
        });
    }

    public function test_list_enquiries_includes_url_per_item(): void
    {
        $enquiry = Enquiry::factory()->create();

        $response = QvtServer::actingAs($this->admin)
            ->tool(ListEnquiriesTool::class, []);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) use ($enquiry) {
            $json->has('data.0.url')
                ->where('data.0.url', fn (string $url) => str_contains($url, "/enquiries/{$enquiry->id}"));
            $json->etc();
        });
    }

    public function test_list_enquiries_filters_by_status(): void
    {
        Enquiry::factory()->create(['status' => 'new']);
        Enquiry::factory()->create(['status' => 'responded']);

        $response = QvtServer::actingAs($this->admin)
            ->tool(ListEnquiriesTool::class, ['status' => 'responded']);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $json->has('data', 1);
            $json->where('data.0.status', 'responded');
            $json->etc();
        });
    }

    public function test_list_enquiries_filters_by_source(): void
    {
        Enquiry::factory()->create(['source' => 'web']);
        Enquiry::factory()->create(['source' => 'phone']);

        $response = QvtServer::actingAs($this->admin)
            ->tool(ListEnquiriesTool::class, ['source' => 'phone']);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $json->has('data', 1);
            $json->where('data.0.source', 'phone');
            $json->etc();
        });
    }

    public function test_list_enquiries_filters_by_since(): void
    {
        Enquiry::factory()->create(['created_at' => now()->subDays(10)]);
        Enquiry::factory()->create(['created_at' => now()->subDays(2)]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(ListEnquiriesTool::class, ['since' => now()->subDays(5)->format('Y-m-d')]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $json->has('data', 1);
            $json->etc();
        });
    }

    public function test_enquiry_tools_are_read_only(): void
    {
        Enquiry::factory()->count(3)->create();
        $countBefore = Enquiry::count();

        QvtServer::actingAs($this->admin)
            ->tool(ListEnquiriesTool::class, []);

        $this->assertEquals($countBefore, Enquiry::count());
    }
}
