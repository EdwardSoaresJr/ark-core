<?php

use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
});

/**
 * @return array<string, mixed>
 */
function businessHoursPayload(array $overrides = []): array
{
    $weekly = [];
    foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $day) {
        $weekly[$day] = [
            'enabled' => in_array($day, ['saturday', 'sunday'], true) ? '0' : '1',
            'open' => '09:00',
            'close' => '18:00',
        ];
    }

    return array_replace_recursive([
        'shop_timezone' => 'America/Denver',
        'business_hours' => [
            'weekly' => $weekly,
            'closed_dates' => "2026-12-25\n2026-11-27",
        ],
    ], $overrides);
}

test('business hours section is discoverable in shop settings', function () {
    $this->actingAs($this->admin)
        ->get(route('operations.settings.shop.edit', ['section' => 'hours']))
        ->assertOk()
        ->assertSee('data-settings-section="hours"', false)
        ->assertSee('Save Business Hours', false)
        ->assertSee('Weekly schedule', false)
        ->assertSee('How hours relate', false);
});

test('appointments settings link to business hours section', function () {
    $this->actingAs($this->admin)
        ->get(route('operations.settings.shop.edit', ['section' => 'operations']))
        ->assertOk()
        ->assertSee(route('operations.settings.shop.edit', ['section' => 'hours']), false)
        ->assertDontSee(route('operations.settings.shop.edit', ['section' => 'general']).'">Edit Business Hours', false);
});

test('admin can save reload and persist business hours', function () {
    $this->actingAs($this->admin)
        ->patch(route('operations.settings.shop.business-hours.update'), businessHoursPayload([
            'shop_timezone' => 'America/Chicago',
            'business_hours' => [
                'weekly' => [
                    'monday' => ['enabled' => '1', 'open' => '08:00', 'close' => '17:00'],
                    'tuesday' => ['enabled' => '1', 'open' => '08:00', 'close' => '17:00'],
                    'wednesday' => ['enabled' => '1', 'open' => '08:00', 'close' => '17:00'],
                    'thursday' => ['enabled' => '1', 'open' => '08:00', 'close' => '17:00'],
                    'friday' => ['enabled' => '1', 'open' => '08:00', 'close' => '17:00'],
                    'saturday' => ['enabled' => '1', 'open' => '09:00', 'close' => '13:00'],
                    'sunday' => ['enabled' => '0', 'open' => '09:00', 'close' => '13:00'],
                ],
                'closed_dates' => "2026-12-25\n2026-07-04",
            ],
        ]))
        ->assertRedirect(route('operations.settings.shop.edit', ['section' => 'hours']))
        ->assertSessionHas('status', 'Business hours saved.');

    ShopSettings::forgetCurrent();
    $settings = ShopSettings::current()->fresh();
    $snapshot = $settings->businessHoursSnapshot();
    $owned = is_array($settings->business_hours) ? $settings->business_hours : [];

    expect($settings->shop_timezone)->toBe('America/Chicago')
        ->and($owned)->not->toBe([])
        ->and($snapshot['weekly']['monday']['open'])->toBe('08:00')
        ->and($snapshot['weekly']['monday']['close'])->toBe('17:00')
        ->and((bool) $snapshot['weekly']['saturday']['enabled'])->toBeTrue()
        ->and($snapshot['weekly']['saturday']['open'])->toBe('09:00')
        ->and((bool) $snapshot['weekly']['sunday']['enabled'])->toBeFalse()
        ->and($snapshot['closed_dates'])->toBe(['2026-07-04', '2026-12-25'])
        ->and($owned['weekly']['monday']['open'] ?? null)->toBe('08:00');

    $this->actingAs($this->admin)
        ->get(route('operations.settings.shop.edit', ['section' => 'hours']))
        ->assertOk()
        ->assertSee('value="08:00"', false)
        ->assertSee('value="America/Chicago"', false);
});

test('hosted settings nav hides stations and phones', function () {
    $catalog = tempnam(sys_get_temp_dir(), 'ark-hosted-');
    file_put_contents($catalog, '{}');
    putenv('ARK_HOSTED_TENANTS='.$catalog);
    $_ENV['ARK_HOSTED_TENANTS'] = $catalog;
    $_SERVER['ARK_HOSTED_TENANTS'] = $catalog;

    try {
        expect(\App\Ark\Runtime\HostedRuntime::isHostedProduct())->toBeTrue();

        $html = $this->actingAs($this->admin)
            ->get(route('operations.settings.shop.edit'))
            ->assertOk()
            ->assertSee('Business Hours', false)
            ->getContent();

        expect($html)
            ->not->toContain('Stations & Phones')
            ->and($html)->not->toContain('Stations &amp; Phones');
    } finally {
        putenv('ARK_HOSTED_TENANTS');
        unset($_ENV['ARK_HOSTED_TENANTS'], $_SERVER['ARK_HOSTED_TENANTS']);
        @unlink($catalog);
    }
});

test('saving shop identity does not wipe business hours', function () {
    $this->actingAs($this->admin)
        ->patch(route('operations.settings.shop.business-hours.update'), businessHoursPayload([
            'business_hours' => [
                'weekly' => [
                    'monday' => ['enabled' => '1', 'open' => '09:00', 'close' => '18:00'],
                    'tuesday' => ['enabled' => '1', 'open' => '09:00', 'close' => '18:00'],
                    'wednesday' => ['enabled' => '1', 'open' => '09:00', 'close' => '18:00'],
                    'thursday' => ['enabled' => '1', 'open' => '09:00', 'close' => '18:00'],
                    'friday' => ['enabled' => '1', 'open' => '09:00', 'close' => '18:00'],
                    'saturday' => ['enabled' => '1', 'open' => '10:00', 'close' => '14:00'],
                    'sunday' => ['enabled' => '0', 'open' => '09:00', 'close' => '13:00'],
                ],
                'closed_dates' => '2026-12-25',
            ],
        ]))
        ->assertRedirect();

    $this->actingAs($this->admin)
        ->patch(route('operations.settings.shop.general.update'), [
            'shop_name' => 'Hours Shop Renamed',
            'shop_timezone' => 'America/Denver',
        ])
        ->assertRedirect();

    ShopSettings::forgetCurrent();
    $settings = ShopSettings::current()->fresh();
    $snapshot = $settings->businessHoursSnapshot();

    expect($settings->shop_name)->toBe('Hours Shop Renamed')
        ->and((bool) $snapshot['weekly']['saturday']['enabled'])->toBeTrue()
        ->and($snapshot['weekly']['saturday']['open'])->toBe('10:00')
        ->and($snapshot['closed_dates'])->toBe(['2026-12-25']);
});

test('business hours feed appointment follow-shop scheduling', function () {
    $this->actingAs($this->admin)
        ->patch(route('operations.settings.shop.business-hours.update'), businessHoursPayload([
            'business_hours' => [
                'weekly' => [
                    'monday' => ['enabled' => '1', 'open' => '07:30', 'close' => '16:30'],
                    'tuesday' => ['enabled' => '1', 'open' => '07:30', 'close' => '16:30'],
                    'wednesday' => ['enabled' => '1', 'open' => '07:30', 'close' => '16:30'],
                    'thursday' => ['enabled' => '1', 'open' => '07:30', 'close' => '16:30'],
                    'friday' => ['enabled' => '1', 'open' => '07:30', 'close' => '16:30'],
                    'saturday' => ['enabled' => '0', 'open' => '09:00', 'close' => '12:00'],
                    'sunday' => ['enabled' => '0', 'open' => '09:00', 'close' => '12:00'],
                ],
            ],
        ]))
        ->assertRedirect();

    ShopSettings::current()->update(['scheduling_hours' => null]);
    ShopSettings::forgetCurrent();

    $hours = ShopSettings::current()->schedulingHours();

    expect(ShopSettings::current()->usesCustomSchedulingHours())->toBeFalse()
        ->and($hours['monday']['open'])->toBe('07:30')
        ->and($hours['saturday']['enabled'])->toBeFalse();
});

test('advisor cannot update business hours', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $before = ShopSettings::current()->fresh()->businessHoursSnapshot();

    $this->actingAs($advisor)
        ->patch(route('operations.settings.shop.business-hours.update'), businessHoursPayload([
            'shop_timezone' => 'America/New_York',
            'business_hours' => [
                'weekly' => [
                    'monday' => ['enabled' => '0', 'open' => '01:00', 'close' => '02:00'],
                ],
            ],
        ]))
        ->assertRedirect();

    ShopSettings::forgetCurrent();
    $after = ShopSettings::current()->fresh();

    expect($after->shop_timezone)->not->toBe('America/New_York')
        ->and($after->businessHoursSnapshot()['weekly']['monday'])->toBe($before['weekly']['monday']);
});

test('settings nav does not surface obsolete platform pairing', function () {
    $this->actingAs($this->admin)
        ->get(route('operations.settings.shop.edit'))
        ->assertOk()
        ->assertDontSee("@click=\"setActive('ark-cloud')\"", false)
        ->assertDontSee('Connect this Box')
        ->assertSee('Business Hours', false);

    $this->actingAs($this->admin)
        ->get(route('operations.settings.shop.edit', ['section' => 'ark-cloud']))
        ->assertRedirect(route('operations.settings.shop.edit', ['section' => 'general']));
});
