<?php

use App\Ark\Operations\Leads\Public\CommonProblemFeaturedMedia;
use App\Ark\Operations\Leads\Public\CommonProblemFeaturedMediaOptimizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('public');
});

test('optimizer stores display and large webp variants on upload', function (): void {
    if (! CommonProblemFeaturedMediaOptimizer::isAvailable()) {
        $this->markTestSkipped('GD with WebP support is not available.');
    }

    $file = UploadedFile::fake()->image('cooling-system.jpg', 2000, 1125);
    $displayPath = CommonProblemFeaturedMediaOptimizer::storeFromUpload('engine-overheating', $file);

    expect($displayPath)->toMatch('/-display\.(webp|jpe?g)$/');

    $largePath = CommonProblemFeaturedMediaOptimizer::largePathForDisplayPath((string) $displayPath);

    Storage::disk('public')->assertExists((string) $displayPath);
    Storage::disk('public')->assertExists($largePath);

    $displaySize = Storage::disk('public')->size((string) $displayPath);
    $largeSize = Storage::disk('public')->size($largePath);

    expect($displaySize)->toBeGreaterThan(0)
        ->and($largeSize)->toBeGreaterThan(0)
        ->and($largeSize)->toBeGreaterThanOrEqual($displaySize);
});

test('store upload prefers optimized webp variants when gd is available', function (): void {
    if (! CommonProblemFeaturedMediaOptimizer::isAvailable()) {
        $this->markTestSkipped('GD with WebP support is not available.');
    }

    $path = CommonProblemFeaturedMedia::storeUpload(
        'engine-overheating',
        UploadedFile::fake()->image('hero.jpg', 1800, 1012),
    );

    expect($path)->toMatch('/-display\.(webp|jpe?g)$/')
        ->and(Storage::disk('public')->exists($path))->toBeTrue()
        ->and(Storage::disk('public')->exists(CommonProblemFeaturedMediaOptimizer::largePathForDisplayPath($path)))->toBeTrue();
});

test('optimize command converts legacy uploads to webp variants', function (): void {
    if (! CommonProblemFeaturedMediaOptimizer::isAvailable()) {
        $this->markTestSkipped('GD with WebP support is not available.');
    }

    $slug = 'engine-overheating';
    $legacyPath = CommonProblemFeaturedMedia::STORAGE_PREFIX.$slug.'/legacy.jpg';
    $file = UploadedFile::fake()->image('legacy.jpg', 1800, 1012);
    Storage::disk('public')->put($legacyPath, file_get_contents($file->getRealPath()));

    CommonProblemFeaturedMedia::persistGalleryForSlug($slug, [[
        'id' => 'legacy',
        'path' => $legacyPath,
        'alt' => 'Technician pressure testing a cooling system at Demo Auto Repair',
        'caption' => '',
    ]]);

    $this->artisan('ark:public-surface:optimize-common-problem-media', ['--only-missing' => true])
        ->assertSuccessful();

    $gallery = CommonProblemFeaturedMedia::forSlug($slug);

    expect($gallery)->toHaveCount(1)
        ->and($gallery[0]['path'])->toMatch('/-display\.(webp|jpe?g)$/')
        ->and(Storage::disk('public')->exists($gallery[0]['path']))->toBeTrue()
        ->and(Storage::disk('public')->exists($legacyPath))->toBeFalse();
});
