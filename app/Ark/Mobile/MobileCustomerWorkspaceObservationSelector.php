<?php

namespace App\Ark\Mobile;

use App\Ark\Operations\Observations\OperationalObservationType;
use App\Ark\Operations\Timeline\OperationalEventEntry;
use App\Ark\Operations\Timeline\OperationalEventKind;
use Illuminate\Support\Collection;

/**
 * Selects which operational observation orients the customer workspace.
 *
 * Target: consume the operational observation stream. Today: infer from timeline reads
 * while emission catches up. Browse (null) is not an observation — neutral workspace entry.
 */
final class MobileCustomerWorkspaceObservationSelector
{
    /**
     * @param  Collection<int, OperationalEventEntry>  $timeline
     */
    public function select(
        ?string $requestedObservation,
        Collection $timeline,
        bool $hasOpenRepairOrder = false,
    ): ?OperationalObservationType {
        $requested = OperationalObservationType::tryFromSurfaceRequest($requestedObservation);

        if ($requested !== null) {
            return $requested;
        }

        if ($requestedObservation !== null && $requestedObservation !== '' && $requestedObservation !== 'browse') {
            $direct = OperationalObservationType::tryFrom($requestedObservation);

            if ($direct !== null) {
                return $direct;
            }
        }

        return $this->inferFromTimeline($timeline, $hasOpenRepairOrder);
    }

    /**
     * @param  Collection<int, OperationalEventEntry>  $timeline
     */
    private function inferFromTimeline(Collection $timeline, bool $hasOpenRepairOrder): ?OperationalObservationType
    {
        /** @var OperationalEventEntry|null $latest */
        $latest = $timeline->first();

        if ($latest === null) {
            return $hasOpenRepairOrder
                ? OperationalObservationType::RepairOrderWaiting
                : null;
        }

        return match ($latest->kind) {
            OperationalEventKind::Sms => OperationalObservationType::CustomerReplied,
            OperationalEventKind::MissedCall,
            OperationalEventKind::Voicemail,
            OperationalEventKind::Call => OperationalObservationType::IncomingCall,
            OperationalEventKind::EstimateViewed => OperationalObservationType::EstimateViewed,
            OperationalEventKind::Payment,
            OperationalEventKind::PortalActivity,
            OperationalEventKind::Approval => OperationalObservationType::PaymentReceived,
            OperationalEventKind::Appointment => OperationalObservationType::AppointmentUpcoming,
            OperationalEventKind::StatusChange,
            OperationalEventKind::Inspection,
            OperationalEventKind::VehicleStatus => OperationalObservationType::RepairOrderWaiting,
            default => null,
        };
    }
}
