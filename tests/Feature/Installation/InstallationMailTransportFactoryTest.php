<?php

use App\Ark\Installation\Mail\InstallationMailTransportFactory;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;

it('requires STARTTLS when encryption is tls and keeps certificate verification on', function () {
    $transport = app(InstallationMailTransportFactory::class)->make([
        'mailer' => 'smtp',
        'host' => '127.0.0.1',
        'port' => 587,
        'encryption' => 'tls',
        'username' => 'user',
        'password' => 'secret-pass',
    ]);

    expect($transport)->toBeInstanceOf(EsmtpTransport::class)
        ->and($transport->isTlsRequired())->toBeTrue()
        ->and($transport->isAutoTls())->toBeTrue();

    /** @var SocketStream $stream */
    $stream = $transport->getStream();
    $options = $stream->getStreamOptions();

    expect($options['ssl']['verify_peer'] ?? null)->toBeTrue()
        ->and($options['ssl']['verify_peer_name'] ?? null)->toBeTrue()
        ->and($options['ssl']['allow_self_signed'] ?? null)->toBeFalse();
});

it('uses implicit TLS for ssl encryption', function () {
    $transport = app(InstallationMailTransportFactory::class)->make([
        'mailer' => 'smtp',
        'host' => '127.0.0.1',
        'port' => 465,
        'encryption' => 'ssl',
    ]);

    expect($transport)->toBeInstanceOf(EsmtpTransport::class)
        ->and($transport->isTlsRequired())->toBeTrue();
});

it('disables auto TLS when encryption is none', function () {
    $transport = app(InstallationMailTransportFactory::class)->make([
        'mailer' => 'smtp',
        'host' => '127.0.0.1',
        'port' => 25,
        'encryption' => null,
    ]);

    expect($transport)->toBeInstanceOf(EsmtpTransport::class)
        ->and($transport->isAutoTls())->toBeFalse()
        ->and($transport->isTlsRequired())->toBeFalse();
});
