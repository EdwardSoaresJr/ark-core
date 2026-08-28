<?php

use App\Ark\Growth\Jobs\RunGrowthBusinessProfileBackfillJob;
use App\Ark\Growth\Settings\GrowthIntegrationSettings;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);

    $path = storage_path('app/private/firebase-mobile-service-account.json');
    if (is_file($path)) {
        unlink($path);
    }
});

function growthIntegrationsAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole(ArkRole::Admin->value);

    return $admin;
}

function sampleGoogleServiceAccountJson(): string
{
    return json_encode([
        'type' => 'service_account',
        'project_id' => 'lugsnplugs-growth',
        'private_key_id' => 'abc123',
        'private_key' => "-----BEGIN PRIVATE KEY-----\nMIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQC7\n-----END PRIVATE KEY-----\n",
        'client_email' => 'growth@lugsnplugs-growth.iam.gserviceaccount.com',
        'client_id' => '1234567890',
        'token_uri' => 'https://oauth2.googleapis.com/token',
    ], JSON_THROW_ON_ERROR);
}

it('saves public sitemap integration setting', function (): void {
    Queue::fake();

    $this->actingAs(growthIntegrationsAdmin())
        ->patch(route('growth.integrations.update'), [
            'public_sitemap_enabled' => '1',
            'google_business_profile_enabled' => '0',
            'google_business_profile_location' => '',
            'backfill_after_save' => '0',
            'google_search_console_enabled' => '0',
            'google_indexing_enabled' => '0',
            'seo_automation_enabled' => '1',
            'use_server_firebase_service_account' => '0',
        ])
        ->assertRedirect(route('growth.integrations.index'));

    $shop = ShopSettings::current()->fresh();

    expect($shop->growth_integrations['public_sitemap']['enabled'] ?? false)->toBeTrue();
});

it('renders growth integrations settings for admins', function (): void {
    $this->actingAs(growthIntegrationsAdmin())
        ->get(route('growth.integrations.index'))
        ->assertOk()
        ->assertSee('Google Business Profile')
        ->assertSee('Discover locations');
});

it('saves google business profile integration settings', function (): void {
    Queue::fake();

    $this->actingAs(growthIntegrationsAdmin())
        ->patch(route('growth.integrations.update'), [
            'public_sitemap_enabled' => '0',
            'google_business_profile_enabled' => '1',
            'google_business_profile_location' => 'locations/12345678901234567890',
            'growth_google_service_account_json' => sampleGoogleServiceAccountJson(),
            'backfill_after_save' => '0',
            'google_search_console_enabled' => '0',
            'google_indexing_enabled' => '0',
            'seo_automation_enabled' => '1',
            'use_server_firebase_service_account' => '0',
        ])
        ->assertRedirect(route('growth.integrations.index'))
        ->assertSessionHas('status', 'Growth integrations saved.');

    $shop = ShopSettings::current()->fresh();

    expect($shop->growth_integrations['google_business_profile']['enabled'] ?? false)->toBeTrue()
        ->and($shop->growth_integrations['google_business_profile']['location'] ?? null)
        ->toBe('locations/12345678901234567890')
        ->and($shop->growth_google_service_account)->not->toBeNull();

    Queue::assertNothingPushed();
});

it('queues backfill when requested after save', function (): void {
    Queue::fake();

    $this->actingAs(growthIntegrationsAdmin())
        ->patch(route('growth.integrations.update'), [
            'public_sitemap_enabled' => '0',
            'google_business_profile_enabled' => '1',
            'google_business_profile_location' => 'locations/12345678901234567890',
            'growth_google_service_account_json' => sampleGoogleServiceAccountJson(),
            'backfill_after_save' => '1',
            'google_search_console_enabled' => '0',
            'google_indexing_enabled' => '0',
            'seo_automation_enabled' => '1',
            'use_server_firebase_service_account' => '0',
        ])
        ->assertRedirect(route('growth.integrations.index'))
        ->assertSessionHas('status', 'Growth integrations saved. Business Profile backfill queued.');

    Queue::assertPushed(RunGrowthBusinessProfileBackfillJob::class);
});

it('falls back to the server firebase service account for growth credentials', function (): void {
    $path = storage_path('app/private/firebase-mobile-service-account.json');

    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), 0755, true);
    }

    file_put_contents($path, json_encode([
        'type' => 'service_account',
        'project_id' => 'lugsnplugs-ark-mobile',
        'private_key' => "-----BEGIN PRIVATE KEY-----\nTEST\n-----END PRIVATE KEY-----\n",
        'client_email' => 'firebase-adminsdk-fbsvc@lugsnplugs-ark-mobile.iam.gserviceaccount.com',
    ], JSON_THROW_ON_ERROR));

    $settings = GrowthIntegrationSettings::current();

    expect($settings->hasGoogleServiceAccountCredentials())->toBeTrue()
        ->and($settings->googleServiceAccountSource())->toBe('server_file')
        ->and($settings->googleServiceAccountCredentials()['client_email'] ?? null)
        ->toBe('firebase-adminsdk-fbsvc@lugsnplugs-ark-mobile.iam.gserviceaccount.com');
});

it('validates location and credentials when enabling google business profile', function (): void {
    $this->actingAs(growthIntegrationsAdmin())
        ->from(route('growth.integrations.index'))
        ->patch(route('growth.integrations.update'), [
            'google_business_profile_enabled' => '1',
            'google_business_profile_location' => '',
            'growth_google_service_account_json' => '',
            'use_server_firebase_service_account' => '0',
        ])
        ->assertRedirect(route('growth.integrations.index'))
        ->assertSessionHasErrors(['google_business_profile_location', 'growth_google_service_account_json']);
});

it('saves google business profile enabled when checkbox sends hidden and checked values', function (): void {
    Queue::fake();

    ShopSettings::current()->update([
        'growth_google_service_account' => sampleGoogleServiceAccountJson(),
    ]);

    $this->actingAs(growthIntegrationsAdmin())
        ->patch(route('growth.integrations.update'), [
            'public_sitemap_enabled' => '0',
            'google_business_profile_enabled' => ['0', '1'],
            'google_business_profile_location' => 'locations/98765432109876543210',
            'backfill_after_save' => '0',
            'google_search_console_enabled' => '0',
            'google_indexing_enabled' => '0',
            'seo_automation_enabled' => '1',
            'use_server_firebase_service_account' => '0',
        ])
        ->assertRedirect(route('growth.integrations.index'))
        ->assertSessionHas('status', 'Growth integrations saved.');

    $shop = ShopSettings::current()->fresh();

    expect($shop->growth_integrations['google_business_profile']['enabled'] ?? false)->toBeTrue()
        ->and($shop->growth_integrations['google_business_profile']['location'] ?? null)
        ->toBe('locations/98765432109876543210');
});

it('discovers google business profile locations using stored credentials', function (): void {
    ShopSettings::current()->update([
        'growth_google_service_account' => sampleGoogleServiceAccountJson(),
    ]);

    Cache::put('growth:google:gbp:access_token', 'test-token', now()->addHour());

    Http::fake([
        'mybusinessaccountmanagement.googleapis.com/v1/accounts' => Http::response([
            'accounts' => [
                ['name' => 'accounts/11111111111111111111'],
            ],
        ], 200),
        'mybusinessbusinessinformation.googleapis.com/v1/accounts/11111111111111111111/locations*' => Http::response([
            'locations' => [
                [
                    'name' => 'locations/98765432109876543210',
                    'title' => 'Lugs N Plugs Auto Care',
                    'storefrontAddress' => [
                        'addressLines' => ['123 Main St'],
                        'locality' => 'Colorado Springs',
                        'administrativeArea' => 'CO',
                        'postalCode' => '80909',
                    ],
                ],
            ],
        ], 200),
    ]);

    $this->actingAs(growthIntegrationsAdmin())
        ->postJson(route('growth.integrations.discover-locations'))
        ->assertOk()
        ->assertJsonPath('locations.0.id', 'locations/98765432109876543210')
        ->assertJsonPath('locations.0.title', 'Lugs N Plugs Auto Care')
        ->assertJsonPath('credentials_source', 'growth_settings');
});

it('discovers locations from pasted json before save', function (): void {
    Cache::put('growth:google:gbp:access_token', 'test-token', now()->addHour());

    Http::fake([
        'mybusinessaccountmanagement.googleapis.com/v1/accounts' => Http::response([
            'accounts' => [
                ['name' => 'accounts/11111111111111111111'],
            ],
        ], 200),
        'mybusinessbusinessinformation.googleapis.com/v1/accounts/11111111111111111111/locations*' => Http::response([
            'locations' => [
                [
                    'name' => 'locations/98765432109876543210',
                    'title' => 'Lugs N Plugs Auto Care',
                ],
            ],
        ], 200),
    ]);

    $this->actingAs(growthIntegrationsAdmin())
        ->postJson(route('growth.integrations.discover-locations'), [
            'growth_google_service_account_json' => sampleGoogleServiceAccountJson(),
        ])
        ->assertOk()
        ->assertJsonPath('credentials_source', 'request');

    expect(ShopSettings::current()->fresh()->growth_google_service_account)->toBeNull();
});

it('returns a friendly message when google business profile apis are not enabled', function (): void {
    ShopSettings::current()->update([
        'growth_google_service_account' => sampleGoogleServiceAccountJson(),
    ]);

    Cache::put('growth:google:gbp:access_token', 'test-token', now()->addHour());

    Http::fake([
        'mybusinessaccountmanagement.googleapis.com/v1/accounts' => Http::response([
            'error' => [
                'code' => 429,
                'message' => 'Quota exceeded for quota metric \'Requests\' and limit \'Requests per minute\' of service \'mybusinessaccountmanagement.googleapis.com\' for consumer \'project_number:569985323501\'.',
                'status' => 'RESOURCE_EXHAUSTED',
                'details' => [
                    [
                        '@type' => 'type.googleapis.com/google.rpc.ErrorInfo',
                        'reason' => 'RATE_LIMIT_EXCEEDED',
                        'domain' => 'googleapis.com',
                        'metadata' => [
                            'quota_limit_value' => '0',
                            'service' => 'mybusinessaccountmanagement.googleapis.com',
                        ],
                    ],
                ],
            ],
        ], 429),
    ]);

    $this->actingAs(growthIntegrationsAdmin())
        ->postJson(route('growth.integrations.discover-locations'))
        ->assertUnprocessable()
        ->assertJsonPath('message', fn (string $message) => str_contains($message, 'My Business Account Management API is not enabled')
            && str_contains($message, 'allowlisted'));
});

it('switches growth google credentials to the server firebase account', function (): void {
    Queue::fake();

    $path = storage_path('app/private/firebase-mobile-service-account.json');
    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), 0755, true);
    }

    file_put_contents($path, json_encode([
        'type' => 'service_account',
        'project_id' => 'lugsnplugs-ark-mobile',
        'private_key' => "-----BEGIN PRIVATE KEY-----\nTEST\n-----END PRIVATE KEY-----\n",
        'client_email' => 'firebase-adminsdk-fbsvc@lugsnplugs-ark-mobile.iam.gserviceaccount.com',
    ], JSON_THROW_ON_ERROR));

    ShopSettings::current()->update([
        'growth_google_service_account' => json_encode([
            'type' => 'service_account',
            'project_id' => 'ark-sms',
            'private_key' => "-----BEGIN PRIVATE KEY-----\nTEST\n-----END PRIVATE KEY-----\n",
            'client_email' => 'google-analytics@ark-sms.iam.gserviceaccount.com',
        ], JSON_THROW_ON_ERROR),
    ]);

    $this->actingAs(growthIntegrationsAdmin())
        ->patch(route('growth.integrations.update'), [
            'public_sitemap_enabled' => '0',
            'google_business_profile_enabled' => '0',
            'google_business_profile_location' => '',
            'backfill_after_save' => '0',
            'google_search_console_enabled' => '0',
            'google_indexing_enabled' => '0',
            'seo_automation_enabled' => '1',
            'use_server_firebase_service_account' => '1',
        ])
        ->assertRedirect(route('growth.integrations.index'));

    $settings = GrowthIntegrationSettings::current();

    expect(ShopSettings::current()->fresh()->growth_google_service_account)->toBeNull()
        ->and($settings->googleServiceAccountSource())->toBe('server_file')
        ->and($settings->googleServiceAccountCredentials()['client_email'] ?? null)
        ->toBe('firebase-adminsdk-fbsvc@lugsnplugs-ark-mobile.iam.gserviceaccount.com');
});

it('shows saved credentials status without re-displaying json', function (): void {
    ShopSettings::current()->update([
        'growth_google_service_account' => sampleGoogleServiceAccountJson(),
    ]);

    $this->actingAs(growthIntegrationsAdmin())
        ->get(route('growth.integrations.index'))
        ->assertOk()
        ->assertSee('Saved on server.')
        ->assertSee('growth@lugsnplugs-growth.iam.gserviceaccount.com');
});
