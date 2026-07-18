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
        Schema::table('ai_model_configs', function (Blueprint $table) {
            $table->decimal('input_price', 10, 4)->nullable()->after('description');
            $table->decimal('output_price', 10, 4)->nullable()->after('input_price');
        });
    }

    public function down(): void
    {
        Schema::table('ai_model_configs', function (Blueprint $table) {
            $table->dropColumn(['input_price', 'output_price']);
        });
    }
};
