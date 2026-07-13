<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enquiry_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enquiry_id')->constrained()->onDelete('cascade');
            $table->foreignId('staff_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('direction')->default('outbound'); // outbound|inbound
            $table->string('subject')->nullable();
            $table->text('body');
            $table->string('to_email')->nullable();
            $table->string('from_email')->nullable();
            $table->string('from_name')->nullable();
            $table->string('status')->default('draft'); // draft|sent|failed|received
            $table->string('message_id')->nullable()->unique();
            $table->string('in_reply_to')->nullable();
            $table->string('postmark_message_id')->nullable();
            $table->json('ai_draft_data')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enquiry_replies');
    }
};
