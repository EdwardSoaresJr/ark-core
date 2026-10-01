<?php

use App\Ark\Dragon\Agent\Bakeoff\DragonFloorBakeoffCatalog;
use App\Ark\Dragon\ServiceAdvisor\ServiceAdvisorFactPreservationCheck;

test('floor bake-off catalog is thirty real shop tasks', function (): void {
    $tasks = DragonFloorBakeoffCatalog::tasks();
    expect($tasks)->toHaveCount(30)
        ->and(DragonFloorBakeoffCatalog::ACCEPTANCE)->toContain('competent employee');

    $ids = array_column($tasks, 'id');
    expect($ids)->toHaveCount(count(array_unique($ids)));

    $families = array_unique(array_column($tasks, 'family'));
    expect($families)->toContain('awareness')
        ->and($families)->toContain('estimate_rewrite')
        ->and($families)->toContain('estimate_critique')
        ->and($families)->toContain('knowledge')
        ->and($families)->toContain('multi_step');
});

test('rewrite bake-off tasks are wired to the service advisor preservation check', function (): void {
    $check = new ServiceAdvisorFactPreservationCheck;
    $rewrite = collect(DragonFloorBakeoffCatalog::tasks())
        ->firstWhere('id', '14_rewrite_pads');

    expect($rewrite['preserve_source'])->toBe('rear pads 2mm rotors grooved')
        ->and($check->check(
            $rewrite['preserve_source'],
            'Rear brake pads measured at 2 mm. Rotors are grooved.',
        )['ok'])->toBeTrue()
        ->and($check->check(
            $rewrite['preserve_source'],
            'Unsafe to drive. Pads are 1 mm.',
        )['ok'])->toBeFalse();
});

test('dragon floor bake-off dry-run lists the frozen set', function (): void {
    $this->artisan('dragon:floor-bakeoff', ['--dry-run' => true])
        ->expectsOutputToContain('01_shop_today')
        ->expectsOutputToContain('30_cannot_see_money')
        ->expectsOutputToContain('competent employee')
        ->assertSuccessful();
});
