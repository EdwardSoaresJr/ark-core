<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addColumns('repair_order_concerns', 'recommendation_intent');
        $this->addColumns('shop_settings', 'default_recommendation_intent', defaultPrefix: 'default_');
        $this->addColumns('work_templates', 'recommendation_intent');

        $this->makeIntentNullable('repair_order_concerns', 'recommendation_intent');
        $this->makeIntentNullable('shop_settings', 'default_recommendation_intent');
        $this->makeIntentNullable('work_templates', 'recommendation_intent');

        $this->split('repair_order_concerns', 'recommendation_intent', 'recommendation_priority', 'information_only');
        $this->split('shop_settings', 'default_recommendation_intent', 'default_recommendation_priority', 'default_information_only');
        $this->split('work_templates', 'recommendation_intent', 'recommendation_priority', 'information_only');
    }

    public function down(): void
    {
        $this->restore('repair_order_concerns', 'recommendation_intent', 'recommendation_priority', 'information_only');
        $this->restore('shop_settings', 'default_recommendation_intent', 'default_recommendation_priority', 'default_information_only');
        $this->restore('work_templates', 'recommendation_intent', 'recommendation_priority', 'information_only');

        $this->dropColumns('repair_order_concerns', 'recommendation_priority', 'information_only');
        $this->dropColumns('shop_settings', 'default_recommendation_priority', 'default_information_only');
        $this->dropColumns('work_templates', 'recommendation_priority', 'information_only');
    }

    private function addColumns(string $table, string $after, string $defaultPrefix = ''): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table, $after, $defaultPrefix): void {
            if (! Schema::hasColumn($table, $defaultPrefix.'recommendation_priority')) {
                $blueprint->string($defaultPrefix.'recommendation_priority', 32)->nullable()->after($after);
            }

            if (! Schema::hasColumn($table, $defaultPrefix.'information_only')) {
                $blueprint->boolean($defaultPrefix.'information_only')->default(false)->after($defaultPrefix.'recommendation_priority');
            }
        });
    }

    private function split(string $table, string $intentColumn, string $priorityColumn, string $informationColumn): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $intentColumn) || ! Schema::hasColumn($table, $priorityColumn)) {
            return;
        }

        foreach ([
            'immediate_attention' => 'must_do',
            'high' => 'must_do',
            'safety_drivability' => 'must_do',
        ] as $from => $to) {
            DB::table($table)->where($intentColumn, $from)->update([
                $priorityColumn => $to,
                $informationColumn => false,
                $intentColumn => null,
            ]);
        }

        foreach ([
            'plan_soon' => 'should_do',
            'future_repair' => 'should_do',
        ] as $from => $to) {
            DB::table($table)->where($intentColumn, $from)->update([
                $priorityColumn => $to,
                $informationColumn => false,
                $intentColumn => null,
            ]);
        }

        foreach (['information_only', 'low', 'monitor'] as $from) {
            DB::table($table)->where($intentColumn, $from)->update([
                $priorityColumn => null,
                $informationColumn => true,
                $intentColumn => null,
            ]);
        }
    }

    private function makeIntentNullable(string $table, string $column): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column): void {
            $blueprint->string($column, 32)->nullable()->change();
        });
    }

    private function restore(string $table, string $intentColumn, string $priorityColumn, string $informationColumn): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $priorityColumn)) {
            return;
        }

        DB::table($table)->where($priorityColumn, 'must_do')->whereNull($intentColumn)->update([
            $intentColumn => 'immediate_attention',
        ]);

        DB::table($table)->whereIn($priorityColumn, ['should_do', 'could_do'])->whereNull($intentColumn)->update([
            $intentColumn => 'plan_soon',
        ]);

        DB::table($table)->where($informationColumn, true)->whereNull($intentColumn)->update([
            $intentColumn => 'information_only',
        ]);

        DB::table($table)->whereNull($intentColumn)->update([
            $intentColumn => 'maintenance',
        ]);
    }

    private function dropColumns(string $table, string $priorityColumn, string $informationColumn): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table, $priorityColumn, $informationColumn): void {
            $drop = array_values(array_filter(
                [$priorityColumn, $informationColumn],
                fn (string $column): bool => Schema::hasColumn($table, $column),
            ));

            if ($drop !== []) {
                $blueprint->dropColumn($drop);
            }
        });
    }
};
