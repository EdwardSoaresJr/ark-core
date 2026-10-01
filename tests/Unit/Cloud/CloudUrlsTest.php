<?php

use App\Ark\Platform\Cloud\CloudUrls;

test('cloud urls build connect and manage deep links', function () {
    config(['services.ark_cloud.base_url' => 'https://cloud.example.test']);

    expect(CloudUrls::connect('11111111-1111-1111-1111-111111111111', 'https://box.example.test/app/cloud/connecting'))
        ->toBe('https://cloud.example.test/connect/11111111-1111-1111-1111-111111111111?return_url='.rawurlencode('https://box.example.test/app/cloud/connecting'));

    expect(CloudUrls::go('services.mail', '22222222-2222-2222-2222-222222222222'))
        ->toBe('https://cloud.example.test/go?to=services.mail&shop=22222222-2222-2222-2222-222222222222');

    expect(CloudUrls::go('shop'))
        ->toBe('https://cloud.example.test/go?to=shop');
});
