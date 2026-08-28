<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Documents\EstimateDocumentService;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Financial\RefreshCustomerInvoiceAction;
use App\Ark\Operations\Labor\TechnicianFlagRecognitionLine;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Authoritative estimate line delete — shared by operations web and mobile.
 */
final class DestroyRepairOrderLine
{
    use RecordsRepairOrderEstimateMutation;

    public function __construct(
        private readonly EstimateTotalsCalculator $calculator,
        private readonly EstimateDocumentService $documents,
        private readonly OperationalEventRecorder $events,
        private readonly RefreshCustomerInvoiceAction $refreshInvoice,
    ) {}

    public function destroy(RepairOrder $repairOrder, RepairOrderLine $line, User $actor): void
    {
        abort_unless((int) $line->repair_order_id === (int) $repairOrder->id, 404);

        $payload = [
            'line_id' => $line->id,
            'concern_id' => $line->repair_order_concern_id,
            'type' => $line->type->value,
            'total_cents' => $line->total_cents,
        ];

        DB::transaction(function () use ($line): void {
            $this->detachFlagRecognitionForLine($line);
            $line->delete();
        });

        $this->calculator->recalculateRepairOrder($repairOrder);
        $this->documents->markDirtyForRepairOrder($repairOrder);

        $this->events->record(
            OperationalEventName::EstimateLineDeleted,
            $repairOrder,
            actor: $actor,
            payload: $payload,
        );

        $this->recordRepairOrderEstimateMutation($repairOrder, $actor);

        $this->refreshInvoice->executeIfNeeded(
            $repairOrder->fresh(['concerns', 'lines.concern', 'customer']),
            $actor,
        );
    }

    /**
     * Flag recognition lines restrict estimate-line deletes (tfrl_line_fk).
     * Detach recognition evidence for this line; drop empty parent recognitions.
     */
    private function detachFlagRecognitionForLine(RepairOrderLine $line): void
    {
        $recognitionLines = TechnicianFlagRecognitionLine::query()
            ->where('repair_order_line_id', $line->id)
            ->with('recognition')
            ->get();

        foreach ($recognitionLines as $recognitionLine) {
            $recognition = $recognitionLine->recognition;
            $recognitionLine->delete();

            if ($recognition === null) {
                continue;
            }

            $remainingHours = (float) $recognition->lines()->sum('flag_hours');

            if ($recognition->lines()->count() === 0) {
                $recognition->delete();

                continue;
            }

            $recognition->forceFill([
                'flag_hours_total' => number_format($remainingHours, 2, '.', ''),
            ])->save();
        }
    }
}
