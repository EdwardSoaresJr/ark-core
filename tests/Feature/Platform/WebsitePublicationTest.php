<?php

use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Platform\Shop;
use App\Ark\Platform\ShopStatus;
use App\Ark\Platform\Website\CoreWebsiteDocument;
use App\Ark\Platform\Website\FoundryPublicationResolverRelease;
use App\Ark\Platform\Website\ImportWebsiteDraftFromCoreAction;
use App\Ark\Platform\Website\PublishWebsiteAction;
use App\Ark\Platform\Website\SwitchWebsiteWritingAuthorityAction;
use App\Ark\Platform\Website\WebsiteAuthoritySwitchException;
use App\Ark\Platform\Website\WebsiteDocumentHash;
use App\Ark\Platform\Website\WebsiteDraftDivergesFromCoreException;
use App\Ark\Platform\Website\WebsiteDraftRevision;
use App\Ark\Platform\Website\WebsiteDraftRevisionAction;
use App\Ark\Platform\Website\WebsiteDraftRevisionConflictException;
use App\Ark\Platform\Website\WebsitePublication;
use App\Ark\Platform\Website\WebsiteSite;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    config()->set('platform_website.management_enabled', true);
});

function publicationShop(string $slug): Shop
{
    return Shop::query()->create([
        'uuid' => (string) Str::uuid(),
        'slug' => $slug,
        'display_name' => $slug,
        'status' => ShopStatus::Active,
    ]);
}

function publicationCore(array $overrides = []): array
{
    ShopSettings::current()->update([
        'shop_name' => $overrides['headline'] ?? 'Core Headline',
        'website' => $overrides['positioning_lede'] ?? 'Core lede line',
        'google_reviews_url' => $overrides['google_reviews_url'] ?? 'https://reviews.example/test',
    ]);
    ShopSettings::forgetCurrent();

    return CoreWebsiteDocument::read()['document'];
}

function importedPublicationSite(string $slug, ?string $host, User $admin): WebsiteSite
{
    return app(ImportWebsiteDraftFromCoreAction::class)->execute(
        publicationShop($slug),
        $admin,
        $host,
    );
}

test('the first publication is the current core document and authority stays core live', function (): void {
    $document = publicationCore();
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $site = importedPublicationSite('publish-shop', 'publish.test', $admin);

    $publication = app(PublishWebsiteAction::class)->execute($site, 1, $admin);

    $site->refresh();

    expect($publication->is_current)->toBeTrue()
        ->and($publication->version)->toBe(1)
        ->and($publication->document)->toBe($document)
        ->and($publication->content_hash)->toBe(WebsiteDocumentHash::hash($document))
        ->and($publication->source_draft_revision)->toBe(1)
        ->and($site->writing_authority->value)->toBe('core_live')
        ->and(WebsitePublication::query()->where('is_current', true)->count())->toBe(1)
        ->and(WebsiteDraftRevision::query()->where('action', WebsiteDraftRevisionAction::Publish)->count())->toBe(1)
        ->and($site->draft?->revision)->toBe(2);
});

test('a stale draft revision publishes nothing', function (): void {
    publicationCore();
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $site = importedPublicationSite('stale-shop', 'stale.test', $admin);

    expect(fn () => app(PublishWebsiteAction::class)->execute($site, 4, $admin))
        ->toThrow(WebsiteDraftRevisionConflictException::class);

    expect(WebsitePublication::query()->count())->toBe(0)
        ->and($site->draft?->fresh()->revision)->toBe(1)
        ->and(WebsiteDraftRevision::query()->where('action', WebsiteDraftRevisionAction::Publish)->count())->toBe(0);
});

test('a second publisher with the same revision does not create another current publication', function (): void {
    publicationCore();
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $site = importedPublicationSite('race-shop', 'race.test', $admin);
    $action = app(PublishWebsiteAction::class);

    $action->execute($site, 1, $admin);

    expect(fn () => $action->execute($site->fresh(), 1, $admin))
        ->toThrow(WebsiteDraftRevisionConflictException::class);

    expect(WebsitePublication::query()->where('website_site_id', $site->id)->where('is_current', true)->count())->toBe(1)
        ->and(WebsitePublication::query()->where('website_site_id', $site->id)->count())->toBe(1);
});

test('a draft that does not match core is not published and does not change history', function (): void {
    publicationCore();
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $site = importedPublicationSite('diverge-shop', 'diverge.test', $admin);
    $draft = $site->draft;
    $draft->update([
        'document' => array_merge($draft->document, ['headline' => 'Draft only']),
    ]);

    expect(fn () => app(PublishWebsiteAction::class)->execute($site, 1, $admin))
        ->toThrow(WebsiteDraftDivergesFromCoreException::class);

    expect(WebsitePublication::query()->count())->toBe(0)
        ->and($draft->fresh()->revision)->toBe(1)
        ->and(WebsiteDraftRevision::query()->count())->toBe(1)
        ->and($site->fresh()->writing_authority->value)->toBe('core_live');
});

test('a failed later publication leaves the previous current publication in place', function (): void {
    publicationCore();
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $site = importedPublicationSite('rollback-shop', 'rollback.test', $admin);
    $first = app(PublishWebsiteAction::class)->execute($site, 1, $admin);

    $draft = $site->draft->fresh();
    $draft->update([
        'document' => array_merge($draft->document, ['headline' => 'Changed after publish']),
    ]);

    expect(fn () => app(PublishWebsiteAction::class)->execute($site->fresh(), 2, $admin))
        ->toThrow(WebsiteDraftDivergesFromCoreException::class);

    expect($first->fresh()->is_current)->toBeTrue()
        ->and(WebsitePublication::query()->where('is_current', true)->count())->toBe(1)
        ->and(WebsiteDraftRevision::query()->where('action', WebsiteDraftRevisionAction::Publish)->count())->toBe(1)
        ->and($site->fresh()->writing_authority->value)->toBe('core_live');
});

test('publishing one shop does not publish another shop', function (): void {
    publicationCore();
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $shopA = importedPublicationSite('tenant-a', 'tenant-a.test', $admin);
    $shopB = importedPublicationSite('tenant-b', 'tenant-b.test', $admin);

    app(PublishWebsiteAction::class)->execute($shopA, 1, $admin);

    expect(WebsitePublication::query()->where('website_site_id', $shopA->id)->where('is_current', true)->count())->toBe(1)
        ->and(WebsitePublication::query()->where('website_site_id', $shopB->id)->count())->toBe(0)
        ->and($shopB->fresh()->writing_authority->value)->toBe('core_live');
});

test('the database rejects a second current publication and a repeated version', function (): void {
    publicationCore();
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $site = importedPublicationSite('constraint-shop', 'constraint.test', $admin);
    $publication = app(PublishWebsiteAction::class)->execute($site, 1, $admin);

    WebsitePublication::query()->create([
        'website_site_id' => $site->id,
        'version' => 2,
        'document' => $publication->document,
        'content_hash' => $publication->content_hash,
        'is_current' => false,
        'published_at' => now(),
    ]);

    expect(fn () => WebsitePublication::query()->create([
        'website_site_id' => $site->id,
        'version' => 3,
        'document' => $publication->document,
        'content_hash' => $publication->content_hash,
        'is_current' => true,
        'published_at' => now(),
    ]))->toThrow(QueryException::class);

    expect(fn () => WebsitePublication::query()->create([
        'website_site_id' => $site->id,
        'version' => 1,
        'document' => $publication->document,
        'content_hash' => $publication->content_hash,
        'is_current' => false,
        'published_at' => now(),
    ]))->toThrow(QueryException::class);

    expect(WebsitePublication::query()->where('website_site_id', $site->id)->where('is_current', true)->count())->toBe(1)
        ->and(WebsitePublication::query()->where('website_site_id', $site->id)->count())->toBe(2);
});

test('a later publication keeps the earlier version and only one current row', function (): void {
    publicationCore();
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $site = importedPublicationSite('second-publish', 'second-publish.test', $admin);
    $first = app(PublishWebsiteAction::class)->execute($site, 1, $admin);
    $second = app(PublishWebsiteAction::class)->execute($site->fresh(), 2, $admin);

    expect($first->fresh()->is_current)->toBeFalse()
        ->and($second->is_current)->toBeTrue()
        ->and($second->version)->toBe(2)
        ->and(WebsitePublication::query()->where('website_site_id', $site->id)->where('is_current', true)->count())->toBe(1)
        ->and(WebsitePublication::query()->where('website_site_id', $site->id)->count())->toBe(2)
        ->and($site->fresh()->writing_authority->value)->toBe('core_live')
        ->and($site->draft?->fresh()->revision)->toBe(3);
});

test('public hosts are unique and empty hosts are allowed more than once', function (): void {
    publicationCore();
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    importedPublicationSite('host-a', 'shared.test', $admin);

    expect(fn () => importedPublicationSite('host-b', 'shared.test', $admin))
        ->toThrow(QueryException::class);

    importedPublicationSite('null-a', null, $admin);
    importedPublicationSite('null-b', null, $admin);

    expect(WebsiteSite::query()->whereNull('public_host')->count())->toBe(2)
        ->and(WebsiteSite::query()->where('public_host', 'shared.test')->count())->toBe(1);
});

test('authority switches only when the resolver release and the live core hash still match', function (): void {
    $document = publicationCore();
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $site = importedPublicationSite('switch-shop', 'switch.test', $admin);
    $other = importedPublicationSite('switch-other', 'switch-other.test', $admin);
    $switch = app(SwitchWebsiteWritingAuthorityAction::class);

    expect(fn () => $switch->execute($site, $admin, 'sha256:not-the-resolver'))
        ->toThrow(WebsiteAuthoritySwitchException::class, 'not running the publication resolver');

    expect($site->fresh()->writing_authority->value)->toBe('core_live');

    expect(fn () => $switch->execute($site, $admin, FoundryPublicationResolverRelease::IMAGE_DIGEST))
        ->toThrow(WebsiteAuthoritySwitchException::class, 'one current publication');

    app(PublishWebsiteAction::class)->execute($site, 1, $admin);

    publicationCore(['headline' => 'Edited after publish', 'positioning_lede' => $document['positioning_lede']]);

    expect(fn () => $switch->execute($site->fresh(), $admin, FoundryPublicationResolverRelease::IMAGE_DIGEST))
        ->toThrow(WebsiteAuthoritySwitchException::class, 'does not match the live website');

    expect($site->fresh()->writing_authority->value)->toBe('core_live')
        ->and(WebsitePublication::query()->where('is_current', true)->count())->toBe(1);

    publicationCore(['headline' => $document['headline'], 'positioning_lede' => $document['positioning_lede']]);

    $publication = WebsitePublication::query()->where('is_current', true)->first();
    $publication->update(['content_hash' => '0000000000000000']);

    expect(fn () => $switch->execute($site->fresh(), $admin, FoundryPublicationResolverRelease::IMAGE_DIGEST))
        ->toThrow(WebsiteAuthoritySwitchException::class, 'does not match the live website');

    $publication->update(['content_hash' => WebsiteDocumentHash::hash($document)]);

    $switched = $switch->execute($site->fresh(), $admin, FoundryPublicationResolverRelease::IMAGE_DIGEST);

    expect($switched->writing_authority->value)->toBe('platform')
        ->and($other->fresh()->writing_authority->value)->toBe('core_live')
        ->and(CoreWebsiteDocument::read()['hash'])->toBe(WebsiteDocumentHash::hash($document))
        ->and(WebsiteDraftRevision::query()->where('action', WebsiteDraftRevisionAction::SwitchAuthority)->count())->toBe(1);
});
