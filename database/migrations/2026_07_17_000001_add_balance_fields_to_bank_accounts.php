<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->integer('balance_pence')->nullable()->after('is_active');
            $table->timestamp('balance_fetched_at')->nullable()->after('balance_pence');
        });
    }

    public function down(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->dropColumn(['balance_pence', 'balance_fetched_at']);
        });
    }
};
