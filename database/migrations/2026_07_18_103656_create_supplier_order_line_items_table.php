<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_order_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_order_id')->constrained()->cascadeOnDelete();
            $table->string('line_type', 20)->default('product');
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_supplier_id')->nullable()->constrained('product_supplier')->nullOnDelete();
            $table->string('supplier_sku', 100)->nullable();
            $table->text('description');
            $table->decimal('quantity', 10, 3)->default(1);
            $table->decimal('unit_amount', 10, 4)->default(0);
            $table->decimal('vat_rate', 5, 4)->default(0.2000);
            $table->decimal('vat_amount', 10, 2)->default(0);
            $table->decimal('line_total', 10, 2)->default(0);
            $table->string('line_type_category', 30)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('supplier_order_id');
            $table->index('product_id');
            $table->index('line_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_order_line_items');
    }
};
