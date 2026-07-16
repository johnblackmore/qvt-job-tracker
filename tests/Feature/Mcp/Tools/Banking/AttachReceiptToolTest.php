<?php

namespace Tests\Feature\Mcp\Tools\Banking;

use App\Mcp\Servers\QvtServer;
use App\Mcp\Tools\Banking\AttachReceiptTool;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AttachReceiptToolTest extends TestCase
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

    public function test_preview_returns_preview_data(): void
    {
        $account = BankAccount::factory()->create();
        $txn = BankTransaction::factory()->create(['bank_account_id' => $account->id]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(AttachReceiptTool::class, [
                'transaction_id' => $txn->id,
                'notes' => 'Fuel receipt',
                'preview' => true,
                'confirmed' => false,
            ]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $json->where('status', 'preview');
            $json->has('data.upload_url');
            $json->etc();
        });

        $this->assertEquals(0, Receipt::count());
    }

    public function test_confirmed_creates_receipt_placeholder(): void
    {
        $account = BankAccount::factory()->create();
        $txn = BankTransaction::factory()->create(['bank_account_id' => $account->id]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(AttachReceiptTool::class, [
                'transaction_id' => $txn->id,
                'notes' => 'Fuel receipt',
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $json->where('status', 'completed');
            $json->has('data.receipt_id');
            $json->etc();
        });

        $this->assertDatabaseHas('receipts', [
            'bank_transaction_id' => $txn->id,
            'notes' => 'Fuel receipt',
        ]);
    }

    public function test_requires_transaction_id(): void
    {
        $response = QvtServer::actingAs($this->admin)
            ->tool(AttachReceiptTool::class, [
                'preview' => true,
                'confirmed' => false,
            ]);

        $response->assertHasErrors();
    }

    public function test_non_admin_cannot_use(): void
    {
        $installer = User::factory()->create();
        $installer->assignRole('installer');

        $response = QvtServer::actingAs($installer)
            ->tool(AttachReceiptTool::class, [
                'transaction_id' => 1,
                'preview' => true,
                'confirmed' => false,
            ]);

        $response->assertHasErrors();
    }
}
