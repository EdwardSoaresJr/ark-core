<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Documents\EstimateDocumentService;
use App\Models\User;

final class UpdateRepairOrderConcernInspection
{
    use RecordsRepairOrderEstimateMutation;

    public function __construct(
        private readonly EstimateDocumentService $documents,
    ) {}

    /**
     * @param  array{
     *     verified_findings?: string|null,
     *     recommendation?: string|null,
     *     dtcs_summary?: string|null,
     * }  $data
     */
    public function update(
        RepairOrder $repairOrder,
        RepairOrderConcern $concern,
        array $data,
        User $actor,
    ): RepairOrderConcern {
        abort_unless((int) $concern->repair_order_id === (int) $repairOrder->id, 404);

        $repairOrder->ensureOpenForEditing();

        $payload = [];

        if (array_key_exists('verified_findings', $data)) {
            $payload['verified_findings'] = filled($data['verified_findings'] ?? null)
                ? trim((string) $data['verified_findings'])
                : null;
        }

        if (array_key_exists('recommendation', $data)) {
            $payload['recommendation'] = filled($data['recommendation'] ?? null)
                ? trim((string) $data['recommendation'])
                : null;
        }

        if (array_key_exists('dtcs_summary', $data)) {
            $payload['dtcs_summary'] = filled($data['dtcs_summary'] ?? null)
                ? trim((string) $data['dtcs_summary'])
                : null;
        }

        if ($payload === []) {
            return $concern;
        }

        $concern->update($payload);
        $this->documents->markDirtyForRepairOrder($repairOrder);
        $this->recordRepairOrderEstimateMutation($repairOrder, $actor);

        return $concern->fresh();
    }
}
