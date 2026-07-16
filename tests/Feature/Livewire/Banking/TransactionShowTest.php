<?php

namespace Tests\Feature\Livewire\Banking;

use App\Livewire\Banking\TransactionShow;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TransactionShowTest extends TestCase
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

    public function test_shows_transaction_details(): void
    {
        $account = BankAccount::factory()->create();
        $txn = BankTransaction::factory()->create(['bank_account_id' => $account->id]);

        $component = Livewire::test(TransactionShow::class, ['transaction' => $txn]);

        $component->assertStatus(200);
        $component->assertSee($txn->description);
    }

    public function test_can_update_expense_category(): void
    {
        $account = BankAccount::factory()->create();
        $txn = BankTransaction::factory()->create(['bank_account_id' => $account->id]);

        $component = Livewire::test(TransactionShow::class, ['transaction' => $txn]);

        $component->set('expenseCategory', 'fuel');
        $component->call('saveCategory');

        $this->assertDatabaseHas('bank_transactions', [
            'id' => $txn->id,
            'expense_category' => 'fuel',
        ]);
    }

    public function test_can_update_notes(): void
    {
        $account = BankAccount::factory()->create();
        $txn = BankTransaction::factory()->create(['bank_account_id' => $account->id]);

        $component = Livewire::test(TransactionShow::class, ['transaction' => $txn]);

        $component->set('notes', 'Test note for this transaction');
        $component->call('saveNotes');

        $this->assertDatabaseHas('bank_transactions', [
            'id' => $txn->id,
            'notes' => 'Test note for this transaction',
        ]);
    }

    public function test_can_toggle_ignored_status(): void
    {
        $account = BankAccount::factory()->create();
        $txn = BankTransaction::factory()->create(['bank_account_id' => $account->id]);

        $component = Livewire::test(TransactionShow::class, ['transaction' => $txn]);

        $component->call('toggleIgnored');

        $this->assertDatabaseHas('bank_transactions', [
            'id' => $txn->id,
            'reconciliation_status' => 'ignored',
        ]);

        $component->call('toggleIgnored');

        $this->assertDatabaseHas('bank_transactions', [
            'id' => $txn->id,
            'reconciliation_status' => 'unmatched',
        ]);
    }
}
