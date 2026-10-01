<?php

use App\Ark\Install\InstallationIdentity;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Platform\PlatformConnection;
use App\Ark\Platform\Shop;
use App\Ark\Platform\ShopStatus;
use App\Ark\Platform\Website\WebsiteDraft;
use App\Ark\Platform\Website\WebsiteDraftRevision;
use App\Ark\Platform\Website\WebsitePublication;
use App\Ark\Platform\Website\WebsiteSite;
use App\Ark\Platform\Website\WebsiteWritingAuthority;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

function connectWebsiteReadInstallation(): string
{
    $installationUuid = (string) Str::uuid();
    InstallationIdentity::write($installationUuid);

    ShopSettings::current()->persistTrusted([
        'platform_status' => 'connected',
        'platform_credential' => 'website-read-credential',
        'platform_base_url' => 'https://cloud.test',
        'platform_shop_public_id' => (string) Str::uuid(),
    ]);
    ShopSettings::forgetCurrent();

    return $installationUuid;
}

function signedWebsiteRead(
    string $host,
    ?string $installationId = null,
    ?string $credential = null,
    ?string $signatureOverride = null,
    ?string $nonce = null,
    ?string $timestamp = null,
): TestResponse {
    $path = '/webhooks/cloud/website/'.$host;
    $installationId ??= InstallationIdentity::uuid();
    $credential ??= (string) PlatformConnection::current()->credential();
    $timestamp ??= (string) time();
    $nonce ??= Str::random(24);
    $signature = $signatureOverride ?? hash_hmac('sha256', implode("\n", [
        $timestamp,
        $nonce,
        'GET',
        $path,
        hash('sha256', ''),
    ]), $credential);

    return test()->call(
        'GET',
        $path,
        [],
        [],
        [],
        [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_ARK_INSTALLATION_ID' => $installationId,
            'HTTP_X_ARK_TIMESTAMP' => $timestamp,
            'HTTP_X_ARK_NONCE' => $nonce,
            'HTTP_X_ARK_SIGNATURE' => $signature,
        ],
    );
}

function websiteReadFixture(string $host = 'fixture.example'): WebsiteSite
{
    $shop = Shop::query()->create([
        'uuid' => (string) Str::uuid(),
        'slug' => 'fixture-'.Str::lower(Str::random(8)),
        'display_name' => 'Fixture Shop',
        'status' => ShopStatus::Active,
    ]);

    $site = WebsiteSite::query()->create([
        'platform_shop_id' => $shop->id,
        'public_host' => $host,
        'writing_authority' => WebsiteWritingAuthority::Platform,
        'acknowledged_core_hash' => 'fixturehash000001',
        'management_enabled' => true,
    ]);

    $document = [
        'headline' => 'Fixture headline',
        'positioning_lede' => 'Fixture lede',
    ];

    $draft = WebsiteDraft::query()->create([
        'website_site_id' => $site->id,
        'document' => $document,
        'revision' => 8,
    ]);

    WebsiteDraftRevision::query()->create([
        'website_draft_id' => $draft->id,
        'revision' => 8,
        'document' => $document,
        'action' => 'edit',
        'core_hash' => 'fixturehash000001',
        'created_at' => now(),
    ]);

    WebsitePublication::query()->create([
        'website_site_id' => $site->id,
        'version' => 1,
        'document' => $document,
        'content_hash' => '6bcaa97a391162ae',
        'is_current' => true,
        'published_at' => '2026-09-18 23:14:23',
        'source_draft_revision' => 6,
    ]);

    return $site;
}

test('signed read returns this installation website state by public host', function (): void {
    connectWebsiteReadInstallation();
    $site = websiteReadFixture();
    $otherShop = Shop::query()->create([
        'uuid' => (string) Str::uuid(),
        'slug' => 'other-fixture',
        'display_name' => 'Other',
        'status' => ShopStatus::Active,
    ]);
    $other = WebsiteSite::query()->create([
        'platform_shop_id' => $otherShop->id,
        'public_host' => 'other.example',
        'writing_authority' => WebsiteWritingAuthority::CoreLive,
        'management_enabled' => true,
    ]);
    WebsiteDraft::query()->create([
        'website_site_id' => $other->id,
        'document' => ['headline' => 'Other headline'],
        'revision' => 1,
    ]);

    $response = signedWebsiteRead('fixture.example')
        ->assertOk()
        ->assertJsonPath('public_host', 'fixture.example')
        ->assertJsonPath('writing_authority', 'platform')
        ->assertJsonPath('draft.revision', 8)
        ->assertJsonPath('draft.document.headline', 'Fixture headline')
        ->assertJsonPath('publication.version', 1)
        ->assertJsonPath('publication.content_hash', '6bcaa97a391162ae')
        ->assertJsonPath('revisions.0.revision', 8)
        ->assertJsonPath('revisions.0.action', 'edit');

    expect($response->json('publication.published_at'))->not->toBeEmpty()
        ->and($response->json())->not->toHaveKey('platform_shop_id')
        ->and($response->json('draft.document.headline'))->not->toBe('Other headline')
        ->and($site->platform_shop_id)->not->toBe($other->platform_shop_id);
});

test('signed read does not modify drafts publications authority or history', function (): void {
    connectWebsiteReadInstallation();
    $site = websiteReadFixture();
    $before = [
        'authority' => $site->writing_authority->value,
        'revision' => $site->draft->revision,
        'document' => $site->draft->document,
        'drafts' => WebsiteDraft::query()->count(),
        'revisions' => WebsiteDraftRevision::query()->count(),
        'publications' => WebsitePublication::query()->count(),
        'hash' => WebsitePublication::query()->value('content_hash'),
        'draft_updated_at' => $site->draft->updated_at?->toIso8601String(),
    ];

    signedWebsiteRead('fixture.example')->assertOk();

    $site->refresh();

    expect($site->writing_authority->value)->toBe($before['authority'])
        ->and($site->draft->revision)->toBe($before['revision'])
        ->and($site->draft->document)->toBe($before['document'])
        ->and($site->draft->updated_at?->toIso8601String())->toBe($before['draft_updated_at'])
        ->and(WebsiteDraft::query()->count())->toBe($before['drafts'])
        ->and(WebsiteDraftRevision::query()->count())->toBe($before['revisions'])
        ->and(WebsitePublication::query()->count())->toBe($before['publications'])
        ->and(WebsitePublication::query()->value('content_hash'))->toBe($before['hash']);
});

test('unknown host is rejected without using platform shop id', function (): void {
    connectWebsiteReadInstallation();
    $site = websiteReadFixture();

    signedWebsiteRead('missing.example')
        ->assertNotFound();

    expect(WebsiteSite::query()->whereKey($site->id)->value('public_host'))->toBe('fixture.example')
        ->and(WebsiteDraftRevision::query()->count())->toBe(1);
});

test('invalid signature and a different installation are rejected', function (): void {
    $installation = connectWebsiteReadInstallation();
    websiteReadFixture();

    signedWebsiteRead('fixture.example', signatureOverride: 'not-a-valid-signature')
        ->assertUnauthorized();

    signedWebsiteRead('fixture.example', installationId: (string) Str::uuid())
        ->assertUnauthorized();

    signedWebsiteRead('fixture.example', credential: 'other-installation-credential')
        ->assertUnauthorized();

    expect(InstallationIdentity::uuid())->toBe($installation)
        ->and(WebsiteDraft::query()->value('revision'))->toBe(8);
});

test('a repeated nonce is rejected and does not change the draft', function (): void {
    connectWebsiteReadInstallation();
    websiteReadFixture();
    $nonce = 'replay-nonce-fixed-value';

    signedWebsiteRead('fixture.example', nonce: $nonce)->assertOk();
    signedWebsiteRead('fixture.example', nonce: $nonce)->assertUnauthorized();

    expect(WebsiteDraft::query()->value('revision'))->toBe(8)
        ->and(WebsiteDraftRevision::query()->count())->toBe(1);
});

test('an expired timestamp is rejected', function (): void {
    connectWebsiteReadInstallation();
    websiteReadFixture();

    signedWebsiteRead('fixture.example', timestamp: '1000')->assertUnauthorized();

    expect(WebsiteDraft::query()->value('revision'))->toBe(8);
});

test('duplicate host or current publication is rejected', function (): void {
    connectWebsiteReadInstallation();
    $site = websiteReadFixture();

    \Illuminate\Support\Facades\DB::statement('DROP INDEX IF EXISTS website_sites_public_host_unique');
    $shop = Shop::query()->create([
        'uuid' => (string) Str::uuid(),
        'slug' => 'dup-host',
        'display_name' => 'Dup',
        'status' => ShopStatus::Active,
    ]);
    WebsiteSite::query()->create([
        'platform_shop_id' => $shop->id,
        'public_host' => 'fixture.example',
        'writing_authority' => WebsiteWritingAuthority::Platform,
        'management_enabled' => true,
    ]);

    signedWebsiteRead('fixture.example')->assertStatus(409);

    WebsiteSite::query()->whereKeyNot($site->id)->delete();
    \Illuminate\Support\Facades\DB::statement('DROP INDEX IF EXISTS website_pub_one_current');
    WebsitePublication::query()->create([
        'website_site_id' => $site->id,
        'version' => 2,
        'document' => ['headline' => 'Second'],
        'content_hash' => 'secondhash',
        'is_current' => true,
        'published_at' => now(),
        'source_draft_revision' => 8,
    ]);

    signedWebsiteRead('fixture.example')->assertStatus(409);
    expect(WebsiteDraft::query()->value('revision'))->toBe(8);
});
