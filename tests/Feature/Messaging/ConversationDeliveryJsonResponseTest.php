<?php

use App\Ark\Operations\Messaging\ConversationDeliveryJsonResponse;

it('returns a successful payload when platform send recorded no core message', function () {
    $response = app(ConversationDeliveryJsonResponse::class)->make([null], [
        'estimate_url' => 'https://portal.test/estimates/token',
        'token_reused' => false,
    ]);

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true)['deliveries'])->toBe([])
        ->and($response->getData(true)['estimate_url'])->toBe('https://portal.test/estimates/token')
        ->and($response->getData(true)['message_id'])->toBeNull();
});
