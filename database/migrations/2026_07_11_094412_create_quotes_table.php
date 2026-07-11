<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->string('reference_number')->unique();
            $table->string('status')->default('draft');
            $table->decimal('total_retail', 10, 2)->default(0);
            $table->decimal('total_trade', 10, 2)->default(0);
            $table->decimal('labour_total', 10, 2)->default(0);
            $table->decimal('grand_total', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->date('valid_until')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->foreignId('converted_order_id')->nullable();
            $table->foreignId('staff_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};
