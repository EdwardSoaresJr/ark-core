<?php

use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Platform\Shop;
use App\Ark\Platform\ShopStatus;
use App\Ark\Platform\Website\CoreWebsiteDocument;
use App\Ark\Platform\Website\ImportWebsiteDraftFromCoreAction;
use App\Ark\Platform\Website\ResolveWebsiteCoreConflictAction;
use App\Ark\Platform\Website\SaveWebsiteDraftAction;
use App\Ark\Platform\Website\WebsiteDocumentHash;
use App\Ark\Platform\Website\WebsiteDraftConflictException;
use App\Ark\Platform\Website\WebsiteDraftRevision;
use App\Ark\Platform\Website\WebsiteDraftRevisionAction;
use App\Ark\Platform\Website\WebsiteDraftRevisionConflictException;
use App\Ark\Platform\Website\WebsitePublication;
use App\Ark\Platform\Website\WebsiteSite;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    config()->set('platform_website.management_enabled', true);
    config()->set('platform_website.uploads_enabled', false);
});

function platformWebsiteShop(string $slug = 'test-garage'): Shop
{
    return Shop::query()->create([
        'uuid' => (string) Str::uuid(),
        'slug' => $slug,
        'display_name' => 'Test Garage',
        'status' => ShopStatus::Active,
    ]);
}

function seedCoreWebsiteDocument(array $overrides = []): array
{
    ShopSettings::current()->update([
        'shop_name' => $overrides['headline'] ?? 'Core Headline',
        'website' => $overrides['positioning_lede'] ?? 'Core lede line',
        'google_reviews_url' => $overrides['google_reviews_url'] ?? 'https://reviews.example/test',
    ]);
    ShopSettings::forgetCurrent();

    return CoreWebsiteDocument::read()['document'];
}

test('import reproduces the existing Core document without changing Core', function (): void {
    $original = seedCoreWebsiteDocument();
    $shop = platformWebsiteShop();
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    $site = app(ImportWebsiteDraftFromCoreAction::class)->execute($shop, $admin, 'example.test');

    expect($site->acknowledged_core_hash)->toBe(WebsiteDocumentHash::hash($original))
        ->and($site->draft?->document)->toBe($original)
        ->and($site->writing_authority->value)->toBe('core_live')
        ->and(CoreWebsiteDocument::read()['document'])->toBe($original)
        ->and(WebsitePublication::query()->where('is_current', true)->count())->toBe(0)
        ->and(WebsiteDraftRevision::query()->where('action', WebsiteDraftRevisionAction::Import)->count())->toBe(1);
});

test('platform draft edits do not change Core or create a current publication', function (): void {
    seedCoreWebsiteDocument();
    $shop = platformWebsiteShop();
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $site = app(ImportWebsiteDraftFromCoreAction::class)->execute($shop, $admin);
    $coreBefore = CoreWebsiteDocument::read();

    $edited = array_merge($coreBefore['document'], ['headline' => 'Platform Draft Headline']);
    app(SaveWebsiteDraftAction::class)->execute($site, $edited, 1, $admin);

    $site->refresh();
    $coreAfter = CoreWebsiteDocument::read();

    expect($site->draft?->document['headline'] ?? null)->toBe('Platform Draft Headline')
        ->and($coreAfter['hash'])->toBe($coreBefore['hash'])
        ->and($coreAfter['document']['headline'] ?? null)->toBe('Core Headline')
        ->and(WebsitePublication::query()->where('is_current', true)->count())->toBe(0)
        ->and(WebsiteDraftRevision::query()->where('action', WebsiteDraftRevisionAction::Edit)->count())->toBe(1);
});

test('concurrent draft edits produce a revision conflict', function (): void {
    seedCoreWebsiteDocument();
    $shop = platformWebsiteShop();
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $site = app(ImportWebsiteDraftFromCoreAction::class)->execute($shop, $admin);
    $document = $site->draft->document;

    app(SaveWebsiteDraftAction::class)->execute(
        $site,
        array_merge($document, ['headline' => 'First writer']),
        1,
        $admin,
    );

    expect(fn () => app(SaveWebsiteDraftAction::class)->execute(
        $site->fresh(),
        array_merge($document, ['headline' => 'Second writer']),
        1,
        $admin,
    ))->toThrow(WebsiteDraftRevisionConflictException::class);
});

test('core edits are detected and both resolutions are audited; later core edit conflicts again', function (): void {
    seedCoreWebsiteDocument();
    $shop = platformWebsiteShop();
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $site = app(ImportWebsiteDraftFromCoreAction::class)->execute($shop, $admin);

    app(SaveWebsiteDraftAction::class)->execute(
        $site,
        array_merge($site->draft->document, ['headline' => 'Platform kept']),
        1,
        $admin,
    );

    seedCoreWebsiteDocument(['headline' => 'Core changed once', 'positioning_lede' => 'New core lede']);
    $live = CoreWebsiteDocument::read();

    expect(fn () => app(SaveWebsiteDraftAction::class)->execute(
        $site->fresh(),
        array_merge($site->fresh()->draft->document, ['local_tagline' => 'x']),
        2,
        $admin,
    ))->toThrow(WebsiteDraftConflictException::class);

    app(ResolveWebsiteCoreConflictAction::class)->keepPlatform(
        $site->fresh(),
        $admin,
        2,
        $live['hash'],
    );

    $site->refresh();
    expect($site->acknowledged_core_hash)->toBe($live['hash'])
        ->and($site->draft->document['headline'] ?? null)->toBe('Platform kept')
        ->and(WebsiteDraftRevision::query()->where('action', WebsiteDraftRevisionAction::KeepPlatform)->count())->toBe(1);

    // Same Core hash: saves allowed.
    app(SaveWebsiteDraftAction::class)->execute(
        $site->fresh(),
        array_merge($site->fresh()->draft->document, ['local_tagline' => 'still platform']),
        3,
        $admin,
    );

    // Later Core change must conflict again.
    seedCoreWebsiteDocument(['headline' => 'Core changed twice']);
    expect(fn () => app(SaveWebsiteDraftAction::class)->execute(
        $site->fresh(),
        array_merge($site->fresh()->draft->document, ['local_tagline' => 'blocked']),
        4,
        $admin,
    ))->toThrow(WebsiteDraftConflictException::class);

    app(ResolveWebsiteCoreConflictAction::class)->acceptCore($site->fresh(), $admin, 4);
    $site->refresh();

    expect($site->draft->document['headline'] ?? null)->toBe('Core changed twice')
        ->and(WebsiteDraftRevision::query()->where('action', WebsiteDraftRevisionAction::AcceptCore)->count())->toBe(1);
});

test('saving a headline on the draft does not change Core shop identity', function (): void {
    $original = seedCoreWebsiteDocument();
    $shop = platformWebsiteShop('fidelity-garage');
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $site = app(ImportWebsiteDraftFromCoreAction::class)->execute($shop, $admin);
    $before = $site->draft->document;

    app(SaveWebsiteDraftAction::class)->execute(
        $site,
        array_merge($before, ['headline' => 'Platform Draft Headline']),
        1,
        $admin,
    );

    expect($site->fresh()->draft->document['headline'])->toBe('Platform Draft Headline')
        ->and($site->fresh()->draft->document['google_reviews_url'])->toBe($before['google_reviews_url'])
        ->and(CoreWebsiteDocument::read()['document'])->toBe($original);
});

test('management disabled still keeps imported website records', function (): void {
    seedCoreWebsiteDocument();
    $shop = platformWebsiteShop();
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $site = app(ImportWebsiteDraftFromCoreAction::class)->execute($shop, $admin);

    config()->set('platform_website.management_enabled', false);

    expect(WebsiteSite::query()->count())->toBe(1)
        ->and($site->fresh()->draft)->not->toBeNull()
        ->and(route('webhooks.cloud.website.show', ['publicHost' => 'example.test']))->toContain('/webhooks/cloud/website/');
});
