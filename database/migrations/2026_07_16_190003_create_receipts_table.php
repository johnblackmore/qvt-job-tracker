<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_transaction_id')->nullable()->constrained()->onDelete('set null');
            $table->string('file_path');
            $table->string('original_filename');
            $table->string('mime_type', 100)->nullable();
            $table->integer('file_size')->nullable();
            $table->text('notes')->nullable();
            $table->string('monzo_attachment_id')->nullable();
            $table->string('sync_status', 20)->default('pending');
            $table->timestamps();

            $table->index('bank_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
