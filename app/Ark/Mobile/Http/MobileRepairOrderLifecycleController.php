<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Mobile\RepairOrderWorkspaceProjection;
use App\Ark\Operations\Documents\EstimateDocumentService;
use App\Ark\Operations\RepairOrders\RecordsRepairOrderEstimateMutation;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcurrency;
use App\Ark\Operations\RepairOrders\RepairOrderLifecycleTransition;
use App\Ark\Operations\RepairOrders\RepairOrderLostReason;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\RepairOrderWorkflowStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * RO lifecycle transitions from the phone - move status forward/back and close
 * the repair order. Reuses RepairOrderLifecycleTransition, the same authority
 * the desktop toolbar uses, so blocking rules, final invoice issue, posting,
 * and lifecycle events behave identically across surfaces. The status value may
 * carry a close variant ("closed:paid" / "closed:lost"), matching the desktop.
 */
final class MobileRepairOrderLifecycleController
{
    use RecordsRepairOrderEstimateMutation;

    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        MobileStaffAccess $access,
        RepairOrderLifecycleTransition $lifecycle,
        RepairOrderWorkspaceProjection $workspace,
        RepairOrderConcurrency $concurrency,
        EstimateDocumentService $documents,
    ): JsonResponse {
        $user = $request->user();

        abort_unless($access->canViewRepairOrder($user, $repairOrder), 403);
        abort_unless($access->canChangeRepairOrderLifecycle($user, $repairOrder), 403);

        $repairOrder->ensureOpenForEditing();
        $concurrency->guard($request, $repairOrder);

        $data = $request->validate([
            'status' => ['required', 'string', 'max:120'],
            'lost_reason_key' => ['nullable', Rule::enum(RepairOrderLostReason::class)],
            'lost_reason_note' => ['nullable', 'string', 'max:500'],
            'operational_closeout_reason' => ['nullable', 'string', 'max:1000'],
            'operational_closeout_fingerprint' => ['nullable', 'string', 'max:128'],
        ]);

        $requestedStatus = $data['status'];
        $closeVariantKey = null;

        if (str_contains($requestedStatus, ':')) {
            [$requestedStatus, $closeVariantKey] = explode(':', $requestedStatus, 2);
        }

        $toStatusSlug = RepairOrderWorkflowStatus::normalizeSlug($requestedStatus);
        $operationalCloseoutReason = isset($data['operational_closeout_reason'])
            ? trim((string) $data['operational_closeout_reason'])
            : null;
        $operationalCloseoutFingerprint = isset($data['operational_closeout_fingerprint'])
            ? trim((string) $data['operational_closeout_fingerprint'])
            : null;
        $operationalCloseoutReason = $operationalCloseoutReason === '' ? null : $operationalCloseoutReason;
        $operationalCloseoutFingerprint = $operationalCloseoutFingerprint === '' ? null : $operationalCloseoutFingerprint;

        if (($reason = $lifecycle->blockingReason(
            $repairOrder,
            $toStatusSlug,
            $user,
            $closeVariantKey,
            operationalCloseoutReason: $operationalCloseoutReason,
            operationalCloseoutFingerprint: $operationalCloseoutFingerprint,
        )) !== null) {
            $challenge = $lifecycle->operationalCloseoutChallenge(
                $repairOrder,
                $toStatusSlug,
                $closeVariantKey,
                $operationalCloseoutReason,
                $operationalCloseoutFingerprint,
            );

            return response()->json([
                'message' => $reason,
                'operational_closeout' => $challenge,
            ], 422);
        }

        $lostReason = null;
        $lostReasonNote = null;

        if ($toStatusSlug === RepairOrderStatus::Closed->value && $closeVariantKey === 'lost') {
            $lostReason = isset($data['lost_reason_key'])
                ? RepairOrderLostReason::from($data['lost_reason_key'])
                : null;

            if ($lostReason === null) {
                return response()->json(['message' => 'Choose why this repair order closed lost before continuing.'], 422);
            }

            $lostReasonNote = isset($data['lost_reason_note']) ? trim((string) $data['lost_reason_note']) : null;

            if ($lostReason->requiresNote() && ($lostReasonNote === null || $lostReasonNote === '')) {
                return response()->json(['message' => 'Add a short note when the lost reason is Other.'], 422);
            }
        }

        $lifecycle->move(
            $repairOrder,
            $toStatusSlug,
            $user,
            $closeVariantKey,
            $lostReason,
            $lostReasonNote,
            operationalCloseoutReason: $operationalCloseoutReason,
            operationalCloseoutFingerprint: $operationalCloseoutFingerprint,
        );

        $this->recordRepairOrderEstimateMutation($repairOrder->fresh(), $user);

        $repairOrder = $repairOrder->fresh();
        $response = [
            'workspace' => $workspace->forRepairOrder($repairOrder, $user),
        ];

        if ($repairOrder->isTerminal() && $documents->hasFailedPdfForRepairOrder($repairOrder)) {
            $response['warning'] = 'The final estimate PDF could not be generated. Open the estimate PDF to try again.';
        }

        return response()->json($response);
    }
}
