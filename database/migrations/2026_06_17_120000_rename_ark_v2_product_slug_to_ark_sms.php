<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('user_product_access')
            ->where('product_slug', 'ark_v2')
            ->update(['product_slug' => 'ark_sms']);

        DB::table('oidc_clients')
            ->where('required_product', 'ark_v2')
            ->update(['required_product' => 'ark_sms']);
    }

    public function down(): void
    {
        DB::table('user_product_access')
            ->where('product_slug', 'ark_sms')
            ->update(['product_slug' => 'ark_v2']);

        DB::table('oidc_clients')
            ->where('required_product', 'ark_sms')
            ->update(['required_product' => 'ark_v2']);
    }
};
