<?php

use App\Ark\Communications\Provisioning\EndpointConfigurationProjection;
use App\Ark\Communications\Telephony\AssignExtensionToWorkstationAction;
use App\Ark\Operations\Communications\CommunicationDevice;
use App\Ark\Operations\Communications\CommunicationDeviceProvider;
use App\Ark\Operations\Communications\CommunicationDeviceStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\TelephonyExtension;
use App\Ark\Operations\Workstations\Workstation;
use App\Ark\Platform\VoiceTransportConfiguration;
use Database\Seeders\CommunicationDeviceModelSeeder;
use Illuminate\Support\Facades\File;


beforeEach(function (): void {
    ShopSettings::forgetCurrent();
    ShopSettings::current();
    $this->seed(CommunicationDeviceModelSeeder::class);

    foreach (['VOICE_SIP_REGISTRAR', 'ARK_SHARED_SECRETS_PATH'] as $key) {
        putenv($key);
        unset($_ENV[$key], $_SERVER[$key]);
    }

    File::delete(VoiceTransportConfiguration::storagePath());
    File::delete(storage_path('framework/testing/ark-production.env'));

    config()->set('telephony.sip_provisioning.host', 'example.sip.twilio.com');
    config()->set('voice-transport.sip_registrar', 'example.sip.twilio.com');
    config()->set('telephony.sip_provisioning.default_password', 'secret-101');
});

function createProvisionableDevice(string $mac = '48256730757F'): CommunicationDevice
{
    $workstation = Workstation::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'name' => 'Front Counter',
        'is_active' => true,
    ]);

    $device = CommunicationDevice::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'workstation_id' => $workstation->id,
        'name' => 'Front Desk VVX350',
        'model' => 'VVX350',
        'mac_address' => $mac,
        'provider' => CommunicationDeviceProvider::ShopPhone,
        'status' => CommunicationDeviceStatus::WaitingForRegistration,
        'is_active' => true,
        'microbrowser_token' => 'screen-token-abc',
        'capabilities' => ['voice'],
    ]);

    app(AssignExtensionToWorkstationAction::class)->execute(
        workstation: $workstation,
        extension: '101',
        displayName: 'Front Counter',
        secret: 'secret-101',
        communicationDevice: $device,
    );

    return $device->fresh(['workstation']);
}

test('get provision master cfg matches asterisk phoneprov bootstrap', function (): void {
    createProvisionableDevice();

    $this->get(route('communications.endpoints.provision', ['filename' => '48256730757f.cfg']))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('<APPLICATION CONFIG_FILES="config/48256730757f"', false)
        ->assertDontSee('sip.cfg', false)
        ->assertDontSee('sip.ld', false);
});

test('get provision phone shell cfg matches asterisk empty phone template', function (): void {
    $this->get(route('communications.endpoints.provision', ['filename' => '48256730757f-phone.cfg']))
        ->assertOk()
        ->assertSee('<?xml version="1.0" standalone="yes"?>', false);
});

test('get provision optional poly artifacts return empty shell instead of 404', function (): void {
    createProvisionableDevice();

    foreach (['48256730757f-license.cfg', '48256730757f-calls.xml'] as $filename) {
        $this->get(route('communications.endpoints.provision', ['filename' => $filename]))
            ->assertOk()
            ->assertSee('<?xml version="1.0" standalone="yes"?>', false);
    }
});

test('get provision config serves poly phone1 body from endpoint configuration projection', function (): void {
    $device = createProvisionableDevice();

    $provisionHost = parse_url(\App\Ark\Communications\Provisioning\EndpointProvisionServerUrl::base(), PHP_URL_HOST);
    $provisionPath = parse_url(\App\Ark\Communications\Provisioning\EndpointProvisionServerUrl::base(), PHP_URL_PATH);
    $provisionServer = $provisionHost.rtrim((string) $provisionPath, '/').'/';

    $response = $this->get(route('communications.endpoints.provision.config', ['mac' => '48256730757f']));

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/xml; charset=UTF-8')
        ->assertSee('<phone1', false)
        ->assertSee('device.prov.serverName="'.$provisionServer.'"', false)
        ->assertSee('reg.1.auth.userId="101"', false)
        ->assertSee('reg.1.auth.password="secret-101"', false)
        ->assertSee('reg.1.server.1.address="example.sip.twilio.com"', false)
        ->assertSee('reg.1.server.1.register="1"', false)
        ->assertSee('reg.1.server.1.transport="UDPOnly"', false)
        ->assertDontSee('assigned_user');

    expect(EndpointConfigurationProjection::query()->current()->count())->toBe(1)
        ->and($device->fresh()->provider_identifier)->toBe('101');
});

test('get provision config returns 404 for unknown mac and records discovery', function (): void {
    $this->get(route('communications.endpoints.provision.config', ['mac' => 'AABBCCDDEEFF']))
        ->assertNotFound();

    expect(CommunicationDevice::query()
        ->where('mac_address', 'AABBCCDDEEFF')
        ->where('status', CommunicationDeviceStatus::Discovered)
        ->exists())->toBeTrue();
});

test('get provision config returns 403 when device is inactive', function (): void {
    $device = createProvisionableDevice();
    $device->forceFill(['is_active' => false])->save();

    $this->get(route('communications.endpoints.provision.config', ['mac' => '48256730757F']))
        ->assertForbidden();
});

test('get provision config returns 403 when workstation has no extension', function (): void {
    CommunicationDevice::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'name' => 'Unassigned Extension Phone',
        'mac_address' => 'AABBCCDDEEFF',
        'workstation_id' => Workstation::query()->create([
            'shop_settings_id' => ShopSettings::reloadCurrent()->id,
            'name' => 'Parts',
            'is_active' => true,
        ])->id,
        'provider' => CommunicationDeviceProvider::ShopPhone,
        'status' => CommunicationDeviceStatus::WaitingForRegistration,
        'is_active' => true,
        'capabilities' => ['voice'],
    ]);

    $this->get(route('communications.endpoints.provision.config', ['mac' => 'AABBCCDDEEFF']))
        ->assertForbidden();
});

test('get provision config does not mutate telephony authority', function (): void {
    createProvisionableDevice();

    $extensionCountBefore = TelephonyExtension::query()->count();

    $this->get(route('communications.endpoints.provision.config', ['mac' => '48256730757F']))
        ->assertOk();

    expect(TelephonyExtension::query()->count())->toBe($extensionCountBefore);
});

test('get provision config reuses projection when inputs are unchanged', function (): void {
    createProvisionableDevice();

    $this->get(route('communications.endpoints.provision.config', ['mac' => '48256730757F']))
        ->assertOk();

    $firstProjectionId = EndpointConfigurationProjection::query()->current()->value('id');

    $this->get(route('communications.endpoints.provision.config', ['mac' => '48256730757F']))
        ->assertOk();

    expect(EndpointConfigurationProjection::query()->current()->count())->toBe(1)
        ->and(EndpointConfigurationProjection::query()->current()->value('id'))->toBe($firstProjectionId);
});

test('extension assignment invalidates projection for next serve', function (): void {
    $device = createProvisionableDevice();

    $this->get(route('communications.endpoints.provision.config', ['mac' => '48256730757F']))
        ->assertOk()
        ->assertSee('secret-101', false);

    app(AssignExtensionToWorkstationAction::class)->execute(
        workstation: $device->workstation,
        extension: '101',
        displayName: 'Front Counter',
        secret: 'rotated-secret',
        communicationDevice: $device,
    );

    $this->get(route('communications.endpoints.provision.config', ['mac' => '48256730757F']))
        ->assertOk()
        ->assertSee('rotated-secret', false);

    expect(EndpointConfigurationProjection::query()->count())->toBe(2)
        ->and(EndpointConfigurationProjection::query()->current()->count())->toBe(1);
});

test('get provision config does not mutate device rows on get', function (): void {
    $device = createProvisionableDevice();
    $device->forceFill(['microbrowser_token' => null])->saveQuietly();

    $updatedBefore = $device->fresh()->updated_at;

    $this->get(route('communications.endpoints.provision.config', ['mac' => '48256730757F']))
        ->assertOk();

    $device->refresh();

    expect($device->microbrowser_token)->toBeNull()
        ->and($device->updated_at?->eq($updatedBefore))->toBeTrue();
});

test('get provision config emits structured observation log', function (): void {
    $logged = [];

    Illuminate\Support\Facades\Log::listen(function (\Illuminate\Log\Events\MessageLogged $event) use (&$logged): void {
        $logged[] = ['message' => $event->message, 'context' => $event->context];
    });

    createProvisionableDevice();

    $this->get(route('communications.endpoints.provision.config', ['mac' => '48256730757F']))
        ->assertOk();

    expect(collect($logged)->contains(function (array $entry): bool {
        return ($entry['message'] ?? null) === 'endpoint.provision.request'
            && ($entry['context']['gate'] ?? null) === 'PASS'
            && ($entry['context']['artifact'] ?? null) === 'device_config'
            && in_array($entry['context']['projection'] ?? null, ['REUSED', 'REGENERATED'], true)
            && isset($entry['context']['duration_ms']);
    }))->toBeTrue();
});

test('provision mac accepts lowercase config path', function (): void {
    createProvisionableDevice('48256730757F');

    $this->get('/provision/config/48256730757f')
        ->assertOk()
        ->assertSee('reg.1.auth.userId="101"', false);
});

test('provision config returns 503 plain text when provisioning host is missing', function (): void {
    createProvisionableDevice();
    config()->set('telephony.sip_provisioning.host', null);
    config()->set('voice-transport.sip_registrar', '');

    $this->get(route('communications.endpoints.provision.config', ['mac' => '48256730757F']))
        ->assertStatus(503)
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('VOICE_SIP_REGISTRAR is not configured', false);
});

test('provision config returns 503 plain text when extension has no credential', function (): void {
    $device = createProvisionableDevice();
    config()->set('telephony.sip_provisioning.default_password', null);

    TelephonyExtension::query()
        ->where('workstation_id', $device->workstation_id)
        ->update(['secret' => null]);

    $this->get(route('communications.endpoints.provision.config', ['mac' => '48256730757F']))
        ->assertStatus(503)
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('No SIP credential configured', false);
});

test('get provision config omits vvx microbrowser idle display', function (): void {
    createProvisionableDevice();

    $this->get(route('communications.endpoints.provision.config', ['mac' => '48256730757F']))
        ->assertOk()
        ->assertDontSee('apps.mb.main.home', false)
        ->assertDontSee('mb.idleDisplay.home', false)
        ->assertDontSee('up.screenSaver.type="2"', false)
        ->assertDontSee('device-screen', false);
});

test('get provision config disables vvx native missed call tracking', function (): void {
    createProvisionableDevice();

    $this->get(route('communications.endpoints.provision.config', ['mac' => '48256730757F']))
        ->assertOk()
        ->assertSee('call.missedCallTracking.1.enabled="0"', false);
});

test('get provision config includes shop timezone clock from settings', function (): void {
    ShopSettings::current()->update(['shop_timezone' => 'America/Denver']);
    ShopSettings::reloadCurrent();

    createProvisionableDevice();

    $offset = \App\Ark\Communications\Provisioning\PolyPhoneClockProvisioning::currentOffsetSeconds('America/Denver');

    $this->get(route('communications.endpoints.provision.config', ['mac' => '48256730757F']))
        ->assertOk()
        ->assertSee('<tcpIpApp>', false)
        ->assertSee('device.sntp.gmtOffset="'.$offset.'"', false)
        ->assertSee('device.sntp.gmtOffsetcityID="6"', false)
        ->assertSee('tcpIpApp.sntp.gmtOffset="'.$offset.'"', false)
        ->assertSee('tcpIpApp.sntp.address="time.google.com"', false)
        ->assertSee('tcpIpApp.sntp.daylightSavings.enable="0"', false)
        ->assertDontSee('tcpIpApp.sntp.gmtOffsetcityID', false)
        ->assertDontSee('tcpIpApp.sntp.daylightSavings.start.month', false)
        ->assertSee('tcpIpApp.sntp.address.overrideDHCP="1"', false);
});

test('get provision config includes twilio nat settings for desk sip registration', function (): void {
    createProvisionableDevice();

    $this->get(route('communications.endpoints.provision.config', ['mac' => '48256730757f']))
        ->assertOk()
        ->assertSee('nat.ip="71.196.200.50"', false)
        ->assertSee('nat.keepalive.interval="20"', false)
        ->assertSee('reg.1.server.1.outboundProxy=', false);
});

test('get provision directory xml returns empty directory shell', function (): void {
    createProvisionableDevice();

    $this->get(route('communications.endpoints.provision', ['filename' => '48256730757f-directory.xml']))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/xml; charset=UTF-8')
        ->assertSee('<directory>', false)
        ->assertSee('<item_list>', false);
});

test('get provision serves site directory and sip cfg shells without device lookup', function (): void {
    $this->get(route('communications.endpoints.provision', ['filename' => '000000000000-directory.xml']))
        ->assertOk()
        ->assertSee('<directory>', false);

    $this->get(route('communications.endpoints.provision', ['filename' => 'sip.cfg']))
        ->assertOk()
        ->assertSee('<?xml version="1.0" standalone="yes"?>', false);
});
