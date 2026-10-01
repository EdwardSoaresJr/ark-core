<?php

use App\Ark\Platform\Cloud\CloudUrls;

beforeEach(function () {
    $this->withoutVite();
    config([
        'services.ark_cloud.base_url' => 'https://cloud.arksms.com',
        'services.ark_mail.base_url' => 'https://cloud.arksms.com',
    ]);
});

it('does not host the ARK Platform product portal on the Core Box', function () {
    $this->get('/cloud')->assertRedirect('https://cloud.arksms.com');
    $this->get('/cloud/login')->assertRedirect('https://cloud.arksms.com/login');
    $this->get('/cloud/dashboard')->assertRedirect('https://cloud.arksms.com/dashboard');
});

it('returns 404 for /cloud when Cloud base URL is not configured', function () {
    config([
        'services.ark_cloud.base_url' => null,
        'services.ark_mail.base_url' => null,
    ]);

    $this->get('/cloud')->assertNotFound();
});

it('points CloudUrls at the external Cloud control plane', function () {
    expect(CloudUrls::usesCloudPrefix())->toBeFalse()
        ->and(CloudUrls::login())->toBe('https://cloud.arksms.com/login')
        ->and(CloudUrls::dashboard())->toBe('https://cloud.arksms.com/portal')
        ->and(CloudUrls::pairing())->toBe('https://cloud.arksms.com/portal/pairing');
});
