<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number', 50)->unique();
            $table->foreignId('expense_category_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description');
            $table->string('merchant_name', 255)->nullable();
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->decimal('vat_total', 10, 2)->default(0);
            $table->date('expense_date');
            $table->string('payment_method', 30)->nullable();
            $table->string('payment_reference', 255)->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->string('status', 20)->default('draft');
            $table->foreignId('bank_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('expense_category_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
