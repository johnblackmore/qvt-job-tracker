<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('bank_transaction_id')
                ->nullable()
                ->after('recorded_by_user_id')
                ->constrained('bank_transactions')
                ->onDelete('set null');
        });

        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->foreignId('matched_payment_id')
                ->nullable()
                ->after('reconciliation_status')
                ->constrained('payments')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('matched_payment_id');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_transaction_id');
        });
    }
};
