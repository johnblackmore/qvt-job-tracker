<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number', 50)->unique();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->date('order_date');
            $table->date('invoice_date')->nullable();
            $table->string('invoice_number', 100)->nullable();
            $table->date('due_date')->nullable();
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('vat_total', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->char('currency', 3)->default('GBP');
            $table->string('status', 20)->default('draft');
            $table->string('payment_method', 30)->nullable();
            $table->string('payment_reference', 255)->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->foreignId('bank_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('supplier_id');
            $table->index('status');
            $table->index('invoice_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_orders');
    }
};
