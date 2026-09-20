<?php

namespace App\Ark\Operations\WorkAuthorization;

use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Record Testing Package outcome — ends with an answer, not a repair.
 */
final class RecordTestingPackageOutcomeAction
{
    public function __construct(
        private readonly OperationalEventRecorder $events,
    ) {}

    public function handle(
        WorkAuthorization $authorization,
        User $actor,
        TestingPackageOutcome $outcome,
        ?string $recommendation = null,
    ): WorkAuthorization {
        if ($authorization->package_type !== WorkAuthorizationPackageType::Testing) {
            throw ValidationException::withMessages([
                'work_authorization' => 'Only Testing Packages record testing outcomes.',
            ]);
        }

        if ($authorization->status === WorkAuthorizationStatus::Completed) {
            throw ValidationException::withMessages([
                'outcome' => 'This Testing Package already has an outcome.',
            ]);
        }

        if ($authorization->status === WorkAuthorizationStatus::Cancelled) {
            throw ValidationException::withMessages([
                'outcome' => 'Cancelled authorizations cannot record an outcome.',
            ]);
        }

        $recommendation = filled($recommendation) ? trim((string) $recommendation) : null;

        if (
            in_array($outcome, [
                TestingPackageOutcome::RepairRecommended,
                TestingPackageOutcome::EscalateTesting,
            ], true)
            && $recommendation === null
        ) {
            throw ValidationException::withMessages([
                'recommendation' => 'Add a short recommendation when the outcome needs a next step.',
            ]);
        }

        return DB::transaction(function () use ($authorization, $actor, $outcome, $recommendation): WorkAuthorization {
            $authorization->forceFill([
                'status' => WorkAuthorizationStatus::Completed,
                'outcome' => $outcome,
                'recommendation' => $recommendation,
                'completed_by_user_id' => $actor->id,
                'completed_at' => now(),
            ])->save();

            /** @var RepairOrder $repairOrder */
            $repairOrder = $authorization->repairOrder;

            $this->events->record(
                OperationalEventName::WorkAuthorizationCompleted,
                $repairOrder,
                actor: $actor,
                payload: [
                    'work_authorization_id' => $authorization->id,
                    'package_type' => WorkAuthorizationPackageType::Testing->value,
                    'outcome' => $outcome->value,
                    'recommendation' => $recommendation,
                ],
            );

            return $authorization->fresh(['concern', 'workGroup', 'packageLine'])
                ?? throw new \RuntimeException('Work authorization missing after outcome.');
        });
    }
}
