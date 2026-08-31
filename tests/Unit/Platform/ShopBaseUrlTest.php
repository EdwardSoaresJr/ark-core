<?php

use App\Ark\Platform\ShopBaseUrl;
use Tests\TestCase;


it('derives voice capability urls from shop base url', function (): void {
    config()->set('shop.base_url', 'https://shop1.arksms.com');

    expect(ShopBaseUrl::origin())->toBe('https://shop1.arksms.com')
        ->and(ShopBaseUrl::host())->toBe('shop1.arksms.com')
        ->and(ShopBaseUrl::voice('call-events'))->toBe('https://shop1.arksms.com/voice/call-events');
});
