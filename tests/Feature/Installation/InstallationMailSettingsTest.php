<?php

use App\Ark\Installation\Mail\InstallationMailConfiguration;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkCapability;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

it('saves installation settings mode through the admin form', function () {
    $admin = User::factory()->create()
        ->assignRole(ArkRole::Admin->value)
        ->givePermissionTo(ArkCapability::SettingsManage->value);

    $response = $this->actingAs($admin)->patch(route('operations.settings.shop.installation-mail.update'), [
        'installation_mail_mode' => InstallationMailConfiguration::MODE_SHOP,
        'installation_mail_mailer' => 'smtp',
        'installation_mail_host' => '127.0.0.1',
        'installation_mail_port' => 587,
        'installation_mail_encryption' => 'tls',
        'installation_mail_username' => 'mail-user',
        'replace_password' => '1',
        'installation_mail_password' => 'secret-pass',
        'installation_mail_from_address' => 'shop@example.test',
        'installation_mail_from_name' => 'Demo Shop',
        'installation_mail_reply_to' => 'reply@example.test',
    ]);

    $response->assertRedirect(route('operations.settings.shop.edit', ['section' => 'email']));
    $response->assertSessionMissing('installation_mail_password');

    $settings = ShopSettings::current()->fresh();

    expect($settings->installation_mail_mode)->toBe(InstallationMailConfiguration::MODE_SHOP)
        ->and((bool) $settings->installation_mail_enabled)->toBeTrue()
        ->and($settings->installation_mail_host)->toBe('127.0.0.1')
        ->and((int) $settings->installation_mail_port)->toBe(587)
        ->and($settings->installation_mail_from_address)->toBe('shop@example.test')
        ->and($settings->installation_mail_password)->toBe('secret-pass');

    if (Schema::hasTable('installation_mail_audit_events')) {
        $this->assertDatabaseHas('installation_mail_audit_events', [
            'action' => 'mode_shop_settings',
            'actor_id' => $admin->id,
        ]);
        expect(\App\Ark\Installation\Mail\InstallationMailAuditEvent::query()->latest('id')->first()?->context)
            ->not->toHaveKey('password')
            ->not->toHaveKey('installation_mail_password');
    }
});

it('keeps the existing password unless replace is checked', function () {
    ShopSettings::current()->persistTrusted([
        'installation_mail_mode' => InstallationMailConfiguration::MODE_SHOP,
        'installation_mail_enabled' => true,
        'installation_mail_mailer' => 'smtp',
        'installation_mail_host' => '127.0.0.1',
        'installation_mail_port' => 587,
        'installation_mail_from_address' => 'shop@example.test',
        'installation_mail_password' => 'keep-me',
        'installation_mail_password_set_at' => now(),
    ]);

    $admin = User::factory()->create()
        ->assignRole(ArkRole::Admin->value)
        ->givePermissionTo(ArkCapability::SettingsManage->value);

    $this->actingAs($admin)->patch(route('operations.settings.shop.installation-mail.update'), [
        'installation_mail_mode' => InstallationMailConfiguration::MODE_SHOP,
        'installation_mail_mailer' => 'smtp',
        'installation_mail_host' => '127.0.0.1',
        'installation_mail_port' => 587,
        'installation_mail_encryption' => 'tls',
        'installation_mail_from_address' => 'shop@example.test',
        'installation_mail_password' => 'ignored-without-replace',
    ])->assertRedirect(route('operations.settings.shop.edit', ['section' => 'email']));

    expect(ShopSettings::current()->fresh()->installation_mail_password)->toBe('keep-me');
});

it('invalidates prior test status when connection settings change', function () {
    ShopSettings::current()->persistTrusted([
        'installation_mail_mode' => InstallationMailConfiguration::MODE_SHOP,
        'installation_mail_enabled' => true,
        'installation_mail_mailer' => 'smtp',
        'installation_mail_host' => '127.0.0.1',
        'installation_mail_port' => 587,
        'installation_mail_from_address' => 'shop@example.test',
        'installation_mail_last_tested_at' => now(),
        'installation_mail_last_test_status' => 'transport_accepted',
        'installation_mail_last_error' => null,
    ]);

    $admin = User::factory()->create()
        ->assignRole(ArkRole::Admin->value)
        ->givePermissionTo(ArkCapability::SettingsManage->value);

    $this->actingAs($admin)->patch(route('operations.settings.shop.installation-mail.update'), [
        'installation_mail_mode' => InstallationMailConfiguration::MODE_SHOP,
        'installation_mail_mailer' => 'smtp',
        'installation_mail_host' => '127.0.0.1',
        'installation_mail_port' => 465,
        'installation_mail_encryption' => 'ssl',
        'installation_mail_from_address' => 'shop@example.test',
    ])->assertRedirect();

    $settings = ShopSettings::current()->fresh();
    expect($settings->installation_mail_last_test_status)->toBeNull()
        ->and($settings->installation_mail_last_tested_at)->toBeNull();
});

it('rejects metadata hosts from the admin form', function () {
    $admin = User::factory()->create()
        ->assignRole(ArkRole::Admin->value)
        ->givePermissionTo(ArkCapability::SettingsManage->value);

    $response = $this->actingAs($admin)->from(route('operations.settings.shop.edit', ['section' => 'email']))
        ->patch(route('operations.settings.shop.installation-mail.update'), [
            'installation_mail_mode' => InstallationMailConfiguration::MODE_SHOP,
            'installation_mail_mailer' => 'smtp',
            'installation_mail_host' => '169.254.169.254',
            'installation_mail_port' => 587,
            'installation_mail_encryption' => 'tls',
            'installation_mail_from_address' => 'shop@example.test',
            'installation_mail_password' => 'should-not-flash',
        ]);

    $response->assertRedirect(route('operations.settings.shop.edit', ['section' => 'email']));
    $response->assertSessionHasErrors('installation_mail_host');
    $response->assertSessionMissing('installation_mail_password');
    expect(session()->getOldInput('installation_mail_password'))->toBeNull();
});
