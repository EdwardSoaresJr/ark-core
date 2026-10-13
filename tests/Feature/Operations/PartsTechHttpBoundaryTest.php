<?php

use App\Ark\Operations\Parts\PartsTechHttpClient;
use App\Ark\Operations\Parts\PartsTechLoginCredentials;
use Illuminate\Support\Facades\Http;

test('stock core does not call PartsTech', function () {
    Http::fake();
    $client = new PartsTechHttpClient(new PartsTechLoginCredentials(
        username: 'shop-user',
        password: 'shop-secret',
        baseUrl: 'https://app.partstech.com',
        sessionIdentity: 'shop',
    ));

    expect(fn () => $client->login())->toThrow(RuntimeException::class, 'PartsTech is not part of this shop installation.');
    expect(fn () => $client->graphql('query { cart { id } }'))->toThrow(RuntimeException::class, 'PartsTech is not part of this shop installation.');
    Http::assertNothingSent();
});
