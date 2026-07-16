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
        Schema::table('quote_line_items', function (Blueprint $table) {
            $table->decimal('vat_rate', 5, 4)->default(0.2000)->after('unit_trade_price');
            $table->decimal('unit_cost_price', 10, 2)->default(0)->after('vat_rate');
            $table->decimal('line_total_cost', 10, 2)->default(0)->after('line_total_trade');
        });
    }

    public function down(): void
    {
        Schema::table('quote_line_items', function (Blueprint $table) {
            $table->dropColumn(['vat_rate', 'unit_cost_price', 'line_total_cost']);
        });
    }
};
