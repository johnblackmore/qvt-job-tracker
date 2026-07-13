<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('customers', fn (Blueprint $table) => $table->softDeletes());
        Schema::table('vehicles', fn (Blueprint $table) => $table->softDeletes());
        Schema::table('enquiries', fn (Blueprint $table) => $table->softDeletes());
        Schema::table('quotes', fn (Blueprint $table) => $table->softDeletes());
        Schema::table('quote_line_items', fn (Blueprint $table) => $table->softDeletes());
        Schema::table('products', fn (Blueprint $table) => $table->softDeletes());
        Schema::table('suppliers', fn (Blueprint $table) => $table->softDeletes());
        Schema::table('product_categories', fn (Blueprint $table) => $table->softDeletes());
        Schema::table('orders', fn (Blueprint $table) => $table->softDeletes());
        Schema::table('sample_quotes', fn (Blueprint $table) => $table->softDeletes());
        Schema::table('email_templates', fn (Blueprint $table) => $table->softDeletes());
        Schema::table('ai_conversations', fn (Blueprint $table) => $table->softDeletes());
        Schema::table('ai_messages', fn (Blueprint $table) => $table->softDeletes());
    }

    public function down(): void
    {
        Schema::table('customers', fn (Blueprint $table) => $table->dropSoftDeletes());
        Schema::table('vehicles', fn (Blueprint $table) => $table->dropSoftDeletes());
        Schema::table('enquiries', fn (Blueprint $table) => $table->dropSoftDeletes());
        Schema::table('quotes', fn (Blueprint $table) => $table->dropSoftDeletes());
        Schema::table('quote_line_items', fn (Blueprint $table) => $table->dropSoftDeletes());
        Schema::table('products', fn (Blueprint $table) => $table->dropSoftDeletes());
        Schema::table('suppliers', fn (Blueprint $table) => $table->dropSoftDeletes());
        Schema::table('product_categories', fn (Blueprint $table) => $table->dropSoftDeletes());
        Schema::table('orders', fn (Blueprint $table) => $table->dropSoftDeletes());
        Schema::table('sample_quotes', fn (Blueprint $table) => $table->dropSoftDeletes());
        Schema::table('email_templates', fn (Blueprint $table) => $table->dropSoftDeletes());
        Schema::table('ai_conversations', fn (Blueprint $table) => $table->dropSoftDeletes());
        Schema::table('ai_messages', fn (Blueprint $table) => $table->dropSoftDeletes());
    }
};
