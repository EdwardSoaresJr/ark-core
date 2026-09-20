<?php

namespace App\Ark\Operations\Customers\Recognition;

use App\Ark\Operations\Appointments\Appointment;
use App\Ark\Operations\Appointments\AppointmentStatus;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Leads\LeadContactPreference;
use App\Ark\Operations\Maintenance\VehicleEngineOilHistoryProjection;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Vehicles\Vehicle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Disposable recognition projection for customer-facing surfaces.
 * Observes authority only — never mutates customer, vehicle, RO, maintenance, or appointments.
 *
 * @phpstan-type RecognitionVehicle array{
 *     id: int,
 *     label: string,
 *     year: int|null,
 *     make: string,
 *     model: string,
 * }
 * @phpstan-type RadarItem array{
 *     concern_id: int,
 *     repair_order_id: int,
 *     summary: string,
 * }
 * @phpstan-type ContextSignal array{
 *     key: string,
 *     label: string,
 * }
 */
final class CustomerRecognitionProjection
{
    public const SESSION_GUEST_KEY = 'book_as_guest';

    public function __construct(
        private readonly VehicleEngineOilHistoryProjection $oilHistory,
    ) {}

    /**
     * @return array{
     *     customer: array{
     *         id: int,
     *         first_name: string,
     *         last_name: string,
     *         phone: string,
     *         email: string,
     *         contact_preference: string,
     *     },
     *     vehicles: list<RecognitionVehicle>,
     * }
     */
    public function forCustomer(Customer $customer): array
    {
        return [
            'customer' => $this->customerSection($customer),
            'vehicles' => $this->vehicleOptions($customer),
        ];
    }

    /**
     * Vehicle Home + schedule prep. Relationship = historical; Context = live.
     *
     * @return array{
     *     customer: array{
     *         id: int,
     *         first_name: string,
     *         last_name: string,
     *         phone: string,
     *         email: string,
     *         contact_preference: string,
     *     },
     *     vehicle: RecognitionVehicle,
     *     relationship: array{
     *         last_service_label: string|null,
     *         oil_label: string|null,
     *         still_on_radar: list<RadarItem>,
     *     },
     *     context: list<ContextSignal>,
     * }
     */
    public function forVehicle(Customer $customer, Vehicle $vehicle): array
    {
        abort_unless((int) $vehicle->customer_id === (int) $customer->id, 404);

        $lastVisit = $this->lastClosedVisit($vehicle);
        $oil = $this->latestOilService($vehicle);

        return [
            'customer' => $this->customerSection($customer),
            'vehicle' => $this->vehicleOption($vehicle),
            'relationship' => [
                'last_here_label' => $this->lastHereLabel($lastVisit),
                'last_service_label' => $this->lastServiceLabelFromVisit($lastVisit),
                'oil_label' => $oil['combined_label'],
                'last_service_lines' => $oil['lines'],
                'still_on_radar' => $this->stillOnRadar($vehicle),
            ],
            'context' => $this->contextSignals($customer, $vehicle),
        ];
    }

    /**
     * Resolve owned vehicle for a signed-in customer, or null.
     */
    public function resolveOwnedVehicle(Customer $customer, mixed $vehicleId): ?Vehicle
    {
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
     * @return array{
     *     id: int,
     *     first_name: string,
     *     last_name: string,
     *     phone: string,
     *     email: string,
     *     contact_preference: string,
     * }
     */
    private function customerSection(Customer $customer): array
    {
        $preference = $customer->contact_preference instanceof LeadContactPreference
            ? $customer->contact_preference->value
            : LeadContactPreference::Text->value;

        return [
            'id' => (int) $customer->id,
            'first_name' => trim((string) $customer->first_name),
            'last_name' => trim((string) ($customer->last_name ?? '')),
            'phone' => PhoneNumber::display($customer->phone) ?? '',
            'email' => trim((string) ($customer->email ?? '')),
            'contact_preference' => $preference,
        ];
    }

    /**
     * @return list<RecognitionVehicle>
     */
    private function vehicleOptions(Customer $customer): array
    {
        return $customer->vehicles()
            ->orderByDesc('year')
            ->orderBy('make')
            ->orderBy('model')
            ->get()
            ->map(fn (Vehicle $vehicle): array => $this->vehicleOption($vehicle))
            ->values()
            ->all();
    }

    /**
     * @return RecognitionVehicle
     */
    private function vehicleOption(Vehicle $vehicle): array
    {
        return [
            'id' => (int) $vehicle->id,
            'label' => $vehicle->display_name,
            'year' => $vehicle->year !== null ? (int) $vehicle->year : null,
            'make' => trim((string) ($vehicle->make ?? '')),
            'model' => trim((string) ($vehicle->model ?? '')),
        ];
    }

    private function lastClosedVisit(Vehicle $vehicle): ?RepairOrder
    {
        return RepairOrder::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('status', RepairOrderStatus::Closed->value)
            ->whereNotNull('closed_at')
            ->orderByDesc('closed_at')
            ->first();
    }

    private function lastHereLabel(?RepairOrder $closed): ?string
    {
        if ($closed?->closed_at === null) {
            return null;
        }

        $when = ShopDisplayTimezone::present($closed->closed_at);
        $days = (int) $when->startOfDay()->diffInDays(ShopDisplayTimezone::now()->startOfDay());

        if ($days <= 0) {
            return 'Last here today';
        }

        if ($days === 1) {
            return 'Last here yesterday';
        }

        return 'Last here '.$days.' days ago';
    }

    private function lastServiceLabelFromVisit(?RepairOrder $closed): ?string
    {
        if ($closed?->closed_at === null) {
            return null;
        }

        $when = ShopDisplayTimezone::present($closed->closed_at);

        return 'Last visit · '.$when->format('M j, Y');
    }

    /**
     * @return array{combined_label: string|null, lines: list<string>}
     */
    private function latestOilService(Vehicle $vehicle): array
    {
        $history = $this->oilHistory->forVehicle((int) $vehicle->id);
        $latest = $history[0] ?? null;

        if (! is_array($latest)) {
            return ['combined_label' => null, 'lines' => []];
        }

        $oilLine = trim(implode(' ', array_values(array_filter([
            filled($latest['oil_brand'] ?? null) ? (string) $latest['oil_brand'] : null,
            filled($latest['viscosity'] ?? null) ? (string) $latest['viscosity'] : null,
        ]))));

        $filterLine = filled($latest['filter_part'] ?? null)
            ? trim((string) $latest['filter_part'])
            : '';

        $lines = array_values(array_filter([$oilLine !== '' ? $oilLine : null, $filterLine !== '' ? $filterLine : null]));

        if ($lines === []) {
            return ['combined_label' => null, 'lines' => []];
        }

        return [
            'combined_label' => 'Oil installed · '.implode(' · ', $lines),
            'lines' => $lines,
        ];
    }

    /**
     * Deferred concerns only — current follow-up authority.
     * Never declined, never invent from recommendations without Deferred disposition.
     *
     * @return list<RadarItem>
     */
    private function stillOnRadar(Vehicle $vehicle): array
    {
        /** @var Collection<int, RepairOrder> $orders */
        $orders = RepairOrder::query()
            ->where('vehicle_id', $vehicle->id)
            ->with(['concerns' => static function ($query): void {
                $query->orderBy('position')->orderBy('id');
            }])
            ->orderByDesc('id')
            ->get();

        $items = [];

        foreach ($orders as $order) {
            foreach ($order->futureWorkConcerns() as $concern) {
                $summary = $this->customerSafeRadarSummary($concern);

                if ($summary === '') {
                    continue;
                }

                $items[] = [
                    'concern_id' => (int) $concern->id,
                    'repair_order_id' => (int) $order->id,
                    'summary' => $summary,
                ];

                if (count($items) >= 8) {
                    return $items;
                }
            }
        }

        return $items;
    }

    private function customerSafeRadarSummary(RepairOrderConcern $concern): string
    {
        $summary = trim((string) $concern->summary);

        if ($summary === '') {
            return '';
        }

        // Labels only — strip dollar amounts advisors may have typed into summaries.
        $summary = preg_replace('/\$\s*\d[\d,]*(?:\.\d{2})?/', '', $summary) ?? $summary;
        $summary = trim(preg_replace('/\s+/', ' ', $summary) ?? $summary);

        return mb_strlen($summary) > 120 ? mb_substr($summary, 0, 117).'…' : $summary;
    }

    /**
     * @return list<ContextSignal>
     */
    private function contextSignals(Customer $customer, Vehicle $vehicle): array
    {
        $signals = [];

        $open = RepairOrder::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('status', '!=', RepairOrderStatus::Closed->value)
            ->orderByDesc('id')
            ->first();

        if ($open !== null) {
            $signals[] = [
                'key' => 'open_visit',
                'label' => $this->openVisitLabel($open),
            ];
        }

        $tomorrow = $this->upcomingAppointmentLabel($customer, $vehicle);

        if ($tomorrow !== null) {
            $signals[] = [
                'key' => 'upcoming_appointment',
                'label' => $tomorrow,
            ];
        }

        return $signals;
    }

    private function openVisitLabel(RepairOrder $order): string
    {
        return match ($order->status) {
            RepairOrderStatus::ReadyPickup, RepairOrderStatus::Completed, RepairOrderStatus::Invoiced => 'Ready for pickup',
            RepairOrderStatus::WaitingApproval => 'Waiting on your approval',
            RepairOrderStatus::WaitingParts => 'With us · waiting on parts',
            RepairOrderStatus::InProgress, RepairOrderStatus::QualityCheck, RepairOrderStatus::ReadyForWork, RepairOrderStatus::Approved => 'With us now',
            default => 'With us now',
        };
    }

    private function upcomingAppointmentLabel(Customer $customer, Vehicle $vehicle): ?string
    {
        try {
            $now = ShopDisplayTimezone::now();
        } catch (\Throwable) {
            return null;
        }

        $windowEnd = $now->copy()->addDays(2)->endOfDay();

        $appointment = Appointment::query()
            ->where('customer_id', $customer->id)
            ->where('vehicle_id', $vehicle->id)
            ->whereNull('canceled_at')
            ->whereIn('status', [
                AppointmentStatus::Scheduled->value,
                AppointmentStatus::Confirmed->value,
            ])
            ->where('starts_at', '>=', $now->copy()->startOfDay()->utc())
            ->where('starts_at', '<=', $windowEnd->copy()->utc())
            ->orderBy('starts_at')
            ->first();

        if ($appointment?->starts_at === null) {
            return null;
        }

        $starts = ShopDisplayTimezone::present($appointment->starts_at);

        if ($starts->isSameDay($now)) {
            return 'Appointment today · '.$starts->format('g:i A');
        }

        if ($starts->isSameDay($now->copy()->addDay())) {
            return 'Appointment tomorrow · '.$starts->format('g:i A');
        }

        return 'Upcoming appointment · '.$starts->format('D g:i A');
    }
}
