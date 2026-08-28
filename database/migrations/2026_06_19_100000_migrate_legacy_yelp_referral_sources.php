<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('encounters') && Schema::hasColumn('encounters', 'source')) {
            DB::table('encounters')->where('source', 'yelp')->update(['source' => 'website']);
        }

        if (Schema::hasTable('customers') && Schema::hasColumn('customers', 'referral_source')) {
            DB::table('customers')->where('referral_source', 'yelp')->update(['referral_source' => null]);
        }
    }

    public function down(): void
    {
        //
    }
};
