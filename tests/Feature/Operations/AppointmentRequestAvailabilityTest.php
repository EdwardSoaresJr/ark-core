<?php

use App\Ark\Operations\Appointments\AppointmentRequestAvailability;
use App\Ark\Operations\Appointments\AppointmentRequestAvailabilityProjection;
use App\Ark\Operations\Appointments\AppointmentRequestException;
use App\Ark\Operations\Appointments\SchedulingHours;
use App\Ark\Operations\Customers\Recognition\CustomerRecognitionProjection;
use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\Leads\LeadSource;
use App\Ark\Operations\Leads\Public\LeadPhoneVerification;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    ShopSettings::forgetCurrent();
    ShopSettings::current()->update([
        'shop_timezone' => 'America/Denver',
        'appointments_enabled' => true,
        'scheduling_hours' => SchedulingHours::defaultWeekly(),
        'appointment_request_availability' => [
            'weekly' => [
                'monday' => ['enabled' => true],
                'tuesday' => ['enabled' => true],
                'wednesday' => ['enabled' => true],
                'thursday' => ['enabled' => true],
                'friday' => ['enabled' => true],
                'saturday' => ['enabled' => false],
                'sunday' => ['enabled' => false],
            ],
            'horizon_days' => 14,
            'minimum_notice_days' => 0,
        ],
    ]);
    ShopSettings::forgetCurrent();

    Carbon::setTestNow(Carbon::parse('2026-07-24 10:00:00', 'America/Denver'));

    ShopSettings::current()->update([
        'telephony_inbound_number' => '7195559999',
    ]);
    ShopSettings::forgetCurrent();
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function bookGuestVerifiedSession(): void
{
    session([
        LeadPhoneVerification::SESSION_KEY => [
            'phone' => '7195550142',
            'verified_at' => now()->timestamp,
        ],
        CustomerRecognitionProjection::SESSION_GUEST_KEY => true,
    ]);
}

test('request availability defaults seed from scheduling hours when unset', function (): void {
    ShopSettings::current()->update(['appointment_request_availability' => null]);
    ShopSettings::forgetCurrent();

    $config = AppointmentRequestAvailability::forShop();

    expect($config['weekly']['saturday']['enabled'])->toBeFalse()
        ->and($config['weekly']['monday']['enabled'])->toBeTrue()
        ->and($config['horizon_days'])->toBe(14);
});

test('book page projects only requestable weekdays inside horizon', function (): void {
    bookGuestVerifiedSession();

    $this->get(route('public.book'))
        ->assertOk()
        ->assertSee('When works best?', false)
        ->assertSee('Which day?', false)
        ->assertSee('Monday, July 27', false)
        ->assertSee('Friday, July 31', false)
        ->assertDontSee('Saturday, July 25', false);
});

test('book rejects preferred date that is not currently requestable', function (): void {
    bookGuestVerifiedSession();

    $this->post(route('public.leads.store'), [
        'concern' => 'Need service',
        'preferred_date' => '2026-07-25', // Saturday — weekly off
        'preferred_period' => 'afternoon',
        'phone' => '719-555-0142',
        'first_name' => 'Alex',
        'last_name' => 'Morgan',
        'source' => LeadSource::Website->value,
        'form_rendered_at' => now()->subSeconds(5)->timestamp,
        'public_surface_page' => 'book',
        'public_surface_variant' => 'appointment_request',
        'public_surface_placement' => 'embedded_form',
    ])->assertSessionHasErrors('preferred_date');

    expect(Lead::query()->count())->toBe(0);
});

test('schedule day disable stops public book from offering that date', function (): void {
    $this->actingAs($this->admin)
        ->post(route('operations.appointments.request-availability'), [
            'date' => '2026-07-29',
            'mode' => 'disable',
            'reason' => 'Fully booked',
        ])
        ->assertRedirect(route('operations.appointments.index', ['day' => '2026-07-29']));

    expect(AppointmentRequestException::query()->whereDate('date', '2026-07-29')->value('mode'))
        ->toBe(AppointmentRequestException::MODE_DISABLE);

    $dates = collect(app(AppointmentRequestAvailabilityProjection::class)->forBook()['dates'])
        ->pluck('date')
        ->all();

    expect($dates)->not->toContain('2026-07-29')
        ->and($dates)->toContain('2026-07-28');
});

test('schedule day enable can open a normally closed weekday for requests', function (): void {
    $this->actingAs($this->admin)
        ->post(route('operations.appointments.request-availability'), [
            'date' => '2026-07-25',
            'mode' => 'enable',
            'reason' => 'Taking a few Saturday requests',
        ])
        ->assertRedirect();

    $dates = collect(app(AppointmentRequestAvailabilityProjection::class)->forBook()['dates'])
        ->pluck('date')
        ->all();

    expect($dates)->toContain('2026-07-25');
});

test('settings can save appointment request availability weekly defaults', function (): void {
    $this->actingAs($this->admin)
        ->patch(route('operations.settings.shop.appointments.update'), [
            'appointments_enabled' => '1',
            'appointment_slot_minutes' => 30,
            'appointment_capacity_basis' => 'limiting_resource',
            'appointment_scheduling_target_percent' => 100,
            'appointment_capacity_enforcement' => 'warn',
            'appointment_request_availability' => [
                'weekly' => [
                    'monday' => ['enabled' => '1'],
                    'tuesday' => ['enabled' => '1'],
                    'wednesday' => ['enabled' => '0'],
                    'thursday' => ['enabled' => '1'],
                    'friday' => ['enabled' => '1'],
                    'saturday' => ['enabled' => '0'],
                    'sunday' => ['enabled' => '0'],
                ],
                'horizon_days' => '7',
                'horizon_is_custom' => '0',
                'minimum_notice_days' => '1',
            ],
        ])
        ->assertRedirect();

    ShopSettings::forgetCurrent();
    $config = AppointmentRequestAvailability::forShop();

    expect($config['weekly']['wednesday']['enabled'])->toBeFalse()
        ->and($config['weekly']['monday']['enabled'])->toBeTrue()
        ->and($config['horizon_days'])->toBe(7)
        ->and($config['minimum_notice_days'])->toBe(1);
});

test('business hours label is independent of request availability saturday', function (): void {
    // Request availability Sat off; telephony hours unchanged by this feature.
    $config = AppointmentRequestAvailability::forShop();
    expect($config['weekly']['saturday']['enabled'])->toBeFalse();

    bookGuestVerifiedSession();

    $this->get(route('public.book'))
        ->assertOk()
        ->assertDontSee('Saturday, July 25', false);
});
