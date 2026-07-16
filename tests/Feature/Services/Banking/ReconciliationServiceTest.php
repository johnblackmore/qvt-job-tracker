<?php

namespace Tests\Feature\Services\Banking;

use App\Banking\Services\ReconciliationService;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReconciliationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ReconciliationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ReconciliationService::class);
    }

    public function test_auto_match_links_exact_match(): void
    {
        $account = BankAccount::factory()->create();
        $customer = Customer::factory()->create();
        $order = Order::factory()->create(['customer_id' => $customer->id, 'status' => 'completed']);

        $txn = BankTransaction::factory()->create([
            'bank_account_id' => $account->id,
            'amount' => -500.00,
            'transaction_date' => now()->subDay(),
        ]);

        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => 500.00,
            'paid_at' => now()->subDay(),
        ]);

        $result = $this->service->autoMatch();

        $this->assertEquals(1, $result['matched']);
        $this->assertEquals(0, $result['ambiguous']);

        $this->assertDatabaseHas('bank_transactions', [
            'id' => $txn->id,
            'matched_payment_id' => $payment->id,
            'reconciliation_status' => 'matched',
        ]);
    }

    public function test_auto_match_ignores_non_matching_amounts(): void
    {
        $account = BankAccount::factory()->create();
        $customer = Customer::factory()->create();
        $order = Order::factory()->create(['customer_id' => $customer->id, 'status' => 'completed']);

        BankTransaction::factory()->create([
            'bank_account_id' => $account->id,
            'amount' => -500.00,
        ]);

        Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => 123.45,
        ]);

        $result = $this->service->autoMatch();

        $this->assertEquals(0, $result['matched']);
    }

    public function test_auto_match_ignores_payments_outside_3_day_window(): void
    {
        $account = BankAccount::factory()->create();
        $customer = Customer::factory()->create();
        $order = Order::factory()->create(['customer_id' => $customer->id, 'status' => 'completed']);

        BankTransaction::factory()->create([
            'bank_account_id' => $account->id,
            'amount' => -500.00,
            'transaction_date' => now()->subDays(30),
        ]);

        Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => 500.00,
            'paid_at' => now()->subDays(10),
        ]);

        $result = $this->service->autoMatch();

        $this->assertEquals(0, $result['matched']);
    }

    public function test_auto_match_handles_minor_amount_tolerance(): void
    {
        $account = BankAccount::factory()->create();
        $customer = Customer::factory()->create();
        $order = Order::factory()->create(['customer_id' => $customer->id, 'status' => 'completed']);

        $txn = BankTransaction::factory()->create([
            'bank_account_id' => $account->id,
            'amount' => -500.01,
            'transaction_date' => now()->subDay(),
        ]);

        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => 500.00,
            'paid_at' => now()->subDay(),
        ]);

        $result = $this->service->autoMatch();

        $this->assertEquals(1, $result['matched']);
    }

    public function test_manual_match_links_and_unlinks_previous(): void
    {
        $account = BankAccount::factory()->create();
        $customer = Customer::factory()->create();
        $order = Order::factory()->create(['customer_id' => $customer->id, 'status' => 'completed']);

        $txn1 = BankTransaction::factory()->create(['bank_account_id' => $account->id, 'amount' => -500.00]);
        $txn2 = BankTransaction::factory()->create(['bank_account_id' => $account->id, 'amount' => -500.00]);

        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => 500.00,
        ]);

        $this->service->manualMatch($txn1, $payment);
        $this->service->manualMatch($txn2, $payment);

        $this->assertDatabaseHas('bank_transactions', [
            'id' => $txn1->id,
            'matched_payment_id' => null,
            'reconciliation_status' => 'unmatched',
        ]);

        $this->assertDatabaseHas('bank_transactions', [
            'id' => $txn2->id,
            'matched_payment_id' => $payment->id,
            'reconciliation_status' => 'matched',
        ]);
    }

    public function test_unlink_breaks_connection(): void
    {
        $account = BankAccount::factory()->create();
        $customer = Customer::factory()->create();
        $order = Order::factory()->create(['customer_id' => $customer->id, 'status' => 'completed']);

        $txn = BankTransaction::factory()->create(['bank_account_id' => $account->id, 'amount' => -500.00]);
        $payment = Payment::factory()->create(['order_id' => $order->id, 'amount' => 500.00]);

        $this->service->manualMatch($txn, $payment);
        $this->service->unlinkTransaction($txn->fresh());

        $this->assertDatabaseHas('bank_transactions', [
            'id' => $txn->id,
            'matched_payment_id' => null,
            'reconciliation_status' => 'unmatched',
        ]);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'bank_transaction_id' => null,
        ]);
    }

    public function test_get_summary_returns_correct_counts(): void
    {
        $account = BankAccount::factory()->create();
        BankTransaction::factory(5)->create(['bank_account_id' => $account->id]);
        BankTransaction::factory(3)->matched()->create(['bank_account_id' => $account->id]);
        BankTransaction::factory(1)->ignored()->create(['bank_account_id' => $account->id]);

        $summary = $this->service->getSummary();

        $this->assertEquals(9, $summary['total_transactions']);
        $this->assertEquals(3, $summary['matched_transactions']);
        $this->assertEquals(5, $summary['unmatched_transactions']);
        $this->assertEquals(1, $summary['ignored_transactions']);
        $this->assertEquals(33.3, $summary['match_rate']);
    }
}
