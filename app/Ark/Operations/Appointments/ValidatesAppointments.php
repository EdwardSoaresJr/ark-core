<?php

namespace App\Ark\Operations\Appointments;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Vehicles\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

trait ValidatesAppointments
{
    /**
     * @return array<string, mixed>
     */
    protected function appointmentRules(?Appointment $appointment = null): array
    {
        return [
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:32'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'lead_id' => ['nullable', 'integer', 'exists:leads,id'],
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
            'advisor_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'technician_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'workstation_id' => ['nullable', 'integer', 'exists:workstations,id'],
            'repair_order_id' => ['nullable', 'integer', 'exists:repair_orders,id'],
            'repair_order_shop_number' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'starts_date' => ['nullable', 'date'],
            'starts_time' => ['nullable', 'date_format:H:i'],
            'ends_date' => ['nullable', 'date'],
            'ends_time' => ['nullable', 'date_format:H:i'],
            'duration_minutes' => ['nullable', 'integer', 'min:15', 'max:480'],
            'estimated_labor_hours' => ['nullable', 'numeric', 'min:0.25', 'max:24'],
            'concern' => ['required', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', Rule::enum(AppointmentStatus::class)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedAppointment(Request $request, ?Appointment $appointment = null): array
    {
        return $request->validate($this->appointmentRules($appointment));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: array<string, mixed>, 1: list<string>}
     */
    protected function normalizeAppointmentInput(array $data, Request $request, ?Appointment $appointment = null): array
    {
        $data = $this->mergeSplitDatetimes($data);

        if (! empty($data['repair_order_id'])) {
            $repairOrder = RepairOrder::query()->findOrFail((int) $data['repair_order_id']);
            $data['customer_id'] ??= $repairOrder->customer_id;
            $data['vehicle_id'] ??= $repairOrder->vehicle_id;
            unset($data['repair_order_shop_number']);
        } elseif (isset($data['repair_order_shop_number'])) {
            $repairOrder = RepairOrder::query()
                ->where('repair_order_id', $data['repair_order_shop_number'])
                ->firstOrFail();
            $data['repair_order_id'] = $repairOrder->id;
            unset($data['repair_order_shop_number']);
            $data['customer_id'] ??= $repairOrder->customer_id;
            $data['vehicle_id'] ??= $repairOrder->vehicle_id;
        } else {
            unset($data['repair_order_shop_number']);
        }

        unset($data['starts_date'], $data['starts_time'], $data['ends_date'], $data['ends_time'], $data['duration_minutes']);

        $customer = null;
        if (filled($data['customer_id'] ?? null)) {
            $customer = Customer::query()->find((int) $data['customer_id']);
        }

        if ($appointment !== null) {
            $snapshots = AppointmentBookingIdentity::snapshotForUpdate(
                $data,
                $appointment,
                $request->exists('contact_name'),
                $request->exists('contact_phone'),
                $request->exists('contact_email'),
            );
        } else {
            $snapshots = AppointmentBookingIdentity::snapshotForCreate($data, $customer);
        }

        $data['contact_name'] = $snapshots['contact_name'];
        $data['contact_phone'] = $snapshots['contact_phone'];
        $data['contact_email'] = $snapshots['contact_email'];

        if (! filled($data['customer_id'] ?? null)) {
            $data['customer_id'] = null;
        }

        if (! filled($data['lead_id'] ?? null)) {
            unset($data['lead_id']);
        }

        AppointmentBookingIdentity::assertValid($data, $customer);

        if (isset($data['vehicle_id'], $data['customer_id']) && filled($data['customer_id'])) {
            Vehicle::query()
                ->whereKey($data['vehicle_id'])
                ->where('customer_id', $data['customer_id'])
                ->firstOrFail();
        }

        if (! isset($data['status'])) {
            $data['status'] = AppointmentStatus::Scheduled;
        } elseif (is_string($data['status'])) {
            $data['status'] = AppointmentStatus::from($data['status']);
        }

        $data = app(AppointmentScheduleGuard::class)->assertSchedulable($data, $appointment);
        $warnings = $data['_schedule_warnings'] ?? [];
        unset($data['_schedule_warnings']);

        return [$data, $warnings];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function mergeSplitDatetimes(array $data): array
    {
        if (filled($data['starts_date'] ?? null) && filled($data['starts_time'] ?? null) && trim((string) $data['starts_time']) !== '') {
            $data['starts_at'] = $data['starts_date'].'T'.$data['starts_time'];
        }

        if (filled($data['ends_date'] ?? null) && filled($data['ends_time'] ?? null)) {
            $data['ends_at'] = $data['ends_date'].'T'.$data['ends_time'];
        }

        if (! filled($data['starts_at'] ?? null)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'starts_at' => 'Appointment day and time are required.',
                'starts_time' => 'Choose an exact appointment time.',
            ]);
        }

        $durationMinutes = isset($data['duration_minutes']) ? (int) $data['duration_minutes'] : null;
        if ($durationMinutes !== null && $durationMinutes > 0) {
            $durationMinutes = AppointmentSlotMinutes::snapDurationMinutes($durationMinutes);
            $start = \Illuminate\Support\Carbon::parse((string) $data['starts_at']);
            $data['ends_at'] = $start->copy()->addMinutes($durationMinutes)->format('Y-m-d\TH:i');
        }

        if (! filled($data['ends_at'] ?? null)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'duration_minutes' => 'Suggested appointment length is required.',
            ]);
        }

        if (strtotime((string) $data['ends_at']) <= strtotime((string) $data['starts_at'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'duration_minutes' => 'Suggested length must be greater than zero.',
            ]);
        }

        return $data;
    }
}
