<?php

use App\Ark\Growth\PublicSurface\PublicMarketingUrl;
use App\Ark\Growth\Seo\SeoEngine;
use App\Ark\Operations\Leads\Public\CommonProblemFeaturedMedia;
use App\Ark\Operations\Leads\Public\CommonProblemFeaturedMediaOptimizer;
use App\Ark\Operations\Leads\Public\CommonProblemRegistry;
use App\Ark\Operations\Leads\Public\PublicSurfaceSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    Storage::fake('public');
});

test('common problem page renders without featured media container when none is set', function (): void {
    $this->get(route('public.common-problems.show', 'engine-overheating'))
        ->assertOk()
        ->assertDontSee('public-featured-media', false);
});

test('common problem page renders featured media when configured', function (): void {
    $path = CommonProblemFeaturedMedia::STORAGE_PREFIX.'engine-overheating/photo-one.jpg';
    Storage::disk('public')->put($path, 'fake-image');

    CommonProblemFeaturedMedia::persistGalleryForSlug('engine-overheating', [[
        'id' => 'photo-one',
        'path' => $path,
        'alt' => 'Technician pressure testing a cooling system at Demo Auto Repair',
        'caption' => 'Cooling system verification before recommending parts.',
    ]]);

    $this->get(route('public.common-problems.show', 'engine-overheating'))
        ->assertOk()
        ->assertSee('public-featured-media', false)
        ->assertSee('Technician pressure testing a cooling system at Demo Auto Repair', false)
        ->assertSee('Cooling system verification before recommending parts.', false)
        ->assertSee('loading="eager"', false)
        ->assertSee('fetchpriority="high"', false)
        ->assertDontSee('public-featured-media-lightbox', false);
});

test('legacy single-image storage normalizes to a one-item gallery', function (): void {
    $path = CommonProblemFeaturedMedia::STORAGE_PREFIX.'engine-overheating.jpg';
    Storage::disk('public')->put($path, 'fake-image');

    $raw = \App\Ark\Operations\Leads\Public\ShopPublicSurfaceRaw::read();
    $raw['common_problem_featured_media'] = [
        'engine-overheating' => [
            'path' => $path,
            'alt' => 'Technician pressure testing a cooling system at Demo Auto Repair',
            'caption' => 'Cooling system verification before recommending parts.',
        ],
    ];
    \App\Ark\Operations\Leads\Public\ShopPublicSurfaceRaw::write($raw);

    $gallery = CommonProblemFeaturedMedia::galleryForDisplay(
        CommonProblemFeaturedMedia::rawForSlug('engine-overheating', null),
    );

    expect($gallery)->toHaveCount(1)
        ->and($gallery[0]['alt'])->toBe('Technician pressure testing a cooling system at Demo Auto Repair');
});

test('featured media rotates across gallery images on reload', function (): void {
    $slug = 'engine-overheating';
    $paths = [
        CommonProblemFeaturedMedia::STORAGE_PREFIX.$slug.'/one.jpg',
        CommonProblemFeaturedMedia::STORAGE_PREFIX.$slug.'/two.jpg',
    ];

    foreach ($paths as $path) {
        Storage::disk('public')->put($path, 'fake-image');
    }

    CommonProblemFeaturedMedia::persistGalleryForSlug($slug, [
        [
            'id' => 'one',
            'path' => $paths[0],
            'alt' => 'Technician pressure testing a radiator on a Subaru Outback',
            'caption' => '',
        ],
        [
            'id' => 'two',
            'path' => $paths[1],
            'alt' => 'Technician replacing a thermostat on a Honda Civic',
            'caption' => '',
        ],
    ]);

    $raw = CommonProblemFeaturedMedia::rawForSlug($slug, null);
    $seen = [];

    for ($attempt = 0; $attempt < 12; $attempt++) {
        $featured = CommonProblemFeaturedMedia::featuredForDisplay($raw);
        $seen[$featured['url']] = true;
    }

    expect($seen)->toHaveCount(2);
});

test('admin can upload featured media gallery for a common problem page', function (): void {
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    $this->actingAs($admin)
        ->get(route('website.page-media.index'))
        ->assertOk()
        ->assertSee('Page featured media', false)
        ->assertSee('Engine Overheating', false);

    $this->actingAs($admin)
        ->patch(route('website.page-media.update', 'brake-noise'), [
            'new_files' => [
                UploadedFile::fake()->image('brake-noise.jpg', 1600, 900),
            ],
            'new_alts' => [
                'Technician measuring brake pad thickness on a Demo City vehicle',
            ],
            'new_captions' => [
                'Brake pad measurement before recommending replacement.',
            ],
        ])
        ->assertRedirect(route('website.page-media.edit', 'brake-noise'));

    expect(CommonProblemFeaturedMedia::forSlug('brake-noise'))->toHaveCount(1);

    if (CommonProblemFeaturedMediaOptimizer::isAvailable()) {
        expect(CommonProblemFeaturedMedia::forSlug('brake-noise')[0]['path'])->toMatch('/-display\.(webp|jpe?g)$/');
    }

    $this->get(route('public.common-problems.show', 'brake-noise'))
        ->assertOk()
        ->assertSee('Technician measuring brake pad thickness on a Demo City vehicle', false);
});

test('admin can manage multiple gallery images with order and metadata', function (): void {
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $slug = 'engine-overheating';
    $firstPath = CommonProblemFeaturedMedia::STORAGE_PREFIX.$slug.'/first.jpg';
    $secondPath = CommonProblemFeaturedMedia::STORAGE_PREFIX.$slug.'/second.jpg';

    Storage::disk('public')->put($firstPath, 'fake-one');
    Storage::disk('public')->put($secondPath, 'fake-two');

    CommonProblemFeaturedMedia::persistGalleryForSlug($slug, [
        [
            'id' => 'first',
            'path' => $firstPath,
            'alt' => 'Technician pressure testing a radiator on a Subaru Outback',
            'caption' => 'Primary cooling system photo',
        ],
        [
            'id' => 'second',
            'path' => $secondPath,
            'alt' => 'Technician replacing a thermostat on a Honda Civic',
            'caption' => 'Thermostat replacement photo',
        ],
    ]);

    $this->actingAs($admin)
        ->patch(route('website.page-media.update', $slug), [
            'items' => [
                [
                    'id' => 'second',
                    'alt' => 'Technician replacing a thermostat on a Honda Civic at Demo Auto Repair',
                    'caption' => 'Thermostat replacement photo',
                ],
                [
                    'id' => 'first',
                    'alt' => 'Technician pressure testing a radiator on a Subaru Outback at Demo Auto Repair',
                    'caption' => 'Primary cooling system photo',
                ],
            ],
            'new_files' => [
                UploadedFile::fake()->image('cooling-fan.jpg', 1600, 900),
            ],
            'new_alts' => [
                'Technician diagnosing a cooling fan on a Toyota Camry at Demo Auto Repair',
            ],
            'new_captions' => [
                'Cooling fan diagnosis photo',
            ],
        ])
        ->assertRedirect(route('website.page-media.edit', $slug));

    $gallery = CommonProblemFeaturedMedia::forSlug($slug);

    expect($gallery)->toHaveCount(3)
        ->and($gallery[0]['id'])->toBe('second')
        ->and($gallery[2]['alt'])->toContain('cooling fan');
});

test('homepage settings save preserves common problem featured media map', function (): void {
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $path = CommonProblemFeaturedMedia::STORAGE_PREFIX.'oil-leak/photo.webp';
    Storage::disk('public')->put($path, 'fake');

    CommonProblemFeaturedMedia::persistGalleryForSlug('oil-leak', [[
        'id' => 'oil-leak-photo',
        'path' => $path,
        'alt' => 'Engine on lift showing an oil leak inspection at Demo Auto Repair',
        'caption' => '',
    ]]);

    $this->actingAs($admin)
        ->patch(route('website.manage.update'), [
            'google_rating' => PublicSurfaceSettings::current()['google_rating'],
            'google_review_count' => PublicSurfaceSettings::current()['google_review_count'],
            'google_reviews_url' => PublicSurfaceSettings::current()['google_reviews_url'],
            'headline' => 'Need help with your vehicle?',
        ])
        ->assertRedirect(route('website.manage'));

    expect(CommonProblemFeaturedMedia::forSlug('oil-leak'))->toHaveCount(1);
});

test('page media index shows photo status for each page', function (): void {
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $path = CommonProblemFeaturedMedia::STORAGE_PREFIX.'engine-overheating/hero.jpg';
    Storage::disk('public')->put($path, 'fake-image');

    CommonProblemFeaturedMedia::persistGalleryForSlug('engine-overheating', [[
        'id' => 'hero',
        'path' => $path,
        'alt' => 'Technician pressure testing a cooling system on a Subaru Outback',
        'caption' => '',
    ]]);

    $this->actingAs($admin)
        ->get(route('website.page-media.index'))
        ->assertOk()
        ->assertSee('Engine Overheating', false)
        ->assertSee('1 photo', false)
        ->assertSee('No photo', false);
});

test('admin rejects generic alt text on featured media upload', function (): void {
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    $this->actingAs($admin)
        ->patch(route('website.page-media.update', 'brake-noise'), [
            'new_files' => [
                UploadedFile::fake()->image('brake-noise.jpg', 1600, 900),
            ],
            'new_alts' => [
                'engine',
            ],
        ])
        ->assertSessionHasErrors('new_alts.0');

    expect(CommonProblemFeaturedMedia::forSlug('brake-noise'))->toBe([]);
});

test('every common problem page without featured media keeps layout and default og image', function (): void {
    foreach (CommonProblemRegistry::slugs() as $slug) {
        $response = $this->get(route('public.common-problems.show', $slug));

        $response->assertOk();
        $content = $response->getContent();

        expect($content)->not->toContain('public-featured-media');
        expect($content)->toContain('public-page-title');
        expect($content)->toContain('public-cp-answer');
        expect($content)->not->toContain('public-hero-trust-chips');
        expect($content)->not->toContain('common-problem-media/');

        if (str_contains($content, 'property="og:image"')) {
            expect($content)->not->toContain('common-problem-media/');
        }
    }
});

test('common problem page with featured media outputs og and twitter image metadata from primary image', function (): void {
    $slug = 'engine-overheating';
    $primaryPath = CommonProblemFeaturedMedia::STORAGE_PREFIX.$slug.'/primary.jpg';
    $secondaryPath = CommonProblemFeaturedMedia::STORAGE_PREFIX.$slug.'/secondary.jpg';
    Storage::disk('public')->put($primaryPath, 'fake-image');
    Storage::disk('public')->put($secondaryPath, 'fake-image-two');
    $primaryAlt = 'Technician pressure testing a cooling system on a Subaru Outback at Demo Auto Repair';
    $secondaryAlt = 'Technician replacing a thermostat on a Honda Civic at Demo Auto Repair';

    CommonProblemFeaturedMedia::persistGalleryForSlug($slug, [
        [
            'id' => 'primary',
            'path' => $primaryPath,
            'alt' => $primaryAlt,
            'caption' => '',
        ],
        [
            'id' => 'secondary',
            'path' => $secondaryPath,
            'alt' => $secondaryAlt,
            'caption' => '',
        ],
    ]);

    $problem = CommonProblemRegistry::find($slug);
    $seo = app(SeoEngine::class)->forCommonProblem($problem);
    $expectedImage = PublicMarketingUrl::absoluteIfRelative(
        PublicSurfaceSettings::photoUrl($primaryPath),
    );

    expect($seo->openGraph['image'] ?? null)->toBe($expectedImage);
    expect($seo->twitter['image'] ?? null)->toBe($expectedImage);

    $this->get(route('public.common-problems.show', $slug))
        ->assertOk()
        ->assertSee('property="og:image"', false)
        ->assertSee('name="twitter:image"', false)
        ->assertSee($expectedImage, false)
        ->assertSee('loading="eager"', false)
        ->assertSee('fetchpriority="high"', false)
        ->assertSee('decoding="async"', false)
        ->assertSee('public-featured-media-lightbox', false);
});

test('removing all gallery items deletes stored files', function (): void {
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $slug = 'brake-noise';
    $path = CommonProblemFeaturedMedia::STORAGE_PREFIX.$slug.'/brake.jpg';
    Storage::disk('public')->put($path, 'fake-image');

    CommonProblemFeaturedMedia::persistGalleryForSlug($slug, [[
        'id' => 'brake',
        'path' => $path,
        'alt' => 'Technician measuring brake pad thickness on a Demo City vehicle',
        'caption' => '',
    ]]);

    $this->actingAs($admin)
        ->patch(route('website.page-media.update', $slug), [
            'items' => [],
        ])
        ->assertRedirect(route('website.page-media.edit', $slug));

    expect(CommonProblemFeaturedMedia::forSlug($slug))->toBe([])
        ->and(Storage::disk('public')->exists($path))->toBeFalse();
});
