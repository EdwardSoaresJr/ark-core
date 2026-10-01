<?php

use App\Ark\Communications\Provisioning\EndpointProvisionServerUrl;
use App\Ark\Communications\Provisioning\PolyPhoneProvServerProvisioning;
use Tests\TestCase;


test('poly phone provision server points at ark provision path', function (): void {
    config()->set('shop.base_url', 'https://app.demo-auto.test');

    $host = parse_url(EndpointProvisionServerUrl::base(), PHP_URL_HOST);
    $path = parse_url(EndpointProvisionServerUrl::base(), PHP_URL_PATH);
    $expected = $host.rtrim((string) $path, '/').'/';

    $xml = PolyPhoneProvServerProvisioning::phoneDeviceElement('America/Denver');

    expect($xml)
        ->toContain('<device ')
        ->toContain('device.prov.serverName="'.$expected.'"')
        ->toContain('device.prov.serverType="HTTPS"')
        ->toContain('device.prov.serverName.set="1"')
        ->toContain('device.sntp.gmtOffset="-21600"')
        ->toContain('device.sntp.gmtOffsetcityID="6"');
});
