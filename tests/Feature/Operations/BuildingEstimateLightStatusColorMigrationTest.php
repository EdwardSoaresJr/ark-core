<?php

use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusCatalog;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusCatalogDefaults;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusColor;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusDefinition;
use App\Ark\Operations\Workboard\JobBoardLane;
use App\Ark\Operations\Workboard\JobBoardLaneCatalog;
use App\Ark\Operations\Workboard\JobBoardLaneCatalogDefaults;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(RepairOrderStatusCatalogSeeder::class);
});

it('migrates only former-default estimate and estimates-lane colors to light', function () {
    RepairOrderStatusDefinition::query()->where('slug', 'estimate')->update(['color' => RepairOrderStatusColor::SECONDARY]);
    RepairOrderStatusDefinition::query()->where('slug', 'closed')->update(['color' => RepairOrderStatusColor::DARK]);
    RepairOrderStatusDefinition::query()->where('slug', 'waiting_approval')->update(['color' => RepairOrderStatusColor::WARNING]);
    JobBoardLane::query()->where('key', JobBoardLaneCatalogDefaults::ESTIMATES)->update(['color' => RepairOrderStatusColor::SECONDARY]);
    JobBoardLane::query()->where('key', JobBoardLaneCatalogDefaults::PARTS)->update(['color' => RepairOrderStatusColor::INFO]);

    // Simulate a tenant that already customized Building Estimate away from the platform default.
    RepairOrderStatusDefinition::query()->where('slug', 'estimate')->update(['color' => RepairOrderStatusColor::PRIMARY]);

    DB::table('migrations')->where('migration', '2026_11_26_180000_assign_building_estimate_light_status_color')->delete();

    expect(Artisan::call('migrate', ['--path' => 'database/migrations/2026_11_26_180000_assign_building_estimate_light_status_color.php', '--force' => true]))
        ->toBe(0);

    expect(RepairOrderStatusDefinition::query()->where('slug', 'estimate')->value('color'))
        ->toBe(RepairOrderStatusColor::PRIMARY)
        ->and(RepairOrderStatusDefinition::query()->where('slug', 'closed')->value('color'))
        ->toBe(RepairOrderStatusColor::DARK)
        ->and(RepairOrderStatusDefinition::query()->where('slug', 'waiting_approval')->value('color'))
        ->toBe(RepairOrderStatusColor::WARNING)
        ->and(JobBoardLane::query()->where('key', JobBoardLaneCatalogDefaults::PARTS)->value('color'))
        ->toBe(RepairOrderStatusColor::INFO);

    // Restore former defaults and migrate again — only those rows move to light.
    RepairOrderStatusDefinition::query()->where('slug', 'estimate')->update(['color' => RepairOrderStatusColor::SECONDARY]);
    JobBoardLane::query()->where('key', JobBoardLaneCatalogDefaults::ESTIMATES)->update(['color' => RepairOrderStatusColor::SECONDARY]);
    DB::table('migrations')->where('migration', '2026_11_26_180000_assign_building_estimate_light_status_color')->delete();

    expect(Artisan::call('migrate', ['--path' => 'database/migrations/2026_11_26_180000_assign_building_estimate_light_status_color.php', '--force' => true]))
        ->toBe(0);

    expect(RepairOrderStatusDefinition::query()->where('slug', 'estimate')->value('color'))
        ->toBe(RepairOrderStatusColor::LIGHT)
        ->and(JobBoardLane::query()->where('key', JobBoardLaneCatalogDefaults::ESTIMATES)->value('color'))
        ->toBe(RepairOrderStatusColor::LIGHT);

    // Idempotent: already-light rows stay light when migrate runs again after a fake rollback of the migrations table only.
    DB::table('migrations')->where('migration', '2026_11_26_180000_assign_building_estimate_light_status_color')->delete();
    expect(Artisan::call('migrate', ['--path' => 'database/migrations/2026_11_26_180000_assign_building_estimate_light_status_color.php', '--force' => true]))
        ->toBe(0);
    expect(RepairOrderStatusDefinition::query()->where('slug', 'estimate')->value('color'))
        ->toBe(RepairOrderStatusColor::LIGHT);

    // Non-destructive down: rolling back must not rewrite light → secondary.
    expect(Artisan::call('migrate:rollback', [
        '--path' => 'database/migrations/2026_11_26_180000_assign_building_estimate_light_status_color.php',
        '--force' => true,
    ]))->toBe(0);

    expect(RepairOrderStatusDefinition::query()->where('slug', 'estimate')->value('color'))
        ->toBe(RepairOrderStatusColor::LIGHT)
        ->and(JobBoardLane::query()->where('key', JobBoardLaneCatalogDefaults::ESTIMATES)->value('color'))
        ->toBe(RepairOrderStatusColor::LIGHT);
});

it('keeps tenant colors through catalog sync after the light default is seeded', function () {
    RepairOrderStatusDefinition::query()->where('slug', 'estimate')->update(['color' => RepairOrderStatusColor::WARNING]);
    JobBoardLane::query()->where('key', JobBoardLaneCatalogDefaults::ESTIMATES)->update(['color' => RepairOrderStatusColor::PRIMARY]);

    RepairOrderStatusCatalogDefaults::sync(app(RepairOrderStatusCatalog::class));
    app(JobBoardLaneCatalog::class)->forgetCache();
    app(RepairOrderStatusCatalog::class)->forgetCache();

    expect(RepairOrderStatusDefinition::query()->where('slug', 'estimate')->value('color'))
        ->toBe(RepairOrderStatusColor::WARNING)
        ->and(JobBoardLane::query()->where('key', JobBoardLaneCatalogDefaults::ESTIMATES)->value('color'))
        ->toBe(RepairOrderStatusColor::PRIMARY);
});
