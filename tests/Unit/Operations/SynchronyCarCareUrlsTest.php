<?php

use App\Ark\Operations\Leads\Public\SynchronyCarCareUrls;

test('synchrony car care urls expose merchant channels', function (): void {
    expect(SynchronyCarCareUrls::linkUrl())
        ->toBe('https://www.synchrony.com/mmc/CR243778456?sitecode=acewel401')
        ->and(SynchronyCarCareUrls::qrUrl())
        ->toBe('https://www.synchrony.com/mmc/CR243778456?sitecode=acewel402')
        ->and(SynchronyCarCareUrls::embedUrl())
        ->toBe('https://www.synchrony.com/mmc/CR243778456?sitecode=acewel403')
        ->and(SynchronyCarCareUrls::channels()['apply_button_image'])
        ->toContain('syf_apply_218.png');
});
