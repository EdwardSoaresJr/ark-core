<?php


use App\Ark\Operations\Payments\Contracts\SquarePaymentsClient;
use App\Ark\Operations\Payments\FakeSquarePaymentsClient;
use App\Ark\Operations\Payments\SquareAdapterMissingClient;
use App\Ark\Operations\Payments\SquareSdk;

test('default core does not ship the optional Square adapter package', function () {
    expect(SquareSdk::adapterPackagePresent())->toBeFalse()
        ->and(SquareSdk::adapterPackageHint())->toContain('ark/payments-square');
});

test('core binds Fake Square client in testing', function () {
    expect(app(SquarePaymentsClient::class))->toBeInstanceOf(FakeSquarePaymentsClient::class);
});

test('SquareAdapterMissingClient explains how to install the adapter', function () {
    $client = new SquareAdapterMissingClient;

    expect(fn () => $client->getPayment('pay_test'))
        ->toThrow(RuntimeException::class, 'ark/payments-square');
});
