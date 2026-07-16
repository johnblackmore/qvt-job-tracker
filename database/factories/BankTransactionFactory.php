<?php

namespace Database\Factories;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

class BankTransactionFactory extends Factory
{
    protected $model = BankTransaction::class;

    public function definition(): array
    {
        return [
            'bank_account_id' => BankAccount::factory(),
            'provider_transaction_id' => 'tx_'.fake()->unique()->lexify('???????????'),
            'amount' => fake()->randomFloat(2, -500, -5),
            'currency' => 'GBP',
            'description' => fake()->company(),
            'merchant_name' => fake()->company(),
            'merchant_category' => fake()->randomElement(['eating_out', 'transport', 'shopping', 'bills', 'general']),
            'transaction_date' => fake()->dateTimeBetween('-30 days', 'now'),
            'settled_date' => fn (array $attrs) => $attrs['transaction_date'],
            'is_pending' => false,
            'is_load' => false,
            'notes' => null,
            'metadata' => null,
            'expense_category' => null,
            'reconciliation_status' => 'unmatched',
            'imported_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_pending' => true,
            'settled_date' => null,
        ]);
    }

    public function load(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_load' => true,
            'amount' => fake()->randomFloat(2, 50, 2000),
        ]);
    }

    public function credit(): static
    {
        return $this->state(fn (array $attributes) => [
            'amount' => fake()->randomFloat(2, 50, 2000),
        ]);
    }

    public function matched(): static
    {
        return $this->state(fn (array $attributes) => [
            'reconciliation_status' => 'matched',
        ]);
    }

    public function ignored(): static
    {
        return $this->state(fn (array $attributes) => [
            'reconciliation_status' => 'ignored',
        ]);
    }

    public function withCategory(?string $category = 'stock'): static
    {
        return $this->state(fn (array $attributes) => [
            'expense_category' => $category,
        ]);
    }
}
