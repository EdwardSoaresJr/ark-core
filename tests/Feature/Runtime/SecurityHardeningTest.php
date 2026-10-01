<?php

use App\Ark\Operations\Appointments\Appointment;
use App\Ark\Operations\Appointments\AppointmentStatus;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Settings\ShopSettings;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    ShopSettings::current()->update(['appointments_enabled' => true]);
    $this->seed(ArkAuthorizationSeeder::class);
});

test('privileged user fields cannot be mass assigned', function () {
    $user = User::factory()->create();
    $user->forceFill(['is_master_admin' => false, 'is_active' => true, 'password_set_at' => null])->save();

    $user->update([
        'is_master_admin' => true,
        'is_active' => false,
        'password' => 'hacked-password',
        'password_set_at' => now(),
    ]);

    $user->refresh();

    expect($user->is_master_admin)->toBeFalse()
        ->and($user->is_active)->toBeTrue()
        ->and($user->hasPasswordSet())->toBeFalse();

    $user->forceFill(['is_master_admin' => true])->save();

    expect($user->fresh()->is_master_admin)->toBeTrue();
});

test('shop integration secrets cannot be mass assigned', function () {
    $settings = ShopSettings::current();

    $settings->update([
        'square_access_token' => 'mass-assigned-square',
        'ark_mail_credential' => 'mass-assigned-ark-mail',
        'cloud_credential' => 'mass-assigned-cloud',
        'messenger_app_secret' => 'mass-assigned-messenger',
        'square_webhook_signature_key' => 'mass-assigned-webhook',
    ]);

    $settings->refresh();

    expect($settings->square_access_token)->toBeNull()
        ->and($settings->ark_mail_credential)->toBeNull()
        ->and($settings->cloud_credential)->toBeNull()
        ->and($settings->messenger_app_secret)->toBeNull()
        ->and($settings->square_webhook_signature_key)->toBeNull();

    $settings->persistTrusted(['square_access_token' => 'trusted-token']);

    expect($settings->fresh()->square_access_token)->toBe('trusted-token');
});

test('appointment status rejects off-site redirect targets', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-10 08:00:00'));
    $advisor = actingAsLearnCurrentAdvisor();

    $customer = Customer::query()->create([
        'first_name' => 'Redirect',
        'last_name' => 'Test',
        'phone' => '555-0199',
    ]);

    $appointment = Appointment::query()->create([
        'customer_id' => $customer->id,
        'created_by_user_id' => $advisor->id,
        'advisor_user_id' => $advisor->id,
        'starts_at' => Carbon::parse('2026-06-10 10:00:00'),
        'ends_at' => Carbon::parse('2026-06-10 11:00:00'),
        'concern' => 'Brakes',
        'status' => AppointmentStatus::Scheduled,
    ]);

    $this->actingAs($advisor)
        ->patch(route('operations.appointments.status', $appointment), [
            'status' => AppointmentStatus::Confirmed->value,
            'redirect' => 'https://evil.example/phish',
        ])
        ->assertRedirect(route('operations.appointments.show', $appointment));

    Carbon::setTestNow();
});

test('appointment status allows same-origin redirect targets', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-10 08:00:00'));
    $advisor = actingAsLearnCurrentAdvisor();

    $customer = Customer::query()->create([
        'first_name' => 'Safe',
        'last_name' => 'Redirect',
        'phone' => '555-0200',
    ]);

    $appointment = Appointment::query()->create([
        'customer_id' => $customer->id,
        'created_by_user_id' => $advisor->id,
        'advisor_user_id' => $advisor->id,
        'starts_at' => Carbon::parse('2026-06-10 10:00:00'),
        'ends_at' => Carbon::parse('2026-06-10 11:00:00'),
        'concern' => 'Oil change',
        'status' => AppointmentStatus::Scheduled,
    ]);

    $target = route('operations.index');

    $this->actingAs($advisor)
        ->patch(route('operations.appointments.status', $appointment), [
            'status' => AppointmentStatus::Confirmed->value,
            'redirect' => $target,
        ])
        ->assertRedirect($target);

    Carbon::setTestNow();
});
