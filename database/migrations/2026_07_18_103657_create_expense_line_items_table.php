<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->constrained()->cascadeOnDelete();
            $table->text('description');
            $table->string('line_type', 20)->default('business');
            $table->decimal('amount', 10, 2)->default(0);
            $table->decimal('vat_rate', 5, 4)->default(0.2000);
            $table->decimal('vat_amount', 10, 2)->default(0);
            $table->string('line_type_category', 30)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('expense_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_line_items');
    }
};
