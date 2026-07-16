<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emails_sent', function (Blueprint $table) {
            $table->json('metadata')->nullable()->after('error_message');
            $table->timestamp('opened_at')->nullable()->after('metadata');
            $table->timestamp('clicked_at')->nullable()->after('opened_at');
            $table->timestamp('bounced_at')->nullable()->after('clicked_at');
            $table->string('bounce_type')->nullable()->after('bounced_at');
            $table->timestamp('spam_complaint_at')->nullable()->after('bounce_type');
            $table->timestamp('delivered_at')->nullable()->after('spam_complaint_at');
        });
    }

    public function down(): void
    {
        Schema::table('emails_sent', function (Blueprint $table) {
            $table->dropColumn([
                'metadata', 'opened_at', 'clicked_at',
                'bounced_at', 'bounce_type', 'spam_complaint_at', 'delivered_at',
            ]);
        });
    }
};
