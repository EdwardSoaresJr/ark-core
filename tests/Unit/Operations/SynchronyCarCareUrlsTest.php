<?php

use App\Ark\Operations\Leads\Public\SynchronyCarCareUrls;

test('synchrony car care urls expose merchant channels', function (): void {
    expect(SynchronyCarCareUrls::linkUrl())
        ->toBe('https://www.synchrony.com/mmc/CR000000000?sitecode=demo401')
        ->and(SynchronyCarCareUrls::qrUrl())
        ->toBe('https://www.synchrony.com/mmc/CR000000000?sitecode=demo402')
        ->and(SynchronyCarCareUrls::embedUrl())
        ->toBe('https://www.synchrony.com/mmc/CR000000000?sitecode=demo403')
        ->and(SynchronyCarCareUrls::channels()['apply_button_image'])
        ->toContain('syf_apply_218.png');
});
