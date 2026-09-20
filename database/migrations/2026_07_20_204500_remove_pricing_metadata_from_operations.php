<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Strips pricing metadata that briefly lived on Operation.
 * Operation owns Operation Class only — posture/legacy rate belong in LaborAuthority.
 *
 * Idempotent: create migration may already omit these columns (fresh installs / SQLite tests).
 */
return new class extends Migration
{
    public function up(): void
    {
        $drop = array_values(array_filter([
            Schema::hasColumn('operations', 'implies_billing_posture') ? 'implies_billing_posture' : null,
            Schema::hasColumn('operations', 'uses_legacy_category_rate') ? 'uses_legacy_category_rate' : null,
        ]));

        if ($drop === []) {
            return;
        }

        Schema::table('operations', function (Blueprint $table) use ($drop) {
            $table->dropColumn($drop);
        });
    }

    public function down(): void
    {
        Schema::table('operations', function (Blueprint $table) {
            if (! Schema::hasColumn('operations', 'implies_billing_posture')) {
                $table->string('implies_billing_posture', 24)->nullable()->after('operation_class_id');
            }

            if (! Schema::hasColumn('operations', 'uses_legacy_category_rate')) {
                $table->boolean('uses_legacy_category_rate')->default(false)->after('implies_billing_posture');
            }
        });
    }
};
