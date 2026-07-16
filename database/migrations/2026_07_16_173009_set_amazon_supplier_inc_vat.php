<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $amazon = DB::table('suppliers')->where('name', 'Amazon')->first();

        if ($amazon) {
            DB::table('suppliers')
                ->where('id', $amazon->id)
                ->update(['default_trade_price_includes_vat' => true]);

            DB::table('product_supplier')
                ->where('supplier_id', $amazon->id)
                ->update(['trade_price_includes_vat' => true]);
        }
    }

    public function down(): void
    {
        $amazon = DB::table('suppliers')->where('name', 'Amazon')->first();

        if ($amazon) {
            DB::table('suppliers')
                ->where('id', $amazon->id)
                ->update(['default_trade_price_includes_vat' => false]);

            DB::table('product_supplier')
                ->where('supplier_id', $amazon->id)
                ->update(['trade_price_includes_vat' => false]);
        }
    }
};
