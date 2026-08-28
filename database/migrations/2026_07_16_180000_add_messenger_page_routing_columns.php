<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_settings', function (Blueprint $table): void {
            $table->string('messenger_page_id', 64)->nullable()->after('messenger_app_secret');
            $table->text('messenger_page_access_token')->nullable()->after('messenger_page_id');
            $table->unique('messenger_page_id', 'shop_settings_messenger_page_id_unique');
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement(<<<'SQL'
                UPDATE shop_settings
                SET messenger_page_id = NULLIF(
                    TRIM(JSON_UNQUOTE(JSON_EXTRACT(communications_channels, '$.messenger.page_id'))),
                    ''
                )
                WHERE communications_channels IS NOT NULL
                  AND JSON_EXTRACT(communications_channels, '$.messenger.page_id') IS NOT NULL
            SQL);
        } else {
            foreach (DB::table('shop_settings')->select(['id', 'communications_channels'])->get() as $row) {
                $channels = json_decode((string) $row->communications_channels, true);
                $pageId = is_array($channels)
                    ? trim((string) data_get($channels, 'messenger.page_id', ''))
                    : '';

                if ($pageId === '') {
                    continue;
                }

                DB::table('shop_settings')
                    ->where('id', $row->id)
                    ->update(['messenger_page_id' => $pageId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('shop_settings', function (Blueprint $table): void {
            $table->dropUnique('shop_settings_messenger_page_id_unique');
            $table->dropColumn(['messenger_page_id', 'messenger_page_access_token']);
        });
    }
};
