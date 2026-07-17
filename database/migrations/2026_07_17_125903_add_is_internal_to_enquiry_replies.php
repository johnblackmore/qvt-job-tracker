<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enquiry_replies', function (Blueprint $table) {
            $table->boolean('is_internal')->default(false)->after('direction');
            $table->index('is_internal');
        });
    }

    public function down(): void
    {
        Schema::table('enquiry_replies', function (Blueprint $table) {
            $table->dropIndex(['is_internal']);
            $table->dropColumn('is_internal');
        });
    }
};
