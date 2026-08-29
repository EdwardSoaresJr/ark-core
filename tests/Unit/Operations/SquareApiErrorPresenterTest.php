<?php

use App\Ark\Operations\Payments\SquareApiErrorPresenter;
use Square\Exceptions\SquareApiException;

/**
 * Runs only when ark/payments-square is installed (Square SDK present).
 */
test('square api error presenter translates unauthorized terminal device errors', function () {
    if (! class_exists(SquareApiErrorPresenter::class) || ! class_exists(SquareApiException::class)) {
        $this->markTestSkipped('Optional ark/payments-square adapter is not installed.');
    }

    $exception = new SquareApiException(
        'API request failed',
        400,
        ['errors' => [[
            'code' => 'BAD_REQUEST',
            'detail' => 'Merchant not authorized for device_id=549CS149C4000527',
            'category' => 'INVALID_REQUEST_ERROR',
        ]]],
    );

    $message = app(SquareApiErrorPresenter::class)->messageFor($exception);

    expect($message)->toContain('not authorized for this merchant account')
        ->and($message)->toContain('Settings → Payments');
});
