<?php

namespace App\Ark\Operations\Leads\Public;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Leads\LeadContactPreference;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Vehicles\Vehicle;
use Illuminate\Support\Facades\Auth;

/**
 * Contact identity for the public lead form when the customer is signed in.
 * Concern is never prefilled from account history — only from problem pages / old input.
 *
 * @phpstan-type VehicleOption array{
 *     id: int,
 *     label: string,
 *     year: int|null,
 *     make: string,
 *     model: string,
 * }
 * @phpstan-type ContactPrefill array{
 *     signed_in: bool,
 *     first_name: string,
 *     last_name: string,
 *     phone: string,
 *     email: string,
 *     contact_preference: string,
 *     skips_phone_verification: bool,
 *     vehicles: list<VehicleOption>,
 *     default_vehicle_selection: string,
 * }
 */
final class PublicLeadFormContactPrefill
{
    /**
     * @return ContactPrefill
     */
    public function forCurrentCustomer(): array
    {
        $customer = Auth::guard('portal')->user();

        if (! $customer instanceof Customer) {
            return $this->empty();
        }

        $phoneDisplay = PhoneNumber::display($customer->phone) ?? '';
        $preference = $customer->contact_preference instanceof LeadContactPreference
            ? $customer->contact_preference->value
            : LeadContactPreference::Text->value;
        $vehicles = $this->vehicleOptions($customer);

        return [
            'signed_in' => true,
            'first_name' => trim((string) $customer->first_name),
            'last_name' => trim((string) ($customer->last_name ?? '')),
            'phone' => $phoneDisplay,
            'email' => trim((string) ($customer->email ?? '')),
            'contact_preference' => $preference,
            'skips_phone_verification' => filled($customer->phone),
            'vehicles' => $vehicles,
            'default_vehicle_selection' => count($vehicles) === 1
                ? (string) $vehicles[0]['id']
                : '',
        ];
    }

    /**
     * Signed-in customers already proved phone ownership via account OTP.
     * Allow lead submit without a second Verify code when the submitted phone matches.
     */
    public function phoneMatchesSignedInCustomer(string $submittedPhone): bool
    {
        $customer = Auth::guard('portal')->user();

        if (! $customer instanceof Customer || blank($customer->phone)) {
            return false;
        }

        $submitted = PhoneNumber::normalize($submittedPhone);
        $onFile = PhoneNumber::normalize((string) $customer->phone);

        return $submitted !== null && $onFile !== null && $submitted === $onFile;
    }

    /**
     * Resolve a vehicle the signed-in customer owns. Guests and foreign IDs return null.
     */
    public function resolveOwnedVehicle(mixed $vehicleId): ?Vehicle
    {
        $customer = Auth::guard('portal')->user();

        if (! $customer instanceof Customer) {
            return null;
        }

        $id = filter_var($vehicleId, FILTER_VALIDATE_INT);

        if ($id === false || $id < 1) {
            return null;
        }

        return Vehicle::query()
            ->whereKey($id)
            ->where('customer_id', $customer->id)
            ->first();
    }

    /**
     * @return list<VehicleOption>
     */
    private function vehicleOptions(Customer $customer): array
    {
        return $customer->vehicles()
            ->orderByDesc('year')
            ->orderBy('make')
            ->orderBy('model')
            ->get()
            ->map(static function (Vehicle $vehicle): array {
                return [
                    'id' => (int) $vehicle->id,
                    'label' => $vehicle->display_name,
                    'year' => $vehicle->year !== null ? (int) $vehicle->year : null,
                    'make' => trim((string) ($vehicle->make ?? '')),
                    'model' => trim((string) ($vehicle->model ?? '')),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return ContactPrefill
     */
    private function empty(): array
    {
        return [
            'signed_in' => false,
            'first_name' => '',
            'last_name' => '',
            'phone' => '',
            'email' => '',
            'contact_preference' => LeadContactPreference::Text->value,
            'skips_phone_verification' => false,
            'vehicles' => [],
            'default_vehicle_selection' => '',
        ];
    }
}
