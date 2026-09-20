<?php

namespace App\Ark\Operations\Appointments;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\PhoneNumber;
use Illuminate\Validation\ValidationException;

/**
 * Central identity rule for Appointment as an independent booking record.
 *
 * Valid when either:
 * - a linked Customer exists, or
 * - usable booking-time contact snapshot exists (normally name + phone for staff UI).
 */
final class AppointmentBookingIdentity
{
    public static function assertValid(array $data, ?Customer $customer = null): void
    {
        if (! self::isSatisfied($data, $customer)) {
            throw ValidationException::withMessages([
                'contact_name' => 'Link a customer or enter a name and phone for this appointment.',
                'contact_phone' => 'Link a customer or enter a name and phone for this appointment.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function isSatisfied(array $data, ?Customer $customer = null): bool
    {
        if ($customer !== null || filled($data['customer_id'] ?? null)) {
            return true;
        }

        $name = trim((string) ($data['contact_name'] ?? ''));
        $phone = PhoneNumber::normalize((string) ($data['contact_phone'] ?? ''))
            ?? trim((string) ($data['contact_phone'] ?? ''));
        $email = strtolower(trim((string) ($data['contact_email'] ?? '')));

        // Staff unlinked booking: name + phone.
        if ($name !== '' && $phone !== '') {
            return true;
        }

        // Linked/internal paths may already identify via email alone when name is present
        // (e.g. website lead without phone). Keep this narrow.
        if ($name !== '' && $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return true;
        }

        return false;
    }

    public static function displayName(Appointment $appointment): string
    {
        $appointment->loadMissing('customer');

        $linked = trim(collect([
            $appointment->customer?->first_name,
            $appointment->customer?->last_name,
        ])->filter()->implode(' '));

        if ($linked !== '') {
            return $linked;
        }

        $snapshot = trim((string) ($appointment->contact_name ?? ''));

        return $snapshot !== '' ? $snapshot : 'Unknown contact';
    }

    public static function displayPhone(Appointment $appointment): ?string
    {
        $appointment->loadMissing('customer');

        $linked = trim((string) ($appointment->customer?->phone ?? ''));
        if ($linked !== '') {
            return $linked;
        }

        $snapshot = trim((string) ($appointment->contact_phone ?? ''));

        return $snapshot !== '' ? $snapshot : null;
    }

    public static function displayEmail(Appointment $appointment): ?string
    {
        $appointment->loadMissing('customer');

        $linked = trim((string) ($appointment->customer?->email ?? ''));
        if ($linked !== '') {
            return $linked;
        }

        $snapshot = trim((string) ($appointment->contact_email ?? ''));

        return $snapshot !== '' ? $snapshot : null;
    }

    /**
     * Create-time snapshots. May seed from linked Customer when contact fields are blank.
     *
     * @param  array<string, mixed>  $data
     * @return array{contact_name: ?string, contact_phone: ?string, contact_email: ?string}
     */
    public static function snapshotForCreate(array $data, ?Customer $customer = null): array
    {
        return self::snapshotFromInput($data, $customer);
    }

    /**
     * Update-time snapshots are appointment-owned.
     * Explicit contact fields in the request replace the booking snapshot.
     * Absent fields keep the existing snapshot — never silent Customer sync.
     *
     * @param  array<string, mixed>  $data
     * @return array{contact_name: ?string, contact_phone: ?string, contact_email: ?string}
     */
    public static function snapshotForUpdate(array $data, Appointment $appointment, bool $nameSubmitted, bool $phoneSubmitted, bool $emailSubmitted): array
    {
        $explicit = self::snapshotFromInput($data, null);

        return [
            'contact_name' => $nameSubmitted ? $explicit['contact_name'] : $appointment->contact_name,
            'contact_phone' => $phoneSubmitted ? $explicit['contact_phone'] : $appointment->contact_phone,
            'contact_email' => $emailSubmitted ? $explicit['contact_email'] : $appointment->contact_email,
        ];
    }

    /**
     * Normalize contact input. Pass $customer only on create to seed blanks from Customer.
     *
     * @param  array<string, mixed>  $data
     * @return array{contact_name: ?string, contact_phone: ?string, contact_email: ?string}
     */
    public static function snapshotFromInput(array $data, ?Customer $customer = null): array
    {
        $name = trim((string) ($data['contact_name'] ?? ''));
        $phoneRaw = trim((string) ($data['contact_phone'] ?? ''));
        $email = strtolower(trim((string) ($data['contact_email'] ?? '')));

        if ($customer !== null) {
            if ($name === '') {
                $name = trim(collect([$customer->first_name, $customer->last_name])->filter()->implode(' '));
            }
            if ($phoneRaw === '' && filled($customer->phone)) {
                $phoneRaw = (string) $customer->phone;
            }
            if ($email === '' && filled($customer->email)) {
                $email = strtolower(trim((string) $customer->email));
            }
        }

        $phone = $phoneRaw !== ''
            ? (PhoneNumber::normalize($phoneRaw) ?? $phoneRaw)
            : null;

        return [
            'contact_name' => $name !== '' ? $name : null,
            'contact_phone' => $phone,
            'contact_email' => $email !== '' ? $email : null,
        ];
    }
}
