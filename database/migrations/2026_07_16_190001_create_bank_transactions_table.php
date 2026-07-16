<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->constrained()->onDelete('cascade');
            $table->string('provider_transaction_id');
            $table->decimal('amount', 10, 2);
            $table->char('currency', 3)->default('GBP');
            $table->string('description', 500);
            $table->string('merchant_name')->nullable();
            $table->string('merchant_category', 100)->nullable();
            $table->dateTime('transaction_date');
            $table->dateTime('settled_date')->nullable();
            $table->boolean('is_pending')->default(false);
            $table->boolean('is_load')->default(false);
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->string('expense_category', 50)->nullable();
            $table->string('reconciliation_status', 20)->default('unmatched');
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->unique('provider_transaction_id');
            $table->index(['bank_account_id', 'transaction_date']);
            $table->index('reconciliation_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
    }
};
