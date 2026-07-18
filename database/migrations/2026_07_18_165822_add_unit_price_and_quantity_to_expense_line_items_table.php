<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expense_line_items', function (Blueprint $table) {
            $table->decimal('unit_price', 12, 4)->nullable()->after('amount');
            $table->decimal('quantity', 10, 2)->nullable()->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('expense_line_items', function (Blueprint $table) {
            $table->dropColumn(['unit_price', 'quantity']);
        });
    }
};
