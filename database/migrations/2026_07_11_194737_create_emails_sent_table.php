<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emails_sent', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('quote_id')->nullable()->constrained()->onDelete('set null');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->foreignId('template_id')->nullable()->constrained('email_templates')->onDelete('set null');
            $table->string('to_email');
            $table->string('subject');
            $table->longText('body_html');
            $table->string('postmark_message_id')->nullable();
            $table->string('status')->default('pending'); // pending, sent, failed
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emails_sent');
    }
};
