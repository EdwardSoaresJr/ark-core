<?php

namespace App\Ark\Operations\Intake;

use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use App\Ark\Operations\RepairOrders\RecommendationIntent;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\Settings\ShopSettings;
use App\Models\User;
use Illuminate\Support\Str;

final class RepairOrderIntakeConcerns
{
    public function __construct(
        private readonly IntakeConcernParser $concernParser,
        private readonly OperationalEventRecorder $events,
    ) {}

    /**
     * @return list<RepairOrderConcern>
     */
    public function seed(
        RepairOrder $repairOrder,
        string $customerStates,
        ?string $advisorNotes = null,
        ?User $actor = null,
        string $intakeSource = 'advisor',
    ): array {
        $customerStates = trim($customerStates);
        $advisorNotes = trim((string) $advisorNotes);
        $parsedConcerns = $this->concernParser->parse($customerStates);

        if ($parsedConcerns === []) {
            return [];
        }

        $defaultIntent = RecommendationIntent::defaultForRepairOrder($repairOrder);
        $created = [];
        $position = 0;

        foreach ($parsedConcerns as $parsedConcern) {
            $position++;

            $concern = RepairOrderConcern::query()->create([
                'repair_order_id' => $repairOrder->id,
                'summary' => $parsedConcern['summary'],
                'customer_states' => $parsedConcern['customer_states'],
                'notes' => $position === 1 && $advisorNotes !== '' ? $advisorNotes : null,
                'disposition' => RepairOrderConcernDisposition::Draft,
                'recommendation_intent' => $defaultIntent,
                'position' => $position,
            ]);

            $this->events->record(
                OperationalEventName::ConcernCreated,
                $repairOrder,
                actor: $actor,
                payload: [
                    'concern_id' => $concern->id,
                    'intake' => $intakeSource,
                    'position' => $concern->position,
                ],
            );

            $created[] = $concern;
        }

        $repairOrder->forceFill([
            'concern_summary' => $parsedConcerns[0]['summary'] ?? Str::limit($customerStates, 2000, ''),
        ])->save();

        return $created;
    }

    /**
     * @param  list<array{
     *     customer_states: string,
     *     recommendation_intent?: string|null,
     *     billing_posture?: string|null,
     *     dtcs_summary?: string|null,
     *     verified_findings?: string|null,
     *     recommendation?: string|null,
     * }>  $rows
     * @return list<RepairOrderConcern>
     */
    public function seedRows(
        RepairOrder $repairOrder,
        array $rows,
        ?string $advisorNotes = null,
        ?User $actor = null,
        string $intakeSource = 'advisor',
    ): array {
        $defaultIntent = RecommendationIntent::defaultForRepairOrder($repairOrder);
        $repairOrder->loadMissing('customer');
        $defaultBillingPosture = ConcernBillingPosture::defaultForCustomer($repairOrder->customer);

        $created = [];
        $position = 0;

        foreach ($rows as $row) {
            $parsedConcern = $this->concernParser->parseRow($row['customer_states'] ?? '');

            if ($parsedConcern === null) {
                continue;
            }

            $position++;

            $intent = RecommendationIntent::tryFromStored(trim((string) ($row['recommendation_intent'] ?? '')))
                ?? $defaultIntent;

            $billingPosture = ConcernBillingPosture::tryFrom(trim((string) ($row['billing_posture'] ?? '')))
                ?? $defaultBillingPosture;

            $concern = RepairOrderConcern::query()->create([
                'repair_order_id' => $repairOrder->id,
                'summary' => $parsedConcern['summary'],
                'customer_states' => $parsedConcern['customer_states'],
                'notes' => $position === 1 && trim((string) $advisorNotes) !== '' ? trim((string) $advisorNotes) : null,
                'dtcs_summary' => filled(trim((string) ($row['dtcs_summary'] ?? '')))
                    ? trim((string) $row['dtcs_summary'])
                    : null,
                'verified_findings' => filled(trim((string) ($row['verified_findings'] ?? '')))
                    ? trim((string) $row['verified_findings'])
                    : null,
                'recommendation' => filled(trim((string) ($row['recommendation'] ?? '')))
                    ? trim((string) $row['recommendation'])
                    : null,
                'disposition' => RepairOrderConcernDisposition::Draft,
                'recommendation_intent' => $intent,
                'position' => $position,
                'billing_posture' => $billingPosture,
            ]);

            $this->events->record(
                OperationalEventName::ConcernCreated,
                $repairOrder,
                actor: $actor,
                payload: [
                    'concern_id' => $concern->id,
                    'intake' => $intakeSource,
                    'position' => $concern->position,
                ],
            );

            $created[] = $concern;
        }

        if ($created !== []) {
            $repairOrder->forceFill([
                'concern_summary' => $created[0]->summary,
            ])->save();
        }

        return $created;
    }
}
