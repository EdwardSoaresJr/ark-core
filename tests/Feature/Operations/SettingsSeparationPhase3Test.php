<?php

use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Workstations\WorkstationPresence;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    session([WorkstationPresence::SESSION_BIND_DISMISSED => true]);

    $this->admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
});

test('settings no longer exposes Box pairing as a Core configuration surface', function (): void {
    $this->actingAs($this->admin)
        ->get(route('operations.settings.shop.edit', ['section' => 'ark-cloud']))
        ->assertRedirect(route('operations.settings.shop.edit', ['section' => 'general']));

    $this->actingAs($this->admin)
        ->get(route('operations.settings.shop.edit'))
        ->assertOk()
        ->assertDontSee('Connect ARK Platform', false)
        ->assertDontSee('action="'.route('operations.settings.shop.ark-cloud.connect').'"', false)
        ->assertDontSee('action="'.route('operations.settings.shop.ark-cloud.connect-manual').'"', false)
        ->assertDontSee("@click=\"setActive('ark-cloud')\"", false);
});

test('standalone communications settings are refused; hosted email still has no separate mail pairing controls', function (): void {
    $this->actingAs($this->admin)
        ->get(route('operations.settings.shop.edit', [
            'section' => 'communications',
            'communications-tab' => 'email',
        ]))
        ->assertRedirect(route('operations.settings.shop.edit', ['section' => 'general']));

    $catalog = enableHostedTenantCatalog();
    fakePlatformVoiceStatus('not_enabled');

    try {
        $this->actingAs($this->admin)
            ->get(route('operations.settings.shop.edit', [
                'section' => 'communications',
                'communications-tab' => 'email',
            ]))
            ->assertOk()
            ->assertSee('Customer email')
            ->assertDontSee('Connect ARK Email', false)
            ->assertDontSee('Disconnect ARK Email', false)
            ->assertDontSee('action="'.route('operations.settings.shop.email.ark-mail.enable').'"', false)
            ->assertDontSee('action="'.route('operations.settings.shop.email.ark-mail.disconnect').'"', false)
            ->assertDontSee('action="'.route('operations.settings.shop.email.ark-mail.claim').'"', false);

        $email = file_get_contents(resource_path('views/operations/settings/partials/communications-email-settings.blade.php'));

        expect($email)->not->toContain('ark-mail.enable')
            ->and($email)->not->toContain('Disconnect ARK Email');
    } finally {
        clearHostedTenantCatalog($catalog);
    }
});

test('connected platform connection storage remains intact when viewing shop settings', function (): void {
    expect(Schema::hasColumn('shop_settings', 'ark_mail_status'))->toBeTrue()
        ->and(Schema::hasColumn('shop_settings', 'cloud_status'))->toBeTrue()
        ->and(Schema::hasColumn('shop_settings', 'platform_status'))->toBeTrue();

    ShopSettings::current()->persistTrusted([
        'ark_mail_status' => 'connected',
        'ark_mail_credential' => 'mail-secret',
        'ark_mail_service_url' => 'https://mail.test',
        'ark_mail_tenant_public_id' => '11111111-1111-4111-8111-111111111111',
        'cloud_status' => 'connected',
        'cloud_credential' => 'cloud-secret',
        'cloud_base_url' => 'https://cloud.test',
        'cloud_shop_public_id' => '22222222-2222-4222-8222-222222222222',
        'platform_status' => 'connected',
        'platform_credential' => 'platform-secret',
        'platform_base_url' => 'https://platform.test',
        'platform_shop_public_id' => '33333333-3333-4333-8333-333333333333',
    ]);

    Http::fake([
        'platform.test/api/v1/status' => Http::response([
            'ok' => true,
            'services' => [
                ['key' => 'voice', 'label' => 'ARK Voice', 'status' => 'not_enabled', 'status_label' => 'Not enabled', 'detail' => null],
            ],
        ], 200),
    ]);

    $this->actingAs($this->admin)
        ->get(route('operations.settings.shop.edit', ['section' => 'general']))
        ->assertOk();

    $settings = ShopSettings::current()->fresh();

    expect($settings->ark_mail_status)->toBe('connected')
        ->and($settings->ark_mail_credential)->toBe('mail-secret')
        ->and($settings->ark_mail_service_url)->toBe('https://mail.test')
        ->and($settings->ark_mail_tenant_public_id)->toBe('11111111-1111-4111-8111-111111111111')
        ->and($settings->cloud_status)->toBe('connected')
        ->and($settings->cloud_credential)->toBe('cloud-secret')
        ->and($settings->cloud_base_url)->toBe('https://cloud.test')
        ->and($settings->cloud_shop_public_id)->toBe('22222222-2222-4222-8222-222222222222')
        ->and($settings->platform_status)->toBe('connected')
        ->and($settings->platform_credential)->toBe('platform-secret')
        ->and($settings->platform_base_url)->toBe('https://platform.test')
        ->and($settings->platform_shop_public_id)->toBe('33333333-3333-4333-8333-333333333333');
});

test('legacy email connection routes refuse Box pairing from Core', function (): void {
    expect(Route::has('operations.settings.shop.email.ark-mail.enable'))->toBeTrue()
        ->and(Route::has('operations.settings.shop.email.ark-mail.claim'))->toBeTrue()
        ->and(Route::has('operations.settings.shop.email.ark-mail.disconnect'))->toBeTrue();

    $this->actingAs($this->admin)
        ->post(route('operations.settings.shop.email.ark-mail.enable'))
        ->assertRedirect(route('operations.settings.shop.edit', ['section' => 'general']));
});
