<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException as AccessDenied;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RecordAuthorizationExceptionAction
{
    public function __construct(
        private readonly OperationalEventRecorder $events,
    ) {}

    public static function actorMayRecord(?User $actor): bool
    {
        return $actor !== null && $actor->hasAnyRole([
            ArkRole::Admin->value,
            ArkRole::Advisor->value,
        ]);
    }

    /**
     * @param  list<int>  $lineIds
     */
    public function execute(
        RepairOrder $repairOrder,
        RepairOrderConcern $concern,
        array $lineIds,
        AuthorizationExceptionReason $reason,
        string $note,
        User $actor,
    ): AuthorizationException {
        if (! self::actorMayRecord($actor)) {
            throw new AccessDenied('Recording an exception requires an advisor or admin.');
        }

        if ((int) $concern->repair_order_id !== (int) $repairOrder->id) {
            throw ValidationException::withMessages([
                'concern' => 'That concern is not on this repair order.',
            ]);
        }

        $note = AuthorizationExceptionNote::assertSubstantive($note);
        $lineIds = $this->scopedLineIds($concern, $lineIds);

        return DB::transaction(function () use ($repairOrder, $concern, $lineIds, $reason, $note, $actor): AuthorizationException {
            $exception = AuthorizationException::query()->create([
                'repair_order_id' => $repairOrder->id,
                'repair_order_concern_id' => $concern->id,
                'reason' => $reason,
                'note' => $note,
                'line_ids' => $lineIds,
                'recorded_by_user_id' => $actor->id,
            ]);

            $this->events->record(
                OperationalEventName::AuthorizationExceptionRecorded,
                $repairOrder,
                actor: $actor,
                payload: [
                    'exception_id' => $exception->id,
                    'concern_id' => $concern->id,
                    'line_ids' => $lineIds,
                    'reason' => $reason->value,
                    'establishes_customer_consent' => false,
                ],
            );

            return $exception->fresh();
        });
    }

    /**
     * @param  list<int>  $lineIds
     * @return list<int>
     */
    private function scopedLineIds(RepairOrderConcern $concern, array $lineIds): array
    {
        $lineIds = array_values(array_unique(array_map(static fn (mixed $id): int => (int) $id, $lineIds)));

        if ($lineIds === []) {
            throw ValidationException::withMessages([
                'line_ids' => 'Select the labor or parts this exception covers.',
            ]);
        }

        $concern->loadMissing('lines');

        $allowed = $concern->linesEligibleForAuthorizationException()
            ->map(fn (RepairOrderLine $line): int => (int) $line->id)
            ->all();

        $unknown = array_values(array_diff($lineIds, $allowed));

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'line_ids' => 'An exception can cover only labor and parts on this concern.',
            ]);
        }

        return $lineIds;
    }
}
