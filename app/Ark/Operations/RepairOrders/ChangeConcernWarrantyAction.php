<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\EstimatePricing\BillingClassWarranty;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Concern-level warranty override. Independent of billing class; reversible to inheritance.
 */
final class ChangeConcernWarrantyAction
{
    public function __construct(
        private readonly OperationalEventRecorder $events,
    ) {}

    /**
     * @param  array<string, mixed>|null  $warranty
     */
    public function override(RepairOrderConcern $concern, ?array $warranty, ?User $actor = null): RepairOrderConcern
    {
        $parsed = BillingClassWarranty::fromArray($warranty);
        if ($parsed->isEmpty() && ! $parsed->coversParts && ! $parsed->coversLabor && $parsed->description === '') {
            throw ValidationException::withMessages([
                'warranty' => 'Enter warranty coverage for this concern override.',
            ]);
        }

        return $this->persist($concern, false, $parsed->toArray(), $actor, 'override');
    }

    public function inherit(RepairOrderConcern $concern, ?User $actor = null): RepairOrderConcern
    {
        return $this->persist($concern, true, null, $actor, 'inherit');
    }

    /**
     * @param  array<string, mixed>|null  $override
     */
    private function persist(
        RepairOrderConcern $concern,
        bool $inherits,
        ?array $override,
        ?User $actor,
        string $action,
    ): RepairOrderConcern {
        return DB::transaction(function () use ($concern, $inherits, $override, $actor, $action): RepairOrderConcern {
            /** @var RepairOrderConcern $locked */
            $locked = RepairOrderConcern::query()->whereKey($concern->id)->lockForUpdate()->firstOrFail();
            $prior = [
                'inherits' => $locked->inheritsClassWarranty(),
                'override' => $locked->warrantyOverridePayload(),
            ];

            $locked->forceFill([
                'warranty_inherits_class' => $inherits,
                'warranty_override' => $inherits ? null : $override,
            ])->save();

            $this->events->record(
                OperationalEventName::ConcernWarrantyChanged,
                $locked->repairOrder,
                actor: $actor,
                payload: [
                    'concern_id' => $locked->id,
                    'action' => $action,
                    'prior' => $prior,
                    'next' => [
                        'inherits' => $inherits,
                        'override' => $override,
                    ],
                ],
            );

            return $locked->fresh();
        });
    }
}
