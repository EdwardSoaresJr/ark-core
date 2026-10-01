<?php

use App\Ark\Operations\Portal\EstimateAccessToken;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Http\Middleware\PreventPortalSearchIndexing;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('robots.txt disallows portal crawling', function () {
    $this->get(route('robots.txt'))
        ->assertOk()
        ->assertSee('Disallow: /portal/', false)
        ->assertSee('Sitemap:', false);
});

test('portal estimate responses include search indexing protections', function () {
    $repairOrder = repairOrderForCommunication(status: RepairOrderStatus::WaitingApproval);

    $plainToken = str_repeat('a', 64);
    EstimateAccessToken::createForPlainToken($repairOrder, $plainToken);

    $response = $this->get(route('portal.estimates.show', ['token' => $plainToken]));

    $response->assertOk()
        ->assertHeader('X-Robots-Tag', PreventPortalSearchIndexing::ROBOTS_DIRECTIVE)
        ->assertSee('<meta name="robots" content="noindex,nofollow,noarchive">', false);

    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

test('portal index includes search indexing protections', function () {
    $this->get(route('portal.index'))
        ->assertOk()
        ->assertHeader('X-Robots-Tag', PreventPortalSearchIndexing::ROBOTS_DIRECTIVE)
        ->assertSee('<meta name="robots" content="noindex,nofollow,noarchive">', false);
});
