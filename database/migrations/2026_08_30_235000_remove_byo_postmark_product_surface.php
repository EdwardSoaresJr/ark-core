<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shop_settings')) {
            return;
        }

        $columns = [
            'postmark_token',
            'postmark_message_stream_id',
            'email_provider',
        ];

        $existing = array_values(array_filter(
            $columns,
            fn (string $column): bool => Schema::hasColumn('shop_settings', $column),
        ));

        if ($existing === []) {
            return;
        }

        Schema::table('shop_settings', function (Blueprint $table) use ($existing): void {
            $table->dropColumn($existing);
        });
    }

    public function down(): void
    {
        //
    }
};
