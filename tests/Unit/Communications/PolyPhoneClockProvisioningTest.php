<?php

use App\Ark\Communications\Provisioning\PolyPhoneClockProvisioning;

test('america denver matches working vvx profile', function (): void {
    $xml = PolyPhoneClockProvisioning::phoneChildren('America/Denver');
    $deviceAttrs = PolyPhoneClockProvisioning::deviceElementAttributes('America/Denver');

    expect($xml)
        ->toContain('tcpIpApp.sntp.gmtOffset="-21600"')
        ->toContain('tcpIpApp.sntp.daylightSavings.enable="0"')
        ->not->toContain('gmtOffsetcityID');

    expect($deviceAttrs)
        ->toMatchArray([
            'device.sntp.gmtOffset' => '-21600',
            'device.sntp.gmtOffsetcityID' => '6',
            'device.sntp.serverName' => 'time.google.com',
        ]);
});

test('america phoenix uses fixed offset without city id on tcpIpApp', function (): void {
    $xml = PolyPhoneClockProvisioning::phoneChildren('America/Phoenix');

    expect($xml)
        ->toContain('tcpIpApp.sntp.gmtOffset=')
        ->toContain('tcpIpApp.sntp.daylightSavings.enable="0"')
        ->not->toContain('gmtOffsetcityID');
});

test('unknown timezone uses computed offset without city id on tcpIpApp', function (): void {
    $xml = PolyPhoneClockProvisioning::phoneChildren('Etc/GMT-5');

    expect($xml)
        ->toContain('tcpIpApp.sntp.gmtOffset=')
        ->not->toContain('gmtOffsetcityID');
});
