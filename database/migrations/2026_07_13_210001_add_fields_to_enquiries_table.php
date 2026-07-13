<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('enquiries', 'email')) {
            Schema::table('enquiries', function (Blueprint $table) {
                $table->string('email')->nullable()->after('customer_id');
                $table->string('phone')->nullable()->after('email');
                $table->text('internal_notes')->nullable()->after('message');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('enquiries', 'email')) {
            Schema::table('enquiries', function (Blueprint $table) {
                $table->dropColumn(['email', 'phone', 'internal_notes']);
            });
        }
    }
};
