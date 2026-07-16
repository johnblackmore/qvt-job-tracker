<?php

namespace Database\Factories;

use App\Models\BankTransaction;
use App\Models\Receipt;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReceiptFactory extends Factory
{
    protected $model = Receipt::class;

    public function definition(): array
    {
        return [
            'bank_transaction_id' => BankTransaction::factory(),
            'file_path' => 'receipts/test/'.fake()->uuid().'.jpg',
            'original_filename' => fake()->word().'.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => fake()->numberBetween(50000, 5000000),
            'notes' => null,
            'monzo_attachment_id' => null,
            'sync_status' => 'pending',
        ];
    }

    public function synced(): static
    {
        return $this->state(fn (array $attributes) => [
            'monzo_attachment_id' => 'attach_'.fake()->lexify('???????????'),
            'sync_status' => 'synced',
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'sync_status' => 'failed',
        ]);
    }
}
