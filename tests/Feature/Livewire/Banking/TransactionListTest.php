<?php

namespace Tests\Feature\Livewire\Banking;

use App\Livewire\Banking\TransactionList;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TransactionListTest extends TestCase
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

    public function test_renders_transactions_page(): void
    {
        $account = BankAccount::factory()->create();
        BankTransaction::factory(3)->create(['bank_account_id' => $account->id]);

        $component = Livewire::test(TransactionList::class);

        $component->assertStatus(200);
        $component->assertSee('Bank Transactions');
    }

    public function test_shows_empty_state_when_no_transactions(): void
    {
        $component = Livewire::test(TransactionList::class);

        $component->assertSee('No transactions found');
    }

    public function test_filters_by_expense_category(): void
    {
        $account = BankAccount::factory()->create();
        BankTransaction::factory(2)->withCategory('stock')->create(['bank_account_id' => $account->id]);
        BankTransaction::factory(3)->withCategory('fuel')->create(['bank_account_id' => $account->id]);

        $component = Livewire::test(TransactionList::class);

        $component->set('expenseCategory', 'fuel');
        $component->assertSee('fuel');
    }

    public function test_clears_filters_when_clear_is_called(): void
    {
        $account = BankAccount::factory()->create();
        BankTransaction::factory(2)->create(['bank_account_id' => $account->id]);

        $component = Livewire::test(TransactionList::class);

        $component->set('expenseCategory', 'stock');
        $component->set('search', 'test');
        $component->call('clearFilters');

        $component->assertSet('expenseCategory', '');
        $component->assertSet('search', '');
    }
}
