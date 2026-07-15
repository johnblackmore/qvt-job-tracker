<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::update('UPDATE quotes SET total_retail = total_retail - labour_total WHERE labour_total > 0');
    }

    public function down(): void
    {
        DB::update('UPDATE quotes SET total_retail = total_retail + labour_total WHERE labour_total > 0');
    }
};
