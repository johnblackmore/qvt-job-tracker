<?php

namespace Tests\Feature\Mcp;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class QvtServerAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'admin', 'guard_name' => 'web']);
        Role::create(['name' => 'installer', 'guard_name' => 'web']);
    }

    public function test_unauthenticated_requests_receive_401(): void
    {
        $response = $this->postJson('/mcp/qvt', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
        ]);

        $response->assertStatus(401);
        $response->assertJsonPath('error.message', 'Unauthorized. Provide a valid Sanctum token in the Authorization header.');
    }

    public function test_non_admin_authenticated_requests_receive_403(): void
    {
        $installer = User::factory()->create();
        $installer->assignRole('installer');

        $token = $installer->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/mcp/qvt', [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'tools/list',
            ]);

        $response->assertStatus(403);
    }

    public function test_admin_authenticated_requests_can_access_server(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/mcp/qvt', [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'tools/list',
                'params' => ['per_page' => 50],
            ]);

        $response->assertOk();
        $tools = $response->json('result.tools');
        $this->assertNotEmpty($tools);
        $this->assertCount(37, $tools);
    }

    public function test_delete_customer_tool_has_destructive_annotation(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/mcp/qvt', [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'tools/list',
                'params' => ['per_page' => 50],
            ]);

        $response->assertOk();
        $tools = $response->json('result.tools');
        $deleteTool = collect($tools)->first(fn (array $tool) => $tool['name'] === 'delete-customer-tool');
        $this->assertNotNull($deleteTool);
        $this->assertTrue($deleteTool['annotations']['destructiveHint'] ?? false);
    }

    public function test_admin_can_list_resources(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/mcp/qvt', [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'resources/templates/list',
            ]);

        $response->assertOk();
        $resources = $response->json('result.resourceTemplates');
        $this->assertNotEmpty($resources);
        $this->assertCount(3, $resources);
    }

    public function test_admin_can_list_prompts(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/mcp/qvt', [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'prompts/list',
            ]);

        $response->assertOk();
        $prompts = $response->json('result.prompts');
        $this->assertNotEmpty($prompts);
        $this->assertCount(2, $prompts);
    }
}
