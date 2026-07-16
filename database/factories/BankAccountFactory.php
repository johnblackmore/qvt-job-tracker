<?php

namespace Database\Factories;

use App\Models\BankAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

class BankAccountFactory extends Factory
{
    protected $model = BankAccount::class;

    public function definition(): array
    {
        return [
            'provider' => 'monzo',
            'provider_account_id' => 'acc_'.fake()->unique()->lexify('???????????'),
            'name' => 'QVT Business Account',
            'type' => 'current',
            'currency' => 'GBP',
            'is_active' => true,
            'metadata' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
