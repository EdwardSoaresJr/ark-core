<?php

namespace App\Ark\Operations\Maintenance;

use App\Ark\Operations\Settings\ShopSettings;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Confirm Installed → append-only MaintenanceServiceEvent (+ optional superseding correction).
 */
final class ConfirmEngineOilInstalledAction
{
    /**
     * @param  array{
     *     oil_brand: string,
     *     viscosity: string,
     *     quantity_qt: string|float,
     *     filter_part: string,
     *     washer: string,
     *     service_mileage: int,
     *     reset_reminder?: bool,
     *     supersede_event_id?: int|null
     * }  $input
     */
    public function handle(MaintenanceService $service, User $actor, array $input): MaintenanceServiceEvent
    {
        if ($service->kind !== MaintenanceServiceKind::EngineOil) {
            throw ValidationException::withMessages([
                'kind' => 'Only Engine Oil Service can use this confirm path.',
            ]);
        }

        if ($service->status === MaintenanceServiceStatus::Cancelled) {
            throw ValidationException::withMessages([
                'service' => 'This maintenance service was cancelled.',
            ]);
        }

        $washer = MaintenanceWasherState::tryFrom((string) $input['washer']);
        if ($washer === null || ! in_array($washer, [
            MaintenanceWasherState::Installed,
            MaintenanceWasherState::NotRequired,
            MaintenanceWasherState::NotReplaced,
        ], true)) {
            throw ValidationException::withMessages([
                'washer' => 'Choose Installed, Not Required, or Not Replaced.',
            ]);
        }

        $oilBrand = trim((string) $input['oil_brand']);
        $viscosity = trim((string) $input['viscosity']);
        $filterPart = trim((string) $input['filter_part']);
        $quantity = (string) $input['quantity_qt'];
        $mileage = (int) $input['service_mileage'];

        if ($oilBrand === '' || $viscosity === '' || $filterPart === '') {
            throw ValidationException::withMessages([
                'oil_brand' => 'Brand, viscosity, and filter are required to confirm installed.',
            ]);
        }

        if ($mileage <= 0) {
            throw ValidationException::withMessages([
                'service_mileage' => 'Enter the service mileage.',
            ]);
        }

        $interval = (int) (ShopSettings::current()->oil_change_interval_miles
            ?: config('vehicle_maintenance_intervals.intervals.oil.interval', 5000));
        $nextDue = $mileage + max(1000, $interval);
        $resetReminder = (bool) ($input['reset_reminder'] ?? $service->reset_reminder);

        return DB::transaction(function () use (
            $service,
            $actor,
            $oilBrand,
            $viscosity,
            $filterPart,
            $quantity,
            $washer,
            $mileage,
            $nextDue,
            $resetReminder,
            $input,
        ): MaintenanceServiceEvent {
            $service = MaintenanceService::query()->lockForUpdate()->findOrFail($service->id);

            $supersedeId = isset($input['supersede_event_id']) ? (int) $input['supersede_event_id'] : null;
            $prior = null;

            if ($supersedeId !== null && $supersedeId > 0) {
                $prior = MaintenanceServiceEvent::query()
                    ->whereKey($supersedeId)
                    ->where('maintenance_service_id', $service->id)
                    ->whereNull('superseded_by_event_id')
                    ->lockForUpdate()
                    ->first();

                if ($prior === null) {
                    throw ValidationException::withMessages([
                        'supersede_event_id' => 'That service event cannot be corrected.',
                    ]);
                }

                $sequence = (int) $prior->service_sequence;
                $revision = (int) $prior->revision + 1;
            } else {
                if ($service->current_event_id !== null) {
                    throw ValidationException::withMessages([
                        'service' => 'Already confirmed. Use correction to supersede the event.',
                    ]);
                }

                $maxSequence = (int) MaintenanceServiceEvent::query()
                    ->where('vehicle_id', $service->vehicle_id)
                    ->where('kind', MaintenanceServiceKind::EngineOil->value)
                    ->max('service_sequence');

                $sequence = $maxSequence + 1;
                $revision = 0;
            }

            $event = MaintenanceServiceEvent::query()->create([
                'maintenance_service_id' => $service->id,
                'repair_order_id' => $service->repair_order_id,
                'vehicle_id' => $service->vehicle_id,
                'kind' => MaintenanceServiceKind::EngineOil,
                'service_sequence' => $sequence,
                'revision' => $revision,
                'superseded_by_event_id' => null,
                'confirmed_by_user_id' => $actor->id,
                'confirmed_at' => now(),
                'service_mileage' => $mileage,
                'next_due_mileage' => $nextDue,
                'oil_brand' => $oilBrand,
                'viscosity' => $viscosity,
                'quantity_qt' => $quantity,
                'filter_part' => $filterPart,
                'washer' => $washer,
                'reset_reminder' => $resetReminder,
            ]);

            if ($prior !== null) {
                $prior->update(['superseded_by_event_id' => $event->id]);
            }

            $service->update([
                'status' => MaintenanceServiceStatus::Confirmed,
                'current_event_id' => $event->id,
                'prepared_oil_brand' => $oilBrand,
                'prepared_viscosity' => $viscosity,
                'prepared_quantity_qt' => $quantity,
                'prepared_filter_part' => $filterPart,
                'prepared_washer' => MaintenanceWasherState::Include,
                'reset_reminder' => $resetReminder,
            ]);

            return $event->fresh() ?? throw new RuntimeException('MaintenanceServiceEvent missing after confirm.');
        });
    }
}
