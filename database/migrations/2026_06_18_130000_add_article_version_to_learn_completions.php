<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('learn_completions', 'article_version')) {
            Schema::table('learn_completions', function (Blueprint $table) {
                $table->unsignedSmallInteger('article_version')->default(1)->after('catalog_version');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('learn_completions', 'article_version')) {
            Schema::table('learn_completions', function (Blueprint $table) {
                $table->dropColumn('article_version');
            });
        }
    }
};
