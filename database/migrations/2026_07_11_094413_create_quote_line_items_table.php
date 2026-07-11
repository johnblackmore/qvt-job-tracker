<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->onDelete('cascade');
            $table->string('line_type')->default('product');
            $table->foreignId('product_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('product_supplier_id')->nullable()->constrained('product_supplier')->onDelete('set null');
            $table->string('description');
            $table->integer('quantity')->default(1);
            $table->decimal('unit_retail_price', 10, 2)->default(0);
            $table->decimal('unit_trade_price', 10, 2)->default(0);
            $table->decimal('line_total_retail', 10, 2)->default(0);
            $table->decimal('line_total_trade', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_line_items');
    }
};
