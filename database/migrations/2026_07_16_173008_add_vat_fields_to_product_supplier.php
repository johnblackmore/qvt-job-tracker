<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('product_supplier', function (Blueprint $table) {
            $table->boolean('trade_price_includes_vat')->default(false)->after('trade_price');
            $table->string('vat_rate_type', 20)->default('standard')->after('trade_price_includes_vat');
        });
    }

    public function down(): void
    {
        Schema::table('product_supplier', function (Blueprint $table) {
            $table->dropColumn(['trade_price_includes_vat', 'vat_rate_type']);
        });
    }
};
