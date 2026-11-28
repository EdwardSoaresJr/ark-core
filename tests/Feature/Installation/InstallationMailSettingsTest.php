<?php

use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkCapability;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

it('saves installation smtp settings through the admin form', function () {
    $admin = User::factory()->create()
        ->assignRole(ArkRole::Admin->value)
        ->givePermissionTo(ArkCapability::SettingsManage->value);

    $response = $this->actingAs($admin)->patch(route('operations.settings.shop.installation-mail.update'), [
        'installation_mail_enabled' => '1',
        'installation_mail_mailer' => 'smtp',
        'installation_mail_host' => 'smtp.example.test',
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

    $settings = ShopSettings::current()->fresh();

    expect((bool) $settings->installation_mail_enabled)->toBeTrue()
        ->and($settings->installation_mail_host)->toBe('smtp.example.test')
        ->and((int) $settings->installation_mail_port)->toBe(587)
        ->and($settings->installation_mail_from_address)->toBe('shop@example.test')
        ->and($settings->installation_mail_password)->toBe('secret-pass');

    if (Schema::hasTable('installation_mail_audit_events')) {
        $this->assertDatabaseHas('installation_mail_audit_events', [
            'action' => 'updated',
            'actor_id' => $admin->id,
        ]);
    }
});

it('keeps the existing password unless replace is checked', function () {
    ShopSettings::current()->persistTrusted([
        'installation_mail_enabled' => true,
        'installation_mail_mailer' => 'smtp',
        'installation_mail_host' => 'smtp.example.test',
        'installation_mail_port' => 587,
        'installation_mail_from_address' => 'shop@example.test',
        'installation_mail_password' => 'keep-me',
        'installation_mail_password_set_at' => now(),
    ]);

    $admin = User::factory()->create()
        ->assignRole(ArkRole::Admin->value)
        ->givePermissionTo(ArkCapability::SettingsManage->value);

    $this->actingAs($admin)->patch(route('operations.settings.shop.installation-mail.update'), [
        'installation_mail_enabled' => '1',
        'installation_mail_mailer' => 'smtp',
        'installation_mail_host' => 'smtp.example.test',
        'installation_mail_port' => 587,
        'installation_mail_encryption' => 'tls',
        'installation_mail_from_address' => 'shop@example.test',
        'installation_mail_password' => 'ignored-without-replace',
    ])->assertRedirect(route('operations.settings.shop.edit', ['section' => 'email']));

    expect(ShopSettings::current()->fresh()->installation_mail_password)->toBe('keep-me');
});

it('rejects metadata hosts from the admin form', function () {
    $admin = User::factory()->create()
        ->assignRole(ArkRole::Admin->value)
        ->givePermissionTo(ArkCapability::SettingsManage->value);

    $response = $this->actingAs($admin)->from(route('operations.settings.shop.edit', ['section' => 'email']))
        ->patch(route('operations.settings.shop.installation-mail.update'), [
            'installation_mail_enabled' => '1',
            'installation_mail_mailer' => 'smtp',
            'installation_mail_host' => '169.254.169.254',
            'installation_mail_port' => 587,
            'installation_mail_encryption' => 'tls',
            'installation_mail_from_address' => 'shop@example.test',
        ]);

    $response->assertRedirect(route('operations.settings.shop.edit', ['section' => 'email']));
    $response->assertSessionHasErrors('installation_mail_host');
});
