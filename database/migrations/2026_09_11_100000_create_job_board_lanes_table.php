<?php

use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusColor;
use App\Ark\Operations\Workboard\JobBoardLaneCatalogDefaults;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_board_lanes', function (Blueprint $table) {
            $table->id();
            $table->string('key', 32)->unique();
            $table->string('name', 64);
            $table->string('color', 16)->default(RepairOrderStatusColor::SECONDARY);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        $now = now();

        foreach (JobBoardLaneCatalogDefaults::lanes() as $lane) {
            DB::table('job_board_lanes')->insert(array_merge($lane, [
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        $this->remapExistingStatusLanes();
    }

    public function down(): void
    {
        Schema::dropIfExists('job_board_lanes');
    }

    private function remapExistingStatusLanes(): void
    {
        if (! Schema::hasTable('ro_statuses')) {
            return;
        }

        $knownKeys = JobBoardLaneCatalogDefaults::keys();

        foreach (DB::table('ro_statuses')->orderBy('id')->get() as $status) {
            $slug = (string) $status->slug;
            $isTerminal = (bool) $status->is_terminal;
            $mapped = $isTerminal
                ? null
                : JobBoardLaneCatalogDefaults::homeLaneKey($status->advisor_lane_key, $slug);

            if ($mapped !== null && ! in_array($mapped, $knownKeys, true)) {
                $this->ensureCustomLane($mapped, (string) $status->name, (string) ($status->color ?: RepairOrderStatusColor::SECONDARY));
            }

            $updates = [];

            if ($status->advisor_lane_key !== $mapped) {
                $updates['advisor_lane_key'] = $mapped;
            }

            if (in_array($slug, ['draft', 'estimate'], true) && ! $isTerminal && ! $status->show_on_advisor_board) {
                $updates['show_on_advisor_board'] = true;
            }

            if ($isTerminal) {
                $updates['advisor_lane_key'] = null;
                $updates['show_on_advisor_board'] = false;
                $updates['show_on_technician_board'] = false;
            }

            if ($updates !== []) {
                DB::table('ro_statuses')->where('id', $status->id)->update($updates);
            }
        }
    }

    private function ensureCustomLane(string $key, string $name, string $color): void
    {
        if (DB::table('job_board_lanes')->where('key', $key)->exists()) {
            return;
        }

        $maxSort = (int) DB::table('job_board_lanes')->max('sort_order');

        DB::table('job_board_lanes')->insert([
            'key' => $key,
            'name' => $name !== '' ? $name : ucwords(str_replace('_', ' ', $key)),
            'color' => RepairOrderStatusColor::normalize($color),
            'sort_order' => $maxSort + 1,
            'active' => true,
            'is_system' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
