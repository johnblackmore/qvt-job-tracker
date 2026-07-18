<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_documents', function (Blueprint $table) {
            $table->id();
            $table->morphs('documentable');
            $table->string('file_path', 500);
            $table->string('original_filename', 255);
            $table->string('mime_type', 100)->nullable();
            $table->integer('file_size')->nullable();
            $table->string('document_type', 30)->default('invoice');
            $table->foreignId('ai_extraction_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_documents');
    }
};
