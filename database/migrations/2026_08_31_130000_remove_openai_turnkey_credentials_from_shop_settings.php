<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shop_settings')) {
            return;
        }

        if (Schema::hasColumn('shop_settings', 'openai_api_key')) {
            DB::table('shop_settings')->update(['openai_api_key' => null]);
        }

        Schema::table('shop_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('shop_settings', 'openai_api_key')) {
                $table->dropColumn('openai_api_key');
            }

            if (Schema::hasColumn('shop_settings', 'openai_transcription_model')) {
                $table->dropColumn('openai_transcription_model');
            }

            if (Schema::hasColumn('shop_settings', 'openai_analysis_model')) {
                $table->dropColumn('openai_analysis_model');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('shop_settings')) {
            return;
        }

        Schema::table('shop_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('shop_settings', 'openai_api_key')) {
                $table->text('openai_api_key')->nullable();
            }

            if (! Schema::hasColumn('shop_settings', 'openai_transcription_model')) {
                $table->string('openai_transcription_model', 64)->nullable();
            }

            if (! Schema::hasColumn('shop_settings', 'openai_analysis_model')) {
                $table->string('openai_analysis_model', 64)->nullable();
            }
        });
    }
};
