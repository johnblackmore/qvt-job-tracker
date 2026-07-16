<?php

namespace Tests\Feature\Mcp\Tools\Banking;

use App\Mcp\Servers\QvtServer;
use App\Mcp\Tools\Banking\ListUnmatchedTransactionsTool;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ListUnmatchedTransactionsToolTest extends TestCase
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

    public function test_lists_only_unmatched_transactions(): void
    {
        $account = BankAccount::factory()->create();
        BankTransaction::factory(3)->create(['bank_account_id' => $account->id]);
        BankTransaction::factory(2)->matched()->create(['bank_account_id' => $account->id]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(ListUnmatchedTransactionsTool::class, []);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $json->where('status', 'completed');
            $json->has('data');
            $json->has('summary');
            $json->etc();
        });
    }

    public function test_include_ignored_shows_ignored_transactions(): void
    {
        $account = BankAccount::factory()->create();
        BankTransaction::factory(2)->create(['bank_account_id' => $account->id]);
        BankTransaction::factory(1)->ignored()->create(['bank_account_id' => $account->id]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(ListUnmatchedTransactionsTool::class, [
                'include_ignored' => true,
            ]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $json->where('summary.count', 3);
            $json->etc();
        });
    }

    public function test_non_admin_cannot_access(): void
    {
        $installer = User::factory()->create();
        $installer->assignRole('installer');

        $response = QvtServer::actingAs($installer)
            ->tool(ListUnmatchedTransactionsTool::class, []);

        $response->assertHasErrors();
    }
}
