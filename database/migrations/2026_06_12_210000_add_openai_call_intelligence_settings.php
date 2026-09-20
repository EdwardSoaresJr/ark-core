<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_settings', function (Blueprint $table): void {
            $anchor = Schema::hasColumn('shop_settings', 'postmark_message_stream_id')
                ? 'postmark_message_stream_id'
                : (Schema::hasColumn('shop_settings', 'shop_excellence_targets') ? 'shop_excellence_targets' : null);

            if (! Schema::hasColumn('shop_settings', 'openai_api_key')) {
                if ($anchor !== null) {
                    $table->text('openai_api_key')->nullable()->after($anchor);
                } else {
                    $table->text('openai_api_key')->nullable();
                }
            }

            if (! Schema::hasColumn('shop_settings', 'openai_transcription_model')) {
                $table->string('openai_transcription_model', 64)->nullable()->after('openai_api_key');
            }

            if (! Schema::hasColumn('shop_settings', 'openai_analysis_model')) {
                $table->string('openai_analysis_model', 64)->nullable()->after('openai_transcription_model');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shop_settings', function (Blueprint $table) {
            $table->dropColumn([
                'openai_api_key',
                'openai_transcription_model',
                'openai_analysis_model',
            ]);
        });
    }
};
