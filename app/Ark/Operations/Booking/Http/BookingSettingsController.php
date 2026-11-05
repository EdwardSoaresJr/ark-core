<?php

namespace App\Ark\Operations\Booking\Http;

use App\Ark\Operations\Appointments\AppointmentRequestAvailability;
use App\Ark\Operations\Appointments\AppointmentRequestException;
use App\Ark\Operations\Booking\BookingMode;
use App\Ark\Operations\Booking\BookingSettings;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

/**
 * Settings → Online requests: the scheduling rules every online booking passes, whichever booking
 * product collects it. A product registered under the route name booking.builder is linked here.
 */
final class BookingSettingsController
{
    public function edit(): View
    {
        $settings = ShopSettings::current();

        return view('operations.settings.booking', [
            'booking' => BookingSettings::forShop($settings),
            'requestAvailability' => AppointmentRequestAvailability::forShop($settings),
            'exceptions' => AppointmentRequestException::query()
                ->whereDate('date', '>=', ShopDisplayTimezone::now()->toDateString())
                ->orderBy('date')
                ->limit(60)
                ->get(),
            'builderUrl' => Route::has('booking.builder') ? route('booking.builder') : null,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mode' => ['required', Rule::enum(BookingMode::class)],
            'service_area_postal_codes' => ['nullable', 'string', 'max:6000'],
            'service_area_message' => ['nullable', 'string', 'max:300'],
            'daily_requests' => ['nullable', 'integer', 'min:1', 'max:500'],
            'respect_capacity' => ['nullable', 'boolean'],
            'concurrent_appointments' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        ShopSettings::current()->forceFill(['online_booking' => BookingSettings::normalize([
            'mode' => $data['mode'],
            'limits' => [
                'daily_requests' => $data['daily_requests'] ?? null,
                'respect_capacity' => $request->boolean('respect_capacity'),
                'concurrent_appointments' => $data['concurrent_appointments'] ?? null,
            ],
            'service_area' => [
                'postal_codes' => BookingSettings::postalCodes((string) ($data['service_area_postal_codes'] ?? '')),
                'message' => (string) ($data['service_area_message'] ?? ''),
            ],
        ])])->save();
        ShopSettings::forgetCurrent();

        return redirect()->route('operations.settings.booking')->with('status', 'Online request rules saved.');
    }

    public function storeClosedDate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        AppointmentRequestException::query()->updateOrCreate(
            ['date' => $data['date']],
            [
                'mode' => AppointmentRequestException::MODE_DISABLE,
                'reason' => $data['reason'] ?? null,
                'created_by_user_id' => $request->user()?->id,
            ],
        );

        return redirect()->route('operations.settings.booking')->with('status', 'Online requests closed on '.$data['date'].'.');
    }

    public function destroyClosedDate(AppointmentRequestException $exception): RedirectResponse
    {
        $exception->delete();

        return redirect()->route('operations.settings.booking')->with('status', 'Date override removed.');
    }
}
