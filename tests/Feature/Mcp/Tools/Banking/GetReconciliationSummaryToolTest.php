<?php

namespace Tests\Feature\Mcp\Tools\Banking;

use App\Mcp\Servers\QvtServer;
use App\Mcp\Tools\Banking\GetReconciliationSummaryTool;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GetReconciliationSummaryToolTest extends TestCase
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

    public function test_returns_summary_with_counts(): void
    {
        $account = BankAccount::factory()->create();
        BankTransaction::factory(5)->create(['bank_account_id' => $account->id]);
        BankTransaction::factory(3)->matched()->create(['bank_account_id' => $account->id]);
        BankTransaction::factory(1)->ignored()->create(['bank_account_id' => $account->id]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(GetReconciliationSummaryTool::class, []);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $json->where('status', 'completed');
            $json->has('summary');
            $json->where('summary.total_transactions', 9);
            $json->where('summary.matched_transactions', 3);
            $json->where('summary.unmatched_transactions', 5);
            $json->where('summary.ignored_transactions', 1);
            $json->etc();
        });
    }

    public function test_non_admin_cannot_access(): void
    {
        $installer = User::factory()->create();
        $installer->assignRole('installer');

        $response = QvtServer::actingAs($installer)
            ->tool(GetReconciliationSummaryTool::class, []);

        $response->assertHasErrors();
    }
}
