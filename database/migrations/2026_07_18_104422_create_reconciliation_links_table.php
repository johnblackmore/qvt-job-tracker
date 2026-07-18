<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reconciliation_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_transaction_id')->unique()->constrained()->cascadeOnDelete();
            $table->morphs('reconcilable');
            $table->decimal('amount', 10, 2);
            $table->foreignId('matched_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('matched_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_links');
    }
};
