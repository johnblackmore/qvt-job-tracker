<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_model_configs', function (Blueprint $table) {
            $table->string('api_type', 30)->nullable()->after('has_vision');
            $table->boolean('supports_text')->default(true)->after('api_type');
            $table->boolean('supports_audio')->default(false)->after('supports_text');
            $table->boolean('supports_file_uploads')->default(false)->after('supports_audio');
        });
    }

    public function down(): void
    {
        Schema::table('ai_model_configs', function (Blueprint $table) {
            $table->dropColumn(['api_type', 'supports_text', 'supports_audio', 'supports_file_uploads']);
        });
    }
};
