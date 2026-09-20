<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Forward-only history migration. Column drops are owned by later migrations.
 */
return new class extends Migration
{
    public function up(): void
    {
        //
    }

    public function down(): void
    {
        //
    }
};
