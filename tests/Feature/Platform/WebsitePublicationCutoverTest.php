<?php

use App\Ark\Platform\Website\WebsitePublication;
use App\Ark\Platform\Website\WebsiteSite;
use App\Ark\Website\PublishWebsiteCatalog;

test('publication cutover dry run preserves media and apply can roll back', function (): void {
    config([
        'website.custom_domains' => [[
            'domain' => 'lugsnplugs.com',
            'site_host' => 'lugsnplugs.arksms.com',
            'preferred' => true,
        ]],
    ]);

    $original = [
        'headline' => 'Shop headline stays',
        'shop_photos' => [
            ['path' => 'public-surface-photos/bay.jpg', 'role' => 'hero'],
        ],
        'composition_photos' => [
            ['path' => 'public-surface-photos/bench.jpg', 'role' => 'diagnostic_evidence'],
        ],
        'customer_reviews' => [
            ['quote' => 'Real customer', 'attribution' => 'Alex'],
        ],
        'reviews' => [
            ['quote' => 'Published review', 'attribution' => 'Sam'],
        ],
        'common_problems' => [],
    ];

    app(PublishWebsiteCatalog::class)->publish('lugsnplugs.com', $original, true);

    $this->artisan('website:cutover-publication', [
        '--from-host' => 'lugsnplugs.com',
        '--to-host' => 'lugsnplugs.arksms.com',
    ])->assertSuccessful()
        ->expectsOutputToContain('dry_run: yes')
        ->expectsOutputToContain('old_public_host: lugsnplugs.com')
        ->expectsOutputToContain('new_public_host: lugsnplugs.arksms.com')
        ->expectsOutputToContain('current_version: 1')
        ->expectsOutputToContain('shop_photos_before: 1')
        ->expectsOutputToContain('shop_photos_after: 1')
        ->expectsOutputToContain('composition_photos_after: 1')
        ->expectsOutputToContain('customer_reviews_after: 1')
        ->expectsOutputToContain('reviews_after: 1')
        ->expectsOutputToContain('common_problems_before: 0')
        ->expectsOutputToContain('common_problems_after: 45')
        ->expectsOutputToContain('preferred_canonical: lugsnplugs.com')
        ->expectsOutputToContain('preserved_keys: shop_photos,composition_photos,customer_reviews,reviews')
        ->expectsOutputToContain('warnings: none');

    expect(WebsiteSite::query()->sole()->public_host)->toBe('lugsnplugs.com')
        ->and(WebsitePublication::query()->where('is_current', true)->count())->toBe(1);

    $this->artisan('website:cutover-publication', [
        '--from-host' => 'lugsnplugs.com',
        '--to-host' => 'lugsnplugs.arksms.com',
        '--apply' => true,
    ])->assertSuccessful()
        ->expectsOutputToContain('applied: yes')
        ->expectsOutputToContain('rollback_version: 1')
        ->expectsOutputToContain('rollback_host: lugsnplugs.com');

    $site = WebsiteSite::query()->sole();
    $current = WebsitePublication::query()->where('is_current', true)->sole();
    $previous = WebsitePublication::query()->where('version', 1)->sole();

    expect($site->public_host)->toBe('lugsnplugs.arksms.com')
        ->and(WebsiteSite::query()->where('public_host', 'lugsnplugs.com')->count())->toBe(0)
        ->and($current->version)->toBe(2)
        ->and($current->document['shop_photos'])->toBe($original['shop_photos'])
        ->and($current->document['reviews'])->toBe($original['reviews'])
        ->and($current->document['headline'])->toBe('Shop headline stays')
        ->and(array_column($current->document['common_problems'], 'slug'))->toContain('p0420')
        ->and($previous->is_current)->toBeFalse()
        ->and($previous->document['common_problems'])->toBe([])
        ->and($previous->document['shop_photos'])->toBe($original['shop_photos']);

    $this->artisan('website:cutover-publication', [
        '--revert-site' => $site->id,
        '--revert-version' => 1,
        '--revert-host' => 'lugsnplugs.com',
        '--apply' => true,
    ])->assertSuccessful()
        ->expectsOutputToContain('reverted: yes');

    $restored = WebsitePublication::query()->where('is_current', true)->sole();
    expect(WebsiteSite::query()->sole()->public_host)->toBe('lugsnplugs.com')
        ->and($restored->version)->toBe(1)
        ->and($restored->document['shop_photos'])->toBe($original['shop_photos'])
        ->and($restored->document['common_problems'])->toBe([]);
});
