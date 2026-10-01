<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addOneCurrentPublicationIndex();
        $this->addPublicHostUnique();
    }

    public function down(): void
    {
        if ($this->hasIndex('website_sites', 'website_sites_public_host_unique')) {
            Schema::table('website_sites', function (Blueprint $table): void {
                $table->dropUnique('website_sites_public_host_unique');
            });
        }

        if ($this->hasIndex('website_publications', 'website_pub_one_current')) {
            Schema::table('website_publications', function (Blueprint $table): void {
                $table->dropIndex('website_pub_one_current');
            });
        }
    }

    private function addOneCurrentPublicationIndex(): void
    {
        if ($this->hasIndex('website_publications', 'website_pub_one_current')) {
            return;
        }

        // MySQL 8.4 error 1215: a stored generated column cannot reference
        // website_site_id while that column's foreign key exists.
        $expression = 'CASE WHEN is_current <> 0 THEN website_site_id ELSE NULL END';
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::statement("CREATE UNIQUE INDEX website_pub_one_current ON website_publications ({$expression})");

            return;
        }

        DB::statement("CREATE UNIQUE INDEX website_pub_one_current ON website_publications (({$expression}))");
    }

    private function addPublicHostUnique(): void
    {
        if ($this->hasIndex('website_sites', 'website_sites_public_host_unique')) {
            return;
        }

        Schema::table('website_sites', function (Blueprint $table): void {
            $table->unique('public_host', 'website_sites_public_host_unique');
        });
    }

    private function hasIndex(string $table, string $index): bool
    {
        $connection = Schema::getConnection();

        if ($connection->getDriverName() === 'sqlite') {
            return $connection->select(
                "select 1 as present from sqlite_master where type = 'index' and name = ? limit 1",
                [$index],
            ) !== [];
        }

        return $connection->select(
            'select 1 as present from information_schema.statistics where table_schema = database() and table_name = ? and index_name = ? limit 1',
            [$table, $index],
        ) !== [];
    }
};
