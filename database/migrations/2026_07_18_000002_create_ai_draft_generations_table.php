<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_draft_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('enquiry_id')->constrained()->cascadeOnDelete();
            $table->string('assistant_name')->default('enquiry-draft-assistant');
            $table->string('tone', 50)->default('professional');
            $table->string('trigger_source', 50)->nullable();
            $table->string('provider', 50)->nullable();
            $table->string('model', 100)->nullable();
            $table->text('prompt_data')->nullable();
            $table->longText('raw_response')->nullable();
            $table->text('summary')->nullable();
            $table->text('draft_subject')->nullable();
            $table->longText('draft_body')->nullable();
            $table->string('confidence', 50)->nullable();
            $table->json('suggested_next_steps')->nullable();
            $table->json('knowledge_gaps')->nullable();
            $table->string('status');
            $table->text('error_message')->nullable();
            $table->integer('input_tokens')->nullable();
            $table->integer('output_tokens')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_draft_generations');
    }
};
