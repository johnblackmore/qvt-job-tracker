<?php

namespace Tests\Feature\Mcp\Tools\Banking;

use App\Mcp\Servers\QvtServer;
use App\Mcp\Tools\Banking\ListTransactionsTool;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ListTransactionsToolTest extends TestCase
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

    public function test_lists_transactions_paginated(): void
    {
        $account = BankAccount::factory()->create();
        BankTransaction::factory(5)->create(['bank_account_id' => $account->id]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(ListTransactionsTool::class, [
                'per_page' => 20,
                'page' => 1,
            ]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $json->where('status', 'completed');
            $json->has('data');
            $json->has('pagination');
            $json->etc();
        });
    }

    public function test_filters_by_expense_category(): void
    {
        $account = BankAccount::factory()->create();
        BankTransaction::factory(3)->withCategory('stock')->create(['bank_account_id' => $account->id]);
        BankTransaction::factory(2)->withCategory('fuel')->create(['bank_account_id' => $account->id]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(ListTransactionsTool::class, [
                'expense_category' => 'fuel',
            ]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $json->where('status', 'completed');
            $json->has('data', 2);
            $json->etc();
        });
    }

    public function test_filters_by_reconciliation_status(): void
    {
        $account = BankAccount::factory()->create();
        BankTransaction::factory(3)->matched()->create(['bank_account_id' => $account->id]);
        BankTransaction::factory(2)->create(['bank_account_id' => $account->id]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(ListTransactionsTool::class, [
                'reconciliation_status' => 'matched',
            ]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $json->where('status', 'completed');
            $json->has('data', 3);
            $json->etc();
        });
    }

    public function test_non_admin_cannot_access(): void
    {
        $installer = User::factory()->create();
        $installer->assignRole('installer');

        $response = QvtServer::actingAs($installer)
            ->tool(ListTransactionsTool::class, []);

        $response->assertHasErrors();
    }
}
