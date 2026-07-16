<?php

namespace Tests\Feature\Mcp\Tools\Banking;

use App\Mcp\Servers\QvtServer;
use App\Mcp\Tools\Banking\ReconcilePaymentTool;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReconcilePaymentToolTest extends TestCase
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

    public function test_preview_returns_preview_data_without_linking(): void
    {
        $account = BankAccount::factory()->create();
        $txn = BankTransaction::factory()->create(['bank_account_id' => $account->id]);
        $customer = Customer::factory()->create();
        $order = Order::factory()->create(['customer_id' => $customer->id]);
        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => abs($txn->amount),
        ]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(ReconcilePaymentTool::class, [
                'transaction_id' => $txn->id,
                'payment_id' => $payment->id,
                'preview' => true,
                'confirmed' => false,
            ]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $json->where('status', 'preview');
            $json->has('data');
            $json->etc();
        });

        $this->assertNull($txn->fresh()->matched_payment_id);
    }

    public function test_confirmed_links_transaction_to_payment(): void
    {
        $account = BankAccount::factory()->create();
        $txn = BankTransaction::factory()->create(['bank_account_id' => $account->id]);
        $customer = Customer::factory()->create();
        $order = Order::factory()->create(['customer_id' => $customer->id]);
        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => abs($txn->amount),
        ]);

        $response = QvtServer::actingAs($this->admin)
            ->tool(ReconcilePaymentTool::class, [
                'transaction_id' => $txn->id,
                'payment_id' => $payment->id,
                'preview' => false,
                'confirmed' => true,
            ]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $json->where('status', 'completed');
            $json->has('data.order_id');
            $json->etc();
        });

        $this->assertDatabaseHas('bank_transactions', [
            'id' => $txn->id,
            'matched_payment_id' => $payment->id,
            'reconciliation_status' => 'matched',
        ]);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'bank_transaction_id' => $txn->id,
        ]);
    }

    public function test_requires_both_preview_and_confirmed(): void
    {
        $response = QvtServer::actingAs($this->admin)
            ->tool(ReconcilePaymentTool::class, [
                'transaction_id' => 1,
                'payment_id' => 1,
                'preview' => false,
                'confirmed' => false,
            ]);

        $response->assertHasErrors();
    }

    public function test_non_admin_cannot_use(): void
    {
        $installer = User::factory()->create();
        $installer->assignRole('installer');

        $response = QvtServer::actingAs($installer)
            ->tool(ReconcilePaymentTool::class, [
                'transaction_id' => 1,
                'payment_id' => 1,
                'preview' => true,
                'confirmed' => false,
            ]);

        $response->assertHasErrors();
    }
}
