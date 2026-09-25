<?php

use App\Ark\Website\Catalog\PublicWebsiteCatalog;
use App\Ark\Website\WebsitePublicationCatalogMerge;

test('catalog merge keeps shop media and reviews and adds common problems', function (): void {
    $publication = [
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
        'trust_signals' => ['repairpal_certified' => true],
        'common_problems' => [],
    ];

    $merged = (new WebsitePublicationCatalogMerge)->merge($publication, PublicWebsiteCatalog::document());
    $slugs = array_column($merged['common_problems'], 'slug');

    expect($merged['shop_photos'])->toBe($publication['shop_photos'])
        ->and($merged['composition_photos'])->toBe($publication['composition_photos'])
        ->and($merged['customer_reviews'])->toBe($publication['customer_reviews'])
        ->and($merged['reviews'])->toBe($publication['reviews'])
        ->and($merged['trust_signals'])->toBe($publication['trust_signals'])
        ->and($merged['headline'])->toBe('Shop headline stays')
        ->and($slugs)->toContain('check-engine-light', 'p0420', 'p0300', 'p0455')
        ->and($merged['pages']['repairpal-warranty']['title'])->toBe('RepairPal nationwide warranty')
        ->and($merged['pages']['warranty']['lede'])->toContain('24 months / 24,000 miles')
        ->and($merged['pages']['repairpal-warranty']['lede'])->toContain('12 months / 12,000 miles');
});
