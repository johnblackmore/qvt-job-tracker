<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('processed_netlify_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('submission_id');
            $table->string('site_id');
            $table->string('form_id')->nullable();
            $table->json('submission_data');
            $table->foreignId('customer_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('enquiry_id')->nullable()->constrained()->onDelete('set null');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['submission_id', 'site_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processed_netlify_submissions');
    }
};
