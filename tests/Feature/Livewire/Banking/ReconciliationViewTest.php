<?php

namespace Tests\Feature\Livewire\Banking;

use App\Banking\Services\ReconciliationService;
use App\Livewire\Banking\ReconciliationView;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReconciliationViewTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'admin', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->actingAs($this->admin);
    }

    public function test_renders_reconciliation_page(): void
    {
        $component = Livewire::test(ReconciliationView::class);

        $component->assertStatus(200);
        $component->assertSee('Reconciliation');
    }

    public function test_shows_unmatched_transactions(): void
    {
        $account = BankAccount::factory()->create();
        BankTransaction::factory(3)->create(['bank_account_id' => $account->id]);

        $component = Livewire::test(ReconciliationView::class);

        $component->assertSee('Unmatched Bank');
    }

    public function test_run_auto_match_links_transactions(): void
    {
        $account = BankAccount::factory()->create();
        $customer = Customer::factory()->create();
        $order = Order::factory()->create(['customer_id' => $customer->id, 'status' => 'completed']);

        $txn = BankTransaction::factory()->create([
            'bank_account_id' => $account->id,
            'amount' => -500.00,
            'transaction_date' => now()->subDay(),
        ]);

        Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => 500.00,
            'paid_at' => now()->subDay(),
        ]);

        Livewire::test(ReconciliationView::class)
            ->call('runAutoMatch');

        $this->assertDatabaseHas('bank_transactions', [
            'id' => $txn->id,
            'reconciliation_status' => 'matched',
        ]);
    }

    public function test_can_select_and_link_transaction_to_payment(): void
    {
        $account = BankAccount::factory()->create();
        $customer = Customer::factory()->create();
        $order = Order::factory()->create(['customer_id' => $customer->id, 'status' => 'completed']);

        $txn = BankTransaction::factory()->create([
            'bank_account_id' => $account->id,
            'amount' => -500.00,
        ]);

        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => 500.00,
        ]);

        Livewire::test(ReconciliationView::class)
            ->call('selectTransaction', $txn->id)
            ->call('selectPayment', $payment->id)
            ->call('linkSelected');

        $this->assertDatabaseHas('bank_transactions', [
            'id' => $txn->id,
            'matched_payment_id' => $payment->id,
            'reconciliation_status' => 'matched',
        ]);
    }

    public function test_can_unlink_matched_transaction(): void
    {
        $account = BankAccount::factory()->create();
        $customer = Customer::factory()->create();
        $order = Order::factory()->create(['customer_id' => $customer->id, 'status' => 'completed']);

        $txn = BankTransaction::factory()->create(['bank_account_id' => $account->id, 'amount' => -500.00]);
        $payment = Payment::factory()->create(['order_id' => $order->id, 'amount' => 500.00]);

        $service = app(ReconciliationService::class);
        $service->manualMatch($txn, $payment);

        Livewire::test(ReconciliationView::class)
            ->call('unlink', $txn->id);

        $this->assertDatabaseHas('bank_transactions', [
            'id' => $txn->id,
            'matched_payment_id' => null,
            'reconciliation_status' => 'unmatched',
        ]);
    }
}
