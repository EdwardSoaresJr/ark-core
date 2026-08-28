<?php

use App\Ark\Operations\Portal\CreatePortalShortLinkAction;
use App\Ark\Operations\Portal\PortalShortLink;
use Illuminate\Support\Carbon;

test('portal short link redirects to destination', function () {
    $destination = route('portal.estimates.show', ['token' => str_repeat('a', 64)]);
    $shortUrl = app(CreatePortalShortLinkAction::class)->execute($destination);
    $code = (string) str($shortUrl)->after('/go/');

    $this->get(route('portal.short.redirect', ['code' => $code]))
        ->assertRedirect($destination);
});

test('portal short link reuses active destination', function () {
    $destination = route('portal.estimates.show', ['token' => str_repeat('b', 64)]);
    $action = app(CreatePortalShortLinkAction::class);

    expect($action->execute($destination))->toBe($action->execute($destination))
        ->and(PortalShortLink::query()->count())->toBe(1);
});

test('expired portal short link is not found', function () {
    $destination = route('portal.estimates.show', ['token' => str_repeat('c', 64)]);

    PortalShortLink::query()->create([
        'code' => 'expiredcode',
        'destination_url' => $destination,
        'expires_at' => Carbon::parse('2020-01-01 00:00:00'),
    ]);

    $this->get(route('portal.short.redirect', ['code' => 'expiredcode']))
        ->assertNotFound();
});
