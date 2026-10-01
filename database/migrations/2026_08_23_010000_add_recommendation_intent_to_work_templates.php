<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('work_templates') || Schema::hasColumn('work_templates', 'recommendation_intent')) {
            return;
        }

        Schema::table('work_templates', function (Blueprint $table) {
            $table->string('recommendation_intent', 32)->default('maintenance')->after('internal_note');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('work_templates') || ! Schema::hasColumn('work_templates', 'recommendation_intent')) {
            return;
        }

        Schema::table('work_templates', function (Blueprint $table) {
            $table->dropColumn('recommendation_intent');
        });
    }
};
