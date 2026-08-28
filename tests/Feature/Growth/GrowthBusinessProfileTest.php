<?php

use App\Ark\Growth\Integrations\BusinessProfileIngestService;
use App\Ark\Growth\Integrations\GoogleBusinessProfileSyncService;
use App\Ark\Growth\Maintenance\GrowthSyncTaskKey;
use App\Ark\Growth\Maintenance\GrowthSyncTaskRecorder;
use App\Ark\Growth\Maintenance\GrowthSyncTaskStatus;
use App\Ark\Growth\Models\GrowthLocationMetric;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('ingests fixture business profile metrics', function (): void {
    config([
        'growth.integrations.google_business_profile.fixture_enabled' => true,
    ]);

    $rows = app(BusinessProfileIngestService::class)->ingestDay(now()->subDay());

    expect($rows)->toBe(8)
        ->and(GrowthLocationMetric::query()->where('metric', 'CALL_CLICKS')->exists())->toBeTrue();
});

it('backfills fixture business profile metrics across a date range', function (): void {
    config([
        'growth.integrations.google_business_profile.fixture_enabled' => true,
    ]);

    $end = now()->subDay()->startOfDay();
    $start = $end->copy()->subDays(6);

    $result = app(BusinessProfileIngestService::class)->ingestBetween($start, $end);

    expect($result['days'])->toBe(7)
        ->and($result['rows'])->toBe(56)
        ->and(GrowthLocationMetric::query()->distinct('report_date')->count('report_date'))->toBe(7);
});

it('records skipped when business profile adapters are disabled', function (): void {
    config([
        'growth.integrations.google_business_profile.fixture_enabled' => false,
    ]);

    ShopSettings::current()->update([
        'growth_integrations' => [
            'google_business_profile' => [
                'enabled' => false,
            ],
        ],
        'growth_google_service_account' => null,
    ]);

    app(GoogleBusinessProfileSyncService::class)->sync(now()->subDay());

    $task = app(GrowthSyncTaskRecorder::class)
        ->lastRun(GrowthSyncTaskKey::GoogleBusinessProfile);

    expect($task)->not->toBeNull()
        ->and($task->status)->toBe(GrowthSyncTaskStatus::Skipped);
});
