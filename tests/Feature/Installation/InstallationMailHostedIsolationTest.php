<?php

use App\Ark\Installation\Mail\CustomerOutboundMail;
use App\Ark\Installation\Mail\InstallationMailConfiguration;
use App\Ark\Mail\OutboundTransactionalMail;
use App\Ark\Mail\TransactionalMailOperation;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkCapability;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Ark\Runtime\HostedRuntime;
use App\Models\User;

function withHostedCatalog(callable $callback): mixed
{
    $catalog = tempnam(sys_get_temp_dir(), 'ark-hosted-tenants-');
    file_put_contents($catalog, '{}');
    $previous = getenv('ARK_HOSTED_TENANTS');
    putenv('ARK_HOSTED_TENANTS='.$catalog);
    $_ENV['ARK_HOSTED_TENANTS'] = $catalog;

    try {
        expect(HostedRuntime::isHostedProduct())->toBeTrue();

        return $callback();
    } finally {
        if ($previous === false) {
            putenv('ARK_HOSTED_TENANTS');
            unset($_ENV['ARK_HOSTED_TENANTS']);
        } else {
            putenv('ARK_HOSTED_TENANTS='.$previous);
            $_ENV['ARK_HOSTED_TENANTS'] = $previous;
        }
        @unlink($catalog);
    }
}

it('returns 404 for installation mail routes on hosted products', function () {
    withHostedCatalog(function () {
        $admin = User::factory()->create()
            ->assignRole(ArkRole::Admin->value)
            ->givePermissionTo(ArkCapability::SettingsManage->value);

        $this->actingAs($admin)
            ->patch(route('operations.settings.shop.installation-mail.update'), [
                'installation_mail_mode' => InstallationMailConfiguration::MODE_SHOP,
                'installation_mail_mailer' => 'smtp',
                'installation_mail_host' => '127.0.0.1',
                'installation_mail_port' => 587,
                'installation_mail_from_address' => 'shop@example.test',
            ])
            ->assertNotFound();

        $this->actingAs($admin)
            ->post(route('operations.settings.shop.installation-mail.test'), [
                'test_recipient' => 'admin@example.test',
            ])
            ->assertNotFound();
    });
});

it('never falls back to installation smtp on hosted even with env and shop settings present', function () {
    withHostedCatalog(function () {
        ShopSettings::current()->persistTrusted([
            'installation_mail_mode' => InstallationMailConfiguration::MODE_SHOP,
            'installation_mail_enabled' => true,
            'installation_mail_mailer' => 'smtp',
            'installation_mail_host' => '127.0.0.1',
            'installation_mail_port' => 587,
            'installation_mail_from_address' => 'shop@example.test',
            'cloud_status' => 'disconnected',
            'ark_mail_from_email' => null,
        ]);

        config([
            'mail.default' => 'smtp',
            'mail.from.address' => 'env@example.test',
            'mail.mailers.smtp.host' => '127.0.0.1',
            'mail.mailers.smtp.port' => 587,
        ]);

        expect(app(InstallationMailConfiguration::class)->allowsCustomerDelivery())->toBeFalse()
            ->and(app(OutboundTransactionalMail::class)->providerMode())->toBe('none')
            ->and(app(CustomerOutboundMail::class)->canSend())->toBeFalse();

        $result = app(OutboundTransactionalMail::class)->sendIntent(
            TransactionalMailOperation::EstimateSend,
            'customer@example.test',
            ['shop_name' => 'Demo'],
            'idem-hosted-1',
        );

        expect($result->ok())->toBeFalse()
            ->and($result->status)->toBe(\App\Ark\Mail\TransactionalMailResult::STATUS_NOT_CONFIGURED);
    });
});
