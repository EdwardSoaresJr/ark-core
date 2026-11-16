<?php

namespace App\Ark\Operations\Search;

use App\Ark\Operations\Appointments\Appointment;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Customers\CustomerSearchQuery;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Operations\Vehicles\VehicleSearchQuery;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * One search everywhere - customer · plate · VIN · phone · RO · estimate · appointment.
 * Projection only; opens existing operational destinations.
 */
final class OperationsGlobalSearchProjection
{
    /**
     * @return array{
     *     query: string,
     *     results: list<array{
     *         type: string,
     *         label: string,
     *         detail: string|null,
     *         url: string,
     *         compose_customer_id: int|null,
     *         compose_phone: string|null,
     *         compose_email: string|null
     *     }>,
     *     count: int
     * }
     */
    public function forQuery(string $query): array
    {
        $query = trim($query);

        if ($query === '' || mb_strlen($query) < 2) {
            return ['query' => $query, 'results' => [], 'count' => 0];
        }

        $results = [];

        foreach (CustomerSearchQuery::matching($query, 8) as $customer) {
            /** @var Customer $customer */
            $results[] = [
                'type' => 'customer',
                'label' => (string) $customer->name,
                'detail' => $customer->phone ?: $customer->email,
                'url' => route('operations.customers.show', $customer),
                'compose_customer_id' => (int) $customer->id,
                'compose_phone' => null,
                'compose_email' => null,
            ];
        }

        foreach ($this->contactComposeRows($query, $results) as $contactRow) {
            array_unshift($results, $contactRow);
        }

        foreach (VehicleSearchQuery::matching($query, 6) as $vehicle) {
            /** @var Vehicle $vehicle */
            $results[] = [
                'type' => 'vehicle',
                'label' => (string) $vehicle->display_name,
                'detail' => trim(implode(' · ', array_filter([
                    $vehicle->plate,
                    $vehicle->vin,
                    $vehicle->customer?->name,
                ]))),
                'url' => $vehicle->customer_id !== null
                    ? route('operations.customers.show', $vehicle->customer_id)
                    : route('operations.customers.search', ['q' => $vehicle->plate ?: $vehicle->vin ?: $vehicle->display_name]),
                'compose_customer_id' => $vehicle->customer_id !== null ? (int) $vehicle->customer_id : null,
                'compose_phone' => null,
                'compose_email' => null,
            ];
        }

        if (preg_match('/^#?(\d+)$/', $query, $matches) === 1) {
            $ro = RepairOrder::query()
                ->with(['customer:id,first_name,last_name', 'vehicle:id,year,make,model'])
                ->find((int) $matches[1]);

            if ($ro !== null) {
                $results[] = [
                    'type' => 'repair_order',
                    'label' => '#'.$ro->repair_order_id,
                    'detail' => trim(implode(' · ', array_filter([
                        $ro->customer?->name,
                        $ro->vehicle?->display_name,
                        $ro->statusDisplayLabel(),
                    ]))),
                    'url' => route('operations.repair-orders.show', $ro),
                    'compose_customer_id' => $ro->customer_id !== null ? (int) $ro->customer_id : null,
                    'compose_phone' => null,
                    'compose_email' => null,
                ];
            }
        }

        $like = '%'.$query.'%';
        foreach (
            RepairOrder::query()
                ->with(['customer:id,first_name,last_name', 'vehicle:id,year,make,model'])
                ->where(function ($builder) use ($like): void {
                    $builder
                        ->where('concern_summary', 'like', $like)
                        ->orWhereHas('concerns', fn ($concerns) => $concerns->where('summary', 'like', $like));
                })
                ->latest('updated_at')
                ->limit(4)
                ->get() as $ro
        ) {
            $results[] = [
                'type' => 'repair_order',
                'label' => '#'.$ro->repair_order_id,
                'detail' => trim(implode(' · ', array_filter([
                    $ro->customer?->name,
                    $ro->vehicle?->display_name,
                    $ro->concern_summary,
                ]))),
                'url' => route('operations.repair-orders.show', $ro),
                'compose_customer_id' => $ro->customer_id !== null ? (int) $ro->customer_id : null,
                'compose_phone' => null,
                'compose_email' => null,
            ];
        }

        if (Schema::hasTable('appointments')) {
            foreach (
                Appointment::query()
                    ->with(['customer:id,first_name,last_name', 'vehicle:id,year,make,model'])
                    ->where(function ($builder) use ($like, $query): void {
                        $builder->where('notes', 'like', $like);
                        if (preg_match('/^\d+$/', $query) === 1) {
                            $builder->orWhere('id', (int) $query);
                        }
                        $builder->orWhereHas('customer', function ($customers) use ($like): void {
                            $customers
                                ->where('first_name', 'like', $like)
                                ->orWhere('last_name', 'like', $like);
                        });
                    })
                    ->where('starts_at', '>=', now()->subDays(7))
                    ->orderBy('starts_at')
                    ->limit(4)
                    ->get() as $appointment
            ) {
                $results[] = [
                    'type' => 'appointment',
                    'label' => 'Appointment · '.($appointment->starts_at?->timezone(config('app.display_timezone'))->format('M j g:i A') ?? ''),
                    'detail' => trim(implode(' · ', array_filter([
                        $appointment->displayName(),
                        $appointment->vehicle?->display_name,
                    ]))),
                    'url' => route('operations.appointments.show', $appointment),
                    'compose_customer_id' => $appointment->customer_id !== null ? (int) $appointment->customer_id : null,
                    'compose_phone' => null,
                    'compose_email' => null,
                ];
            }
        }

        $deduped = [];
        $seen = [];
        foreach ($results as $row) {
            $key = $row['type'].':'.$row['url'].':'.($row['compose_phone'] ?? '').':'.($row['compose_email'] ?? '');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $deduped[] = $row;
        }

        $deduped = array_slice($deduped, 0, 20);

        return [
            'query' => $query,
            'results' => $deduped,
            'count' => count($deduped),
        ];
    }

    /**
     * Offer compose-to-contact when the query itself is a phone or email, without creating a customer.
     *
     * @param  list<array{type: string, compose_customer_id: int|null, detail: string|null}>  $existing
     * @return list<array{
     *     type: string,
     *     label: string,
     *     detail: string|null,
     *     url: string,
     *     compose_customer_id: int|null,
     *     compose_phone: string|null,
     *     compose_email: string|null
     * }>
     */
    private function contactComposeRows(string $query, array $existing): array
    {
        $rows = [];
        $normalizedPhone = PhoneNumber::normalize($query);
        $digits = $normalizedPhone !== null ? PhoneNumber::digits($normalizedPhone) : '';

        if (strlen($digits) >= 10) {
            $alreadyCustomerPhone = collect($existing)->contains(function (array $row) use ($digits): bool {
                if (($row['type'] ?? null) !== 'customer' || ($row['compose_customer_id'] ?? null) === null) {
                    return false;
                }

                return PhoneNumber::digits((string) ($row['detail'] ?? '')) === $digits
                    || PhoneNumber::normalize((string) ($row['detail'] ?? '')) === $digits;
            });

            if (! $alreadyCustomerPhone) {
                $display = PhoneNumber::display($normalizedPhone) ?? $query;
                $rows[] = [
                    'type' => 'message',
                    'label' => 'Message '.$display,
                    'detail' => 'Text without a customer record',
                    'url' => route('operations.communications.compose.create', ['phone' => $normalizedPhone]),
                    'compose_customer_id' => null,
                    'compose_phone' => $normalizedPhone,
                    'compose_email' => null,
                ];
            }
        }

        $email = Str::lower(trim($query));
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $alreadyCustomerEmail = collect($existing)->contains(function (array $row) use ($email): bool {
                if (($row['type'] ?? null) !== 'customer' || ($row['compose_customer_id'] ?? null) === null) {
                    return false;
                }

                return Str::lower(trim((string) ($row['detail'] ?? ''))) === $email;
            });

            if (! $alreadyCustomerEmail) {
                $rows[] = [
                    'type' => 'message',
                    'label' => 'Email '.$email,
                    'detail' => 'Email without a customer record',
                    'url' => route('operations.communications.compose.create', ['email' => $email]),
                    'compose_customer_id' => null,
                    'compose_phone' => null,
                    'compose_email' => $email,
                ];
            }
        }

        return $rows;
    }
}
