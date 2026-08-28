<?php

namespace App\Ark\Operations\Portal;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Documents\CustomerFacingEstimateStatus;
use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\Documents\EstimateDocumentService;
use App\Ark\Operations\Evidence\EvidenceProjection;
use App\Ark\Operations\Evidence\RecordEvidenceCustomerPresentedAction;
use App\Ark\Operations\Payments\SquareConfiguration;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkCapability;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class PortalEstimatePage
{
    public function __construct(
        private readonly RecordPortalEstimateViewAction $recordView,
        private readonly PortalEstimateSnapshot $snapshot,
        private readonly PortalEstimateAuthorization $authorization,
        private readonly EstimateDocumentService $documents,
        private readonly SquareConfiguration $square,
        private readonly CustomerFacingEstimateStatus $estimateStatus,
        private readonly PortalVehicleRecordsLink $vehicleRecordsLink,
        private readonly PortalEstimatePreparedOn $preparedOn,
        private readonly PortalEstimateDepositProjection $depositProjection,
        private readonly EvidenceProjection $evidenceProjection,
        private readonly RecordEvidenceCustomerPresentedAction $recordEvidencePresented,
    ) {}

    public function shouldRecordCustomerView(Request $request): bool
    {
        if (! PortalCustomerViewGate::shouldRecordCustomerView($request)) {
            return false;
        }

        $user = $request->user();

        if ($user === null) {
            return true;
        }

        return ! $user->can(ArkCapability::RepairOrdersManage->value);
    }

    public function render(
        EstimateAccessToken $accessToken,
        string $plainToken,
        bool $recordCustomerView,
        bool $staffPreview = false,
    ): View {
        if ($recordCustomerView && $accessToken->last_viewed_at === null) {
            $repairOrderForView = $accessToken->repairOrder()->firstOrFail();
            $this->recordView->execute($repairOrderForView, $accessToken);
        }

        if ($recordCustomerView) {
            $accessToken->forceFill(['last_viewed_at' => now()])->save();
            $accessToken = $accessToken->fresh();
        }

        $repairOrder = $accessToken->repairOrder()
            ->with(['customer', 'vehicle', 'concerns', 'approvalEvents'])
            ->firstOrFail();

        /** @var Customer|null $portalCustomer */
        $portalCustomer = Auth::guard('portal')->user();

        $depositState = $this->depositProjection->forAccessToken(
            $repairOrder,
            $accessToken,
            session('portal_authorization'),
        );
        $portalAuthorization = $depositState['portalAuthorization'];
        $depositCollected = $depositState['depositCollected'];
        $payingRemaining = $depositState['payingRemaining'];

        $snapshot = $this->snapshot->forRepairOrder($repairOrder);
        $document = EstimateDocument::query()
            ->where('repair_order_id', $repairOrder->id)
            ->where('document_type', 'estimate')
            ->first();

        $pendingConcerns = $this->authorization->pendingRecommendedConcerns($repairOrder);
        $canAuthorize = $this->authorization->canAuthorize($repairOrder);

        $customerEvidence = $this->evidenceProjection
            ->forRepairOrder($repairOrder, customerFacing: true)
            ->map(function (array $row) use ($plainToken): array {
                $row['url'] = route('portal.estimates.evidence.show', [
                    'token' => $plainToken,
                    'evidence' => $row['id'],
                ]);

                return $row;
            });
        $evidenceByConcern = [];
        $generalEvidence = $customerEvidence->filter(
            fn (array $row): bool => ($row['attachable_kind'] ?? null) === 'repair_order'
                || ($row['filter'] ?? '') === 'general',
        )->values();
        foreach ($customerEvidence as $row) {
            if (($row['attachable_kind'] ?? null) === 'concern' && isset($row['attachable_id'])) {
                $evidenceByConcern[(int) $row['attachable_id']][] = $row;
            }
        }

        if ($recordCustomerView && $customerEvidence->isNotEmpty()) {
            $presented = \App\Ark\Operations\Evidence\Evidence::query()
                ->whereIn('id', $customerEvidence->pluck('id')->all())
                ->get();
            $this->recordEvidencePresented->handle($repairOrder, $presented);
        }

        return view('portal.estimate', [
            'repairOrder' => $repairOrder,
            'snapshot' => $snapshot,
            'customerEvidence' => $customerEvidence,
            'evidenceByConcern' => $evidenceByConcern,
            'generalEvidence' => $generalEvidence,
            'evidenceToken' => $plainToken,
            'preparedOn' => $this->preparedOn->label($repairOrder),
            'token' => $plainToken,
            'estimatePdfAvailable' => $document !== null && $this->documents->hasViewablePdf($document),
            'authorizationMode' => $this->authorization->authorizationMode($repairOrder),
            'canAuthorize' => $canAuthorize,
            'pendingConcerns' => $pendingConcerns,
            'authorizationForm' => $canAuthorize
                ? PortalEstimateAuthorizationFormProjection::fromSnapshotAndPendingConcerns($snapshot, $pendingConcerns)
                : null,
            'presentedConcerns' => $this->authorization->presentedConcerns($repairOrder),
            'latestRecordedApproval' => $this->authorization->latestRecordedApproval($repairOrder),
            'presentedWorkIsFullyApproved' => $this->authorization->presentedWorkIsFullyApproved($repairOrder),
            'portalAuthorization' => $portalAuthorization,
            'depositEnabled' => $this->square->portalPayEnabled(),
            'depositCollected' => $depositCollected,
            'payingRemaining' => $payingRemaining,
            'square' => $this->square->publicConfig(),
            'customerName' => trim($repairOrder->customer->first_name.' '.$repairOrder->customer->last_name),
            'customerStatusLabel' => $this->estimateStatus->labelForRepairOrder($repairOrder),
            'signatureRequired' => ShopSettings::current()->portalSignatureRequired(),
            'authorizationLanguage' => ShopSettings::current()->authorizationLanguage(),
            'staffPreview' => $staffPreview,
            'vehicleRecordsLink' => $this->vehicleRecordsLink->forVehicle($portalCustomer, $repairOrder->vehicle),
        ]);
    }
}
