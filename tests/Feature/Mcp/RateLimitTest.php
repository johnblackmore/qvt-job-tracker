<?php

namespace Tests\Feature\Mcp;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_requests_exceed_rate_limit_after_60_per_minute(): void
    {
        Role::create(['name' => 'admin', 'guard_name' => 'web']);

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        RateLimiter::clear('mcp:'.$admin->id);

        $token = $admin->createToken('test-token')->plainTextToken;

        for ($i = 1; $i <= 60; $i++) {
            $this->withHeader('Authorization', 'Bearer '.$token)
                ->postJson('/mcp/qvt', [
                    'jsonrpc' => '2.0',
                    'id' => $i,
                    'method' => 'tools/list',
                    'params' => ['per_page' => 50],
                ])->assertOk();
        }

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/mcp/qvt', [
                'jsonrpc' => '2.0',
                'id' => 61,
                'method' => 'tools/list',
                'params' => ['per_page' => 50],
            ])->assertStatus(429);
    }

    public function test_rate_limit_is_per_user(): void
    {
        Role::create(['name' => 'admin', 'guard_name' => 'web']);

        $adminA = User::factory()->create();
        $adminA->assignRole('admin');
        RateLimiter::clear('mcp:'.$adminA->id);
        $tokenA = $adminA->createToken('test-token-A')->plainTextToken;

        $adminB = User::factory()->create();
        $adminB->assignRole('admin');
        RateLimiter::clear('mcp:'.$adminB->id);
        $tokenB = $adminB->createToken('test-token-B')->plainTextToken;

        for ($i = 1; $i <= 60; $i++) {
            $this->withHeader('Authorization', 'Bearer '.$tokenA)
                ->postJson('/mcp/qvt', [
                    'jsonrpc' => '2.0',
                    'id' => $i,
                    'method' => 'tools/list',
                    'params' => ['per_page' => 50],
                ])->assertOk();
        }

        $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->postJson('/mcp/qvt', [
                'jsonrpc' => '2.0',
                'id' => 61,
                'method' => 'tools/list',
                'params' => ['per_page' => 50],
            ])->assertStatus(429);

        $this->withHeader('Authorization', 'Bearer '.$tokenB)
            ->postJson('/mcp/qvt', [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'tools/list',
                'params' => ['per_page' => 50],
            ])->assertOk();
    }
}
