<?php

use App\Ark\Operations\Communications\CommunicationsQuickReplyTemplates;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shop_settings') || ! Schema::hasColumn('shop_settings', 'quick_reply_templates')) {
            return;
        }

        DB::table('shop_settings')
            ->whereNull('quick_reply_templates')
            ->update([
                'quick_reply_templates' => json_encode(
                    CommunicationsQuickReplyTemplates::defaults(),
                    JSON_THROW_ON_ERROR,
                ),
            ]);
    }

    public function down(): void
    {
        // Starting copy only. Shops may already have edited it.
    }
};
