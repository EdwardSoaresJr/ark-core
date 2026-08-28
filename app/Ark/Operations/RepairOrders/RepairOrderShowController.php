<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Growth\Journey\JourneyComparisonProjection;
use App\Ark\Growth\Journey\OperationalJourneyProjection;
use App\Ark\Operations\Financial\BalanceDueCalculator;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Financial\RepairOrderFinancialPresenter;
use App\Ark\Operations\Inspections\InspectionWorkspaceTabBadgeProjection;
use App\Ark\Operations\Documents\DocumentProjection;
use App\Ark\Operations\Evidence\EvidenceProjection;
use App\Ark\Operations\Maintenance\MaintenanceService;
use App\Ark\Operations\Maintenance\MaintenanceServiceKind;
use App\Ark\Operations\Maintenance\MaintenanceServiceStatus;
use App\Ark\Operations\WorkAuthorization\WorkAuthorization;
use App\Ark\Operations\WorkAuthorization\WorkAuthorizationPackageType;
use App\Ark\Operations\WorkAuthorization\WorkAuthorizationStatus;
use App\Ark\Orientation\Orientation;
use App\Ark\Orientation\OrientationDensity;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Staff\SoloShopOperations;
use App\Ark\Operations\Workspace\WorkspaceTabSupport;
use App\Ark\Operations\Work\AdvisorWorkProjection;
use App\Ark\Runtime\Authorization\ArkCapability;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Canonical Repair Order presentation surface.
 *
 * GET /app/repair-orders/{repairOrder} — report first; authoring via Workspace Modal.
 */
class RepairOrderShowController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        EstimateTotalsCalculator $calculator,
        RepairOrderFinancialPresenter $financialPresenter,
        BalanceDueCalculator $balanceDue,
        RepairOrderConcurrency $concurrency,
        SoloShopOperations $soloShop,
        AdvisorWorkProjection $advisorWork,
        OperationalJourneyProjection $operationalJourney,
        JourneyComparisonProjection $journeyComparison,
        ProposeConcernsFromVisitReason $proposeConcernsFromVisitReason,
        ApprovalForecastProjection $approvalForecast,
        EvidenceProjection $evidenceProjection,
        DocumentProjection $documentProjection,
    ): View {
        if (RepairOrderProductionLandingGate::applies($request->user())) {
            return app(RepairOrderProductionLandingController::class)($request, $repairOrder);
        }

        $repairOrder->load(['customer', 'vehicle', 'encounter.creator', 'assignedTechnician', 'concerns.workGroups.lines', 'concerns.workGroups.ownerUser', 'concerns.lines', 'lines', 'approvalEvents.revocation']);
        $settings = ShopSettings::current();
        $technicians = $soloShop->assignableTechnicians();
        $totals = $calculator->totalsFor($repairOrder);
        $balanceProjection = $balanceDue->projectForRepairOrder($repairOrder);
        $request->attributes->set(
            'operations.workspace_tab_boot',
            WorkspaceTabSupport::bootFromRepairOrder($repairOrder, $request),
        );

        $journey = $operationalJourney->forRepairOrder($repairOrder);

        $visitReason = trim((string) ($repairOrder->visit_reason ?? ''));
        $visitReasonConcernProposals = [];
        $proposalsDismissed = $request->session()->get(
            RepairOrderVisitReasonConcernAcceptController::dismissKey($repairOrder),
            false,
        );

        if ($visitReason !== '' && $repairOrder->concerns->isEmpty() && ! $proposalsDismissed) {
            $visitReasonConcernProposals = $proposeConcernsFromVisitReason->propose($visitReason);
        }

        $engineOilServices = MaintenanceService::query()
            ->with(['packageLine', 'currentEvent', 'concern'])
            ->where('repair_order_id', $repairOrder->id)
            ->where('kind', MaintenanceServiceKind::EngineOil->value)
            ->where('status', '!=', MaintenanceServiceStatus::Cancelled->value)
            ->orderBy('id')
            ->get()
            ->filter(fn (MaintenanceService $service): bool => $service->isLinkedAlive() || $service->hasConfirmedEvent())
            ->values();

        $testingAuthorizations = WorkAuthorization::query()
            ->with(['concern', 'workGroup', 'packageLine'])
            ->where('repair_order_id', $repairOrder->id)
            ->where('package_type', WorkAuthorizationPackageType::Testing->value)
            ->where('status', '!=', WorkAuthorizationStatus::Cancelled->value)
            ->orderBy('id')
            ->get()
            ->filter(fn (WorkAuthorization $authorization): bool => $authorization->isLinkedAlive()
                || $authorization->status === WorkAuthorizationStatus::Completed)
            ->values();

        $canAuthor = $request->user()?->can(ArkCapability::RepairOrdersManage->value) ?? false;

        return view('operations.repair-orders.show', [
            'soloOwnerShop' => $soloShop->isSoloOwnerShop(),
            'repairOrder' => $repairOrder,
            'engineOilServices' => $engineOilServices,
            'testingAuthorizations' => $testingAuthorizations,
            'evidenceGallery' => $evidenceProjection->staffGallery($repairOrder),
            'customerDocuments' => $documentProjection->forRepairOrder($repairOrder),
            'attachableDocuments' => $documentProjection->attachableForRepairOrder($repairOrder),
            ...RepairOrderPosture::for($repairOrder),
            'totals' => $totals,
            'approvalForecast' => $approvalForecast->for($repairOrder),
            'balanceProjection' => $balanceProjection,
            'financial' => $financialPresenter->for($repairOrder, $totals, $balanceProjection),
            'editingLineId' => $request->integer('editing_line') ?: null,
            'partsMatrices' => $settings->partsMatrices(),
            'defaultPartsMatrixKey' => $settings->defaultPartsMatrix()['key'],
            'defaultLaborRate' => $settings->defaultLaborRate(),
            'laborCategories' => $settings->laborCategories(),
            'defaultLaborCategoryKey' => $settings->defaultLaborCategory()['key'],
            'defaultRecommendationIntent' => $settings->default_recommendation_intent,
            'defaultNotesPrivate' => (bool) $settings->default_notes_private,
            'taxLabel' => $settings->taxLabel(),
            'technicians' => $technicians,
            'estimateVersion' => $concurrency->openedVersion($repairOrder),
            'worksheetSurface' => 'repair_order',
            'canAuthorRepairOrder' => $canAuthor,
            'openFollowUps' => $advisorWork->openFollowUpsForRepairOrder($repairOrder, $request->user()),
            'openTasks' => $advisorWork->openTasksForRepairOrder($repairOrder, $request->user()),
            'currentSituation' => Orientation::forRepairOrder($repairOrder, OrientationDensity::Full),
            'workspaceStrip' => RepairOrderWorkspaceStripProjection::for($repairOrder, 'presentation', $request->user()),
            'repairOrderFooter' => RepairOrderFooterProjection::for($repairOrder, $request->user()),
            'operationalJourney' => $journey,
            'journeyComparison' => $journey->hasStory ? $journeyComparison->forJourney($journey) : null,
            'visitReasonConcernProposals' => $visitReasonConcernProposals,
            'priorVisitMentions' => PriorVisitMentionProjection::for(
                $repairOrder->customer_id,
                $repairOrder->id,
                $repairOrder->vehicle_id,
            ),
            ...InspectionWorkspaceTabBadgeProjection::for($repairOrder, $request->user()),
        ]);
    }
}
