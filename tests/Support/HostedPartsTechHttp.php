<?php

use App\Ark\Operations\Parts\PartsTechCredentialsResolver;
use App\Ark\Operations\Parts\PartsTechHttpClient;

function bindHostedPartsTechHttpClient(): void
{
    $path = dirname(base_path()).'/ark-saas/saas/src/Catalog/HostedPartsTechHttpClient.php';
    if (! is_file($path)) {
        test()->markTestSkipped('PartsTech HTTP is owned by ARK SaaS.');
    }

    require_once $path;
    app()->scoped(PartsTechHttpClient::class, function ($app): PartsTechHttpClient {
        $resolver = $app->make(PartsTechCredentialsResolver::class);

        return new \Ark\Saas\Catalog\HostedPartsTechHttpClient($resolver->forUser(auth()->user()));
    });
}
