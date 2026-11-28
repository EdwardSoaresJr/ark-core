<?php

use App\Ark\Installation\Mail\InstallationMailConfiguration;
use App\Ark\Installation\Mail\InstallationMailSender;
use App\Ark\Mail\TransactionalMailResult;
use App\Ark\Operations\Settings\ShopSettings;

it('returns not configured when no installation mail is ready', function () {
    ShopSettings::current()->persistTrusted([
        'installation_mail_enabled' => false,
    ]);
    config(['mail.default' => 'array']);

    $result = app(InstallationMailSender::class)->send(
        'installation.mail.test',
        'owner@example.test',
        ['shop_name' => 'Demo'],
    );

    expect($result->status)->toBe(TransactionalMailResult::STATUS_NOT_CONFIGURED)
        ->and($result->ok())->toBeFalse()
        ->and($result->confirmedDelivered())->toBeFalse();
});

it('reports transport acceptance distinctly from confirmed delivery', function () {
    $result = TransactionalMailResult::transportAccepted('corr-1', ['source' => 'shop_settings']);

    expect($result->ok())->toBeTrue()
        ->and($result->confirmedDelivered())->toBeFalse()
        ->and($result->operatorMessage())->toContain('not confirmed');
});

it('exposes active source for the admin UI', function () {
    ShopSettings::current()->persistTrusted([
        'installation_mail_enabled' => true,
        'installation_mail_mailer' => 'smtp',
        'installation_mail_host' => 'smtp.example.test',
        'installation_mail_port' => 587,
        'installation_mail_from_address' => 'shop@example.test',
    ]);

    expect(app(InstallationMailConfiguration::class)->formDefaults()['active_source'])
        ->toBe(InstallationMailConfiguration::SOURCE_SHOP);
});
