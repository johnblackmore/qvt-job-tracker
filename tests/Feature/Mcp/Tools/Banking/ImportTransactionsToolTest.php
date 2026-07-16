<?php

namespace Tests\Feature\Mcp\Tools\Banking;

use App\Mcp\Servers\QvtServer;
use App\Mcp\Tools\Banking\ImportTransactionsTool;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ImportTransactionsToolTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'admin', 'guard_name' => 'web']);
        Role::create(['name' => 'installer', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    public function test_preview_returns_preview_data_without_importing(): void
    {
        $account = BankAccount::factory()->create();

        $response = QvtServer::actingAs($this->admin)
            ->tool(ImportTransactionsTool::class, [
                'bank_account_id' => $account->id,
                'preview' => true,
                'confirmed' => false,
            ]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $json->where('status', 'preview');
            $json->has('data.accounts');
            $json->etc();
        });

        $this->assertEquals(0, BankTransaction::count());
    }

    public function test_preview_with_no_active_accounts_returns_error(): void
    {
        $response = QvtServer::actingAs($this->admin)
            ->tool(ImportTransactionsTool::class, [
                'preview' => true,
                'confirmed' => false,
            ]);

        $response->assertHasErrors();
    }

    public function test_confirmed_requires_both_preview_and_confirmed(): void
    {
        $response = QvtServer::actingAs($this->admin)
            ->tool(ImportTransactionsTool::class, [
                'preview' => false,
                'confirmed' => false,
            ]);

        $response->assertHasErrors();
    }

    public function test_non_admin_cannot_use_tool(): void
    {
        $installer = User::factory()->create();
        $installer->assignRole('installer');

        $response = QvtServer::actingAs($installer)
            ->tool(ImportTransactionsTool::class, [
                'preview' => true,
                'confirmed' => false,
            ]);

        $response->assertHasErrors();
    }
}
