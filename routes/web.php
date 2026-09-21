<?php

use App\Ark\Communications\Provisioning\EndpointProvisionController;
use App\Ark\Dragon\ReviewEstimateNotes\Http\ApplyReviewEstimateNotesProposalController;
use App\Ark\Dragon\ReviewEstimateNotes\Http\RequestReviewEstimateNotesController;
use App\Ark\Dragon\ServiceAdvisor\Http\ApplyLineNoteRewriteController;
use App\Ark\Dragon\ServiceAdvisor\Http\ApplyServiceAdvisorRewriteController;
use App\Ark\Dragon\ServiceAdvisor\Http\ApplyVisitReasonRewriteController;
use App\Ark\Dragon\ServiceAdvisor\Http\RequestLineNoteRewriteController;
use App\Ark\Dragon\ServiceAdvisor\Http\RequestServiceAdvisorRewriteController;
use App\Ark\Dragon\ServiceAdvisor\Http\RequestVisitReasonRewriteController;
use App\Ark\Dragon\ServiceAdvisor\Http\RevertServiceAdvisorRewriteController;
use App\Ark\Operations\Appointments\AppointmentAssignController;
use App\Ark\Operations\Appointments\AppointmentConfirmationSmsController;
use App\Ark\Operations\Appointments\AppointmentCreateController;
use App\Ark\Operations\Appointments\AppointmentIndexController;
use App\Ark\Operations\Appointments\AppointmentReminderSettingsController;
use App\Ark\Operations\Appointments\AppointmentRequestExceptionController;
use App\Ark\Operations\Appointments\AppointmentRescheduleController;
use App\Ark\Operations\Appointments\AppointmentScheduleBoardViewController;
use App\Ark\Operations\Appointments\AppointmentShowController;
use App\Ark\Operations\Appointments\AppointmentStatusController;
use App\Ark\Operations\Appointments\AppointmentStoreController;
use App\Ark\Operations\Appointments\AppointmentUpdateController;
use App\Ark\Operations\Appointments\ScheduleEntryController;
use App\Ark\Operations\Appointments\ScheduleRepairOrderAppointmentController;
use App\Ark\Operations\ArkManager\Http\ArkManagerDraftCommunicationController;
use App\Ark\Operations\ArkManager\Http\ArkManagerExplainRecommendationController;
use App\Ark\Operations\Attention\ActOnAdvisorNudgeController;
use App\Ark\Operations\Attention\DismissAdvisorNudgeController;
use App\Ark\Operations\Business\BusinessCockpitController;
use App\Ark\Operations\Commitments\OperationalCommitmentFulfillController;
use App\Ark\Operations\Commitments\OperationalCommitmentStoreController;
use App\Ark\Operations\Communications\AssignConversationController;
use App\Ark\Operations\Communications\AssignDiscoveredCommunicationDeviceController;
use App\Ark\Operations\Communications\AssignWorkstationExtensionController;
use App\Ark\Operations\Communications\CommsInterruptController;
use App\Ark\Operations\Communications\CommunicationDeviceController;
use App\Ark\Operations\Communications\CommunicationsCallLibraryController;
use App\Ark\Operations\Communications\CommunicationsComposeStartController;
use App\Ark\Operations\Communications\CommunicationsLegacySurfaceRedirectController;
use App\Ark\Operations\Communications\CommunicationsMarkCallHandledController;
use App\Ark\Operations\Communications\CommunicationsMarkConversationHandledController;
use App\Ark\Operations\Communications\CommunicationsMarkConversationReadController;
use App\Ark\Operations\Communications\CommunicationsPersonController;
use App\Ark\Operations\Communications\CommunicationsQueueApiController;
use App\Ark\Operations\Communications\CommunicationsRecentActivityFragmentController;
use App\Ark\Operations\Communications\CommunicationsShopController;
use App\Ark\Operations\Communications\CommunicationsWorkspaceController;
use App\Ark\Operations\Communications\CommunicationsWorkspaceFragmentController;
use App\Ark\Operations\Communications\CommunicationWorkboardFragmentController;
use App\Ark\Operations\Communications\DestroyCommunicationDeviceController;
use App\Ark\Operations\Communications\DownloadCommunicationDeviceConfigController;
use App\Ark\Operations\Communications\GenerateCommunicationDeviceConfigController;
use App\Ark\Operations\Communications\OperationalCommunicationStoreController;
use App\Ark\Operations\Communications\ReopenConversationController;
use App\Ark\Operations\Communications\StoreCallSessionNoteController;
use App\Ark\Operations\Communications\StoreCommunicationDeviceController;
use App\Ark\Operations\Communications\StoreConversationInternalNoteController;
use App\Ark\Operations\Communications\ToggleSmsIntelligenceCoachingFollowUpController;
use App\Ark\Operations\Communications\UpdateCommunicationsIncomingRoutingController;
use App\Ark\Operations\Communications\VoiceCapabilityHealthController;
use App\Ark\Operations\Conversations\CallerLookupController;
use App\Ark\Operations\Conversations\ConversationAttachmentShowController;
use App\Ark\Operations\Conversations\LinkMessengerConversationController;
use App\Ark\Operations\Conversations\MarkConversationReadController;
use App\Ark\Operations\Conversations\ResolveConversationController;
use App\Ark\Operations\Customers\CustomerHubCommsUpdatesController;
use App\Ark\Operations\Customers\CustomerSearchController;
use App\Ark\Operations\Customers\CustomerShowController;
use App\Ark\Operations\Customers\CustomerStoreController;
use App\Ark\Operations\Customers\CustomerUpdateController;
use App\Ark\Operations\Display\OperationsShopDisplayController;
use App\Ark\Operations\Documents\DocumentAttachController;
use App\Ark\Operations\Documents\DocumentDownloadController;
use App\Ark\Operations\Documents\DocumentEmailController;
use App\Ark\Operations\Documents\DocumentRetireController;
use App\Ark\Operations\Documents\DocumentRotateController;
use App\Ark\Operations\Documents\DocumentScanController;
use App\Ark\Operations\Documents\DocumentStreamController;
use App\Ark\Operations\Documents\DocumentUploadController;
use App\Ark\Operations\Documents\DocumentViewerController;
use App\Ark\Operations\Documents\DocumentVisibilityController;
use App\Ark\Operations\Documents\EstimateDocumentEmailController;
use App\Ark\Operations\Documents\EstimateDocumentOpenController;
use App\Ark\Operations\Documents\EstimateDocumentPdfController;
use App\Ark\Operations\Documents\EstimateDocumentShowController;
use App\Ark\Operations\Documents\EstimateDocumentStoreController;
use App\Ark\Operations\Documents\InvoiceDocumentEmailController;
use App\Ark\Operations\Documents\RepairOrderDocumentAttachController;
use App\Ark\Operations\Documents\RepairOrderDocumentScanController;
use App\Ark\Operations\Documents\RepairOrderDocumentUploadController;
use App\Ark\Operations\Evidence\RepairOrderEvidencePrimaryController;
use App\Ark\Operations\Evidence\RepairOrderEvidenceRetireController;
use App\Ark\Operations\Evidence\RepairOrderEvidenceShowController;
use App\Ark\Operations\Evidence\RepairOrderEvidenceStoreController;
use App\Ark\Operations\Evidence\RepairOrderEvidenceVisibilityController;
use App\Ark\Operations\Financial\RepairOrderInvoiceGenerateController;
use App\Ark\Operations\Financial\RepairOrderInvoiceRefreshController;
use App\Ark\Operations\Inspections\RepairOrderInspectionFindingStoreController;
use App\Ark\Operations\Inspections\RepairOrderInspectionItemStoreController;
use App\Ark\Operations\Inspections\RepairOrderInspectionItemUpdateController;
use App\Ark\Operations\Inspections\RepairOrderInspectionMeasurementDestroyController;
use App\Ark\Operations\Inspections\RepairOrderInspectionMeasurementStoreController;
use App\Ark\Operations\Inspections\RepairOrderInspectionNotesUpdateController;
use App\Ark\Operations\Inspections\RepairOrderInspectionPhotoDestroyController;
use App\Ark\Operations\Inspections\RepairOrderInspectionPhotoShowController;
use App\Ark\Operations\Inspections\RepairOrderInspectionPhotoStoreController;
use App\Ark\Operations\Inspections\RepairOrderInspectionPointUpdateController;
use App\Ark\Operations\Inspections\RepairOrderInspectionResetController;
use App\Ark\Operations\Inspections\RepairOrderInspectionShowController;
use App\Ark\Operations\Inspections\RepairOrderInspectionTemplateAssignController;
use App\Ark\Operations\Inspections\RepairOrderInspectionWalkLinkSendController;
use App\Ark\Operations\Intake\AdvisorIntakeCreateController;
use App\Ark\Operations\Intake\AdvisorIntakeCustomerDuplicateController;
use App\Ark\Operations\Intake\AdvisorIntakeCustomerSearchController;
use App\Ark\Operations\Intake\AdvisorIntakeCustomerShowController;
use App\Ark\Operations\Intake\AdvisorIntakeIndexController;
use App\Ark\Operations\Intake\AdvisorIntakeStoreController;
use App\Ark\Operations\Intake\AdvisorIntakeVehicleLookupController;
use App\Ark\Operations\Intake\AdvisorIntakeWebsiteLeadStoreController;
use App\Ark\Operations\Labor\TechnicianProductionAssistController;
use App\Ark\Operations\Labor\TechnicianTimeClockController;
use App\Ark\Operations\LaborGuides\RepairOrderLaborGuideController;
use App\Ark\Operations\LaborGuides\RepairOrderRteLaborApplyController;
use App\Ark\Operations\LaborGuides\RepairOrderRteLaborSearchController;
use App\Ark\Operations\Leads\AdvisorIngressCreateContactController;
use App\Ark\Operations\Leads\AdvisorLeadCreateContactController;
use App\Ark\Operations\Leads\AdvisorLeadIndexController;
use App\Ark\Operations\Leads\AdvisorLeadIntakeController;
use App\Ark\Operations\Leads\AdvisorLeadStateController;
use App\Ark\Operations\Leads\WebsiteLeadInterruptDismissController;
use App\Ark\Operations\Learn\LearnArkController;
use App\Ark\Operations\Learn\LearnArkMediaController;
use App\Ark\Operations\Learn\LearnArkPreviewController;
use App\Ark\Operations\Learn\LearnArkPrintController;
use App\Ark\Operations\Learn\LearnArkProgressController;
use App\Ark\Operations\Learn\LearnArkTeamProgressController;
use App\Ark\Operations\Learn\LearnArticleMediaShowController;
use App\Ark\Operations\Maintenance\AddEngineOilServiceController;
use App\Ark\Operations\Maintenance\AddExtraOilQuartsAtCostController;
use App\Ark\Operations\Maintenance\ConfirmEngineOilInstalledController;
use App\Ark\Operations\Messaging\CancelScheduledOutboundEstimateController;
use App\Ark\Operations\Messaging\CancelScheduledOutboundSmsController;
use App\Ark\Operations\Messaging\OutboundMmsMediaController;
use App\Ark\Operations\Messaging\SendAdvisorMessageActionController;
use App\Ark\Operations\Messaging\SendConversationContactMessageController;
use App\Ark\Operations\Messaging\SendConversationDepositRequestLinkController;
use App\Ark\Operations\Messaging\SendConversationEstimateLinkController;
use App\Ark\Operations\Messaging\SendConversationMessageController;
use App\Ark\Operations\Messaging\SendConversationPaymentLinkController;
use App\Ark\Operations\Messaging\SendDepositRequestLinkController;
use App\Ark\Operations\Messaging\SendEstimateLinkController;
use App\Ark\Operations\Messaging\SendInspectionLinkController;
use App\Ark\Operations\Messaging\SendPaymentLinkController;
use App\Ark\Operations\Messaging\SendReviewRequestController;
use App\Ark\Operations\Messaging\SendShopAddressController;
use App\Ark\Operations\Observations\Http\OperationalObservationsIndexController;
use App\Ark\Operations\OperationsHomeController;
use App\Ark\Operations\OperationsIndexController;
use App\Ark\Operations\Parts\DealerQuoteShowController;
use App\Ark\Operations\Parts\RepairOrderDealerQuoteCaptureController;
use App\Ark\Operations\Portal\PortalCustomerActivityInterruptDismissController;
use App\Ark\Operations\Portal\RepairOrderEstimatePortalLinkController;
use App\Ark\Operations\Portal\RepairOrderInspectionPortalLinkController;
use App\Ark\Operations\Portal\RepairOrderInspectionPrintController;
use App\Ark\Operations\Portal\RepairOrderPaymentPortalLinkController;
use App\Ark\Operations\Portal\RepairOrderPortalEstimatePreviewController;
use App\Ark\Operations\Portal\RepairOrderPortalInspectionPreviewController;
use App\Ark\Operations\Portal\RepairOrderPortalPaymentPreviewController;
use App\Ark\Operations\Portal\RepairOrderPortalSessionController;
use App\Ark\Operations\Printing\KeyTagPrintController;
use App\Ark\Operations\Printing\OilChangeStickerPrintController;
use App\Ark\Operations\Printing\PartsLabelPrintController;
use App\Ark\Operations\Printing\PrintRoutingController;
use App\Ark\Operations\Printing\QzPrintingPocController;
use App\Ark\Operations\Printing\QzTraySignController;
use App\Ark\Operations\RepairOrders\RepairOrderAuthorizationRevokeController;
use App\Ark\Operations\RepairOrders\RepairOrderAuthorizationStoreController;
use App\Ark\Operations\RepairOrders\RepairOrderConcernBillingPostureController;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDestroyController;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDispositionController;
use App\Ark\Operations\RepairOrders\RepairOrderConcernMemorySuggestController;
use App\Ark\Operations\RepairOrders\RepairOrderConcernMoveController;
use App\Ark\Operations\RepairOrders\RepairOrderConcernMoveToNewRepairOrderController;
use App\Ark\Operations\RepairOrders\RepairOrderConcernProductionStatusController;
use App\Ark\Operations\RepairOrders\RepairOrderConcernRecommendationIntentController;
use App\Ark\Operations\RepairOrders\RepairOrderConcernStoreController;
use App\Ark\Operations\RepairOrders\RepairOrderConcernUpdateController;
use App\Ark\Operations\RepairOrders\RepairOrderConcernVocabularySuggestController;
use App\Ark\Operations\RepairOrders\RepairOrderCustomerDisplayController;
use App\Ark\Operations\RepairOrders\RepairOrderDepositController;
use App\Ark\Operations\RepairOrders\RepairOrderDraftStoreController;
use App\Ark\Operations\RepairOrders\RepairOrderIndexController;
use App\Ark\Operations\RepairOrders\RepairOrderLaborMemorySuggestController;
use App\Ark\Operations\RepairOrders\RepairOrderLedgerRefundController;
use App\Ark\Operations\RepairOrders\RepairOrderLedgerVoidController;
use App\Ark\Operations\RepairOrders\RepairOrderLedgerWriteOffController;
use App\Ark\Operations\RepairOrders\RepairOrderLegacySurfaceRedirectController;
use App\Ark\Operations\RepairOrders\RepairOrderLifecycleController;
use App\Ark\Operations\RepairOrders\RepairOrderLineDestroyController;
use App\Ark\Operations\RepairOrders\RepairOrderLinePricingPreviewController;
use App\Ark\Operations\RepairOrders\RepairOrderLineProcurementController;
use App\Ark\Operations\RepairOrders\RepairOrderLineStoreController;
use App\Ark\Operations\RepairOrders\RepairOrderLineUpdateController;
use App\Ark\Operations\RepairOrders\RepairOrderMileageUpdateController;
use App\Ark\Operations\RepairOrders\RepairOrderOperationalSheetController;
use App\Ark\Operations\RepairOrders\RepairOrderPaymentController;
use App\Ark\Operations\RepairOrders\RepairOrderPostController;
use App\Ark\Operations\RepairOrders\RepairOrderShowController;
use App\Ark\Operations\RepairOrders\RepairOrderTechnicianAssignmentController;
use App\Ark\Operations\RepairOrders\RepairOrderVehicleUpdateController;
use App\Ark\Operations\RepairOrders\RepairOrderVisitPostureUpdateController;
use App\Ark\Operations\RepairOrders\RepairOrderVisitReasonConcernAcceptController;
use App\Ark\Operations\RepairOrders\RepairOrderVisitReasonConcernProposeDismissController;
use App\Ark\Operations\RepairOrders\RepairOrderVisitReasonUpdateController;
use App\Ark\Operations\RepairOrders\RepairOrderWorkGroupCommunicationController;
use App\Ark\Operations\RepairOrders\RepairOrderWorkGroupDestroyController;
use App\Ark\Operations\RepairOrders\RepairOrderWorkGroupOwnerController;
use App\Ark\Operations\RepairOrders\RepairOrderWorkGroupStoreController;
use App\Ark\Operations\RepairOrders\RepairOrderWorksheetSessionController;
use App\Ark\Operations\RepairOrders\RepairOrderWorkspaceTabController;
use App\Ark\Operations\RepairOrders\RepairOrderWorkspaceTabPresenter;
use App\Ark\Operations\Reports\OperationalReportController;
use App\Ark\Operations\Reports\ReportsEndOfDayController;
use App\Ark\Operations\Reports\ReportsIndexController;
use App\Ark\Operations\Search\OperationsGlobalSearchController;
use App\Ark\Operations\ShopExcellence\OwnerDayReviewController;
use App\Ark\Operations\ShopExcellence\PartsMatrixTuneController;
use App\Ark\Operations\Staff\OwnerStaffCoachingController;
use App\Ark\Operations\Staff\StaffFrontDoor;
use App\Ark\Operations\Staff\StoreStaffCoachingLogController;
use App\Ark\Operations\Telephony\CallRecordingPlaybackController;
use App\Ark\Operations\Telephony\CallSessionCallerContextController;
use App\Ark\Operations\Telephony\CallSessionCoachingPdfController;
use App\Ark\Operations\Telephony\ClaimCallSessionController;
use App\Ark\Operations\Telephony\IncomingCallActiveController;
use App\Ark\Operations\Telephony\IncomingCallDismissController;
use App\Ark\Operations\Telephony\IncomingCallQueueController;
use App\Ark\Operations\Telephony\MarkCallSessionWorkedController;
use App\Ark\Operations\Telephony\OwnerCallIntelligenceController;
use App\Ark\Operations\Telephony\SimulateIncomingCallController;
use App\Ark\Operations\Telephony\StaffPresenceHeartbeatController;
use App\Ark\Operations\Telephony\TelephonyCallbackController;
use App\Ark\Operations\Telephony\ToggleCallSessionCoachingFollowUpController;
use App\Ark\Operations\Today\Surface\TodayController;
use App\Ark\Operations\Today\TodayRecommendationCloseLostController;
use App\Ark\Operations\Today\TodayRecommendationSnoozeController;
use App\Ark\Operations\Vehicles\VehicleDestroyController;
use App\Ark\Operations\Vehicles\VehicleSearchController;
use App\Ark\Operations\Vehicles\VehicleStoreController;
use App\Ark\Operations\Vehicles\VehicleUpdateController;
use App\Ark\Operations\Vehicles\VehicleVinDecodeController;
use App\Ark\Operations\Work\AdvisorFollowUpCompleteController;
use App\Ark\Operations\Work\AdvisorFollowUpStoreController;
use App\Ark\Operations\Work\AdvisorTaskCompleteController;
use App\Ark\Operations\Work\AdvisorTaskStoreController;
use App\Ark\Operations\Work\CustomerDecisionScheduleClearController;
use App\Ark\Operations\Work\CustomerDecisionScheduleStoreController;
use App\Ark\Operations\Work\WorkQueueController;
use App\Ark\Operations\WorkAuthorization\AuthorizeTestingPackageController;
use App\Ark\Operations\WorkAuthorization\RecordTestingPackageOutcomeController;
use App\Ark\Operations\Workboard\OperationsWorkboardTriageFragmentController;
use App\Ark\Operations\Workspace\WorkspaceTabActivityController;
use App\Ark\Operations\Workstations\BindWorkstationController;
use App\Ark\Operations\Workstations\DestroyWorkstationController;
use App\Ark\Operations\Workstations\StoreWorkstationController;
use App\Ark\Operations\Workstations\UpdateWorkstationController;
use App\Ark\Operations\Workstations\WorkstationOperatorController;
use App\Ark\Operations\WorkTemplates\ApplyWorkTemplateController;
use App\Ark\Operations\WorkTemplates\HistoricalWorkRecallAssistController;
use App\Ark\Operations\WorkTemplates\HistoricalWorkRecallAssistStatusController;
use App\Ark\Operations\WorkTemplates\HistoricalWorkRecallController;
use App\Ark\Operations\WorkTemplates\WorkTemplateSearchController;
use App\Ark\Platform\ClusterIndexController;
use App\Ark\Runtime\Authorization\ArkCapability;
use App\Ark\Runtime\Exceptions\ExceptionReportCopyController;
use App\Ark\Runtime\Surfaces\SurfaceRouting;
use App\Ark\ShopMemory\Rewrite\RepairOrderAiRewriteController;
use App\Ark\ShopMemory\ShopMemorySuggestionEventStoreController;
use App\Http\Controllers\DevRolePretendController;
use App\Http\Controllers\DisplayThemeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReverbDeploymentHealthController;
use Illuminate\Support\Facades\Route;

Route::get('/up/reverb', ReverbDeploymentHealthController::class)->name('health.reverb');

Route::get('/error-reports/{reportId}/copy', ExceptionReportCopyController::class)
    ->middleware('signed')
    ->name('runtime.exception-reports.copy');

SurfaceRouting::appRoutes(function (): void {
    if (SurfaceRouting::enabled()) {
        Route::get('/', function () {
            if (auth()->check()) {
                return redirect()->to(StaffFrontDoor::landingUrl());
            }

            return redirect()->route('login');
        });
    }

    Route::middleware('throttle:120,1')->group(function (): void {
        Route::prefix('voice')->group(function (): void {
            Route::get('/health', VoiceCapabilityHealthController::class)
                ->name('voice.health');
        });

        Route::get('/provision/config/{mac}', [EndpointProvisionController::class, 'config'])
            ->where('mac', '[0-9A-Fa-f]{12}')
            ->name('communications.endpoints.provision.config');

        Route::get('/provision/{filename}', EndpointProvisionController::class)
            ->where('filename', '000000000000(-license)\.cfg|000000000000-directory\.xml|sip\.cfg|[0-9a-fA-F]{12}(-phone|-web|-cloud|-directory|-calls|-license)?\.(cfg|xml)')
            ->name('communications.endpoints.provision');

        Route::get('/media/outbound-mms/{token}', OutboundMmsMediaController::class)
            ->middleware('signed')
            ->name('messaging.outbound-media');

    });

    Route::get('/dashboard', function () {
        return redirect()->to(StaffFrontDoor::landingUrl());
    })->middleware(['auth', 'verified'])->name('dashboard');

    Route::middleware('auth')->group(function () {
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::patch('/profile/appearance', [ProfileController::class, 'updateAppearance'])->name('profile.appearance.update');
        Route::patch('/profile/display-theme', [DisplayThemeController::class, 'update'])->name('profile.display-theme.update');
        Route::patch('/profile/workstation-pin', [ProfileController::class, 'updateWorkstationPin'])->name('profile.workstation-pin.update');
        Route::post('/profile/dev-role/technician', [DevRolePretendController::class, 'technician'])->name('dev-role-pretend.technician');
        Route::post('/profile/dev-role/clear', [DevRolePretendController::class, 'clear'])->name('dev-role-pretend.clear');
    });

    Route::middleware(['auth', 'permission:'.StaffFrontDoor::STAFF_SHELL_PERMISSION])->group(function () {
        Route::get('/app/today', TodayController::class)
            ->middleware('front_door:today')
            ->name('operations.today');

        Route::get('/app/business', BusinessCockpitController::class)
            ->middleware('business.workspace')
            ->name('operations.business');

        Route::redirect('/app/briefing', '/app/today')->name('operations.briefing');

        Route::get('/app/time-clock', [TechnicianTimeClockController::class, 'index'])
            ->name('operations.time-clock.index');
        Route::post('/app/time-clock/in', [TechnicianTimeClockController::class, 'clockIn'])
            ->name('operations.time-clock.in');
        Route::post('/app/time-clock/out', [TechnicianTimeClockController::class, 'clockOut'])
            ->name('operations.time-clock.out');
        Route::get('/app/time-clock/staff/{user}', [TechnicianTimeClockController::class, 'showStaff'])
            ->name('operations.time-clock.staff');
        Route::post('/app/time-clock/staff/{user}/in', [TechnicianTimeClockController::class, 'clockInForStaff'])
            ->name('operations.time-clock.staff.in');
        Route::post('/app/time-clock/staff/{user}/out', [TechnicianTimeClockController::class, 'clockOutForStaff'])
            ->name('operations.time-clock.staff.out');
        Route::post('/app/time-clock/staff/{user}/auto-clock', [TechnicianTimeClockController::class, 'updateAutoClock'])
            ->name('operations.time-clock.staff.auto-clock');
        Route::post('/app/time-clock/sessions/{technicianTimeSession}/correct', [TechnicianTimeClockController::class, 'correct'])
            ->name('operations.time-clock.correct');
        Route::post('/app/time-clock/sessions/{technicianTimeSession}/delete', [TechnicianTimeClockController::class, 'destroy'])
            ->name('operations.time-clock.delete');

        Route::middleware('permission:'.ArkCapability::CommunicationsInternalView->value.'|'.ArkCapability::OperationsAccess->value)->group(function (): void {
            Route::get('/app/communications/internal', [CommunicationsWorkspaceController::class, 'internal'])
                ->name('operations.communications.internal');

            Route::get('/app/communications/internal/{channel:slug}', [CommunicationsWorkspaceController::class, 'internalChannel'])
                ->name('operations.communications.internal.channel');
        });

        Route::middleware('permission:'.ArkCapability::OperationsAccess->value)->group(function (): void {
            Route::post('/app/today/recommendations/snooze', TodayRecommendationSnoozeController::class)
                ->name('operations.today.snooze');

            Route::post('/app/today/recommendations/close-lost', TodayRecommendationCloseLostController::class)
                ->name('operations.today.close-lost');

            Route::post('/app/today/ark-manager/explain', ArkManagerExplainRecommendationController::class)
                ->name('operations.today.ark-manager.explain');

            Route::post('/app/today/ark-manager/draft', ArkManagerDraftCommunicationController::class)
                ->name('operations.today.ark-manager.draft');

            Route::post('/app/commitments/{commitment}/fulfill', OperationalCommitmentFulfillController::class)
                ->name('operations.commitments.fulfill');

            Route::get('/app', OperationsHomeController::class)
                ->middleware('front_door:attention')
                ->name('operations.index');

            Route::get('/app/display', [OperationsShopDisplayController::class, 'index'])
                ->name('operations.display');

            Route::get('/app/display/fragment', [OperationsShopDisplayController::class, 'fragment'])
                ->name('operations.display.fragment');

            Route::redirect('/app/communications', '/app/communications/inbox?filter=needs')
                ->middleware('front_door:attention')
                ->name('operations.communications.index');

            Route::get('/app/communications/workboard', [CommunicationsLegacySurfaceRedirectController::class, 'workboard'])
                ->middleware('front_door:attention')
                ->name('operations.communications.workboard');

            Route::get('/app/communications/attention', [CommunicationsWorkspaceController::class, 'attention'])
                ->middleware('front_door:attention')
                ->name('operations.communications.attention');

            Route::get('/app/communications/inbox', [CommunicationsWorkspaceController::class, 'inbox'])
                ->middleware('front_door:attention')
                ->name('operations.communications.inbox');

            Route::post('/app/communications/compose', CommunicationsComposeStartController::class)
                ->middleware('front_door:attention')
                ->name('operations.communications.compose');

            Route::get('/app/search', OperationsGlobalSearchController::class)
                ->middleware('front_door:attention')
                ->name('operations.search');

            Route::get('/app/communications/history', [CommunicationsWorkspaceController::class, 'history'])
                ->middleware('front_door:attention')
                ->name('operations.communications.history');

            Route::get('/app/communications/calls', CommunicationsCallLibraryController::class)
                ->middleware('front_door:attention')
                ->name('operations.communications.calls');

            Route::get('/app/communications/workspace/fragment', CommunicationsWorkspaceFragmentController::class)
                ->middleware('front_door:attention')
                ->name('operations.communications.workspace.fragment');

            Route::post('/app/communications/conversations/{conversation}/assign', AssignConversationController::class)
                ->name('operations.communications.conversations.assign');

            Route::post('/app/communications/conversations/{conversation}/reopen', ReopenConversationController::class)
                ->name('operations.communications.conversations.reopen');

            Route::post('/app/communications/conversations/{conversation}/internal-note', StoreConversationInternalNoteController::class)
                ->name('operations.communications.conversations.internal-note');

            Route::post('/app/communications/calls/{callSession}/note', StoreCallSessionNoteController::class)
                ->name('operations.communications.calls.note');

            Route::post('/app/communications/calls/{callSession}/mark-handled', CommunicationsMarkCallHandledController::class)
                ->name('operations.communications.calls.mark-handled');

            Route::post('/app/communications/conversations/{conversation}/mark-read', CommunicationsMarkConversationReadController::class)
                ->name('operations.communications.conversations.mark-read');

            Route::post('/app/communications/conversations/{conversation}/mark-handled', CommunicationsMarkConversationHandledController::class)
                ->name('operations.communications.conversations.mark-handled');

            Route::post('/app/communications/nudge/dismiss', DismissAdvisorNudgeController::class)
                ->name('operations.communications.nudge.dismiss');

            Route::post('/app/communications/nudge/act', ActOnAdvisorNudgeController::class)
                ->name('operations.communications.nudge.act');

            Route::get('/app/operations/observations', OperationalObservationsIndexController::class)
                ->middleware('permission:'.ArkCapability::SettingsManage->value)
                ->name('operations.observations.index');

            // Hidden platform admin — not in nav. Master admin only.
            Route::get('/app/platform/clusters', ClusterIndexController::class)
                ->name('platform.clusters.index');

            Route::middleware('permission:'.ArkCapability::SettingsManage->value)->group(function (): void {
                Route::redirect('/app/communications/shop', '/app/shop/communications');

                Route::prefix('app/shop')->group(function (): void {
                    Route::get('/communications', CommunicationsShopController::class)
                        ->name('operations.shop.communications');

                    Route::get('/people/{user}', CommunicationsPersonController::class)
                        ->name('operations.shop.people.show');

                    Route::post('/devices/{communicationDevice}/assign-station', AssignDiscoveredCommunicationDeviceController::class)
                        ->name('operations.shop.devices.assign-station');

                    Route::get('/devices/{communicationDevice}', CommunicationDeviceController::class)
                        ->name('operations.shop.devices.show');

                    Route::post('/devices', StoreCommunicationDeviceController::class)
                        ->name('operations.shop.devices.store');

                    Route::delete('/devices/{communicationDevice}', DestroyCommunicationDeviceController::class)
                        ->name('operations.shop.devices.destroy');

                    Route::post('/devices/{communicationDevice}/provision/generate', GenerateCommunicationDeviceConfigController::class)
                        ->name('operations.shop.devices.provision.generate');

                    Route::get('/devices/{communicationDevice}/provision/download', DownloadCommunicationDeviceConfigController::class)
                        ->name('operations.shop.devices.provision.download');

                    Route::patch('/communications/incoming-routing', UpdateCommunicationsIncomingRoutingController::class)
                        ->name('operations.shop.communications.incoming-routing.update');

                    Route::post('/workstations', StoreWorkstationController::class)
                        ->name('operations.shop.workstations.store');

                    Route::patch('/workstations/{workstation}', UpdateWorkstationController::class)
                        ->name('operations.shop.workstations.update');

                    Route::delete('/workstations/{workstation}', DestroyWorkstationController::class)
                        ->name('operations.shop.workstations.destroy');

                    Route::post('/workstations/{workstation}/extension', AssignWorkstationExtensionController::class)
                        ->name('operations.shop.workstations.extension.assign');
                });
            });

            Route::redirect('/app/communications/attention-queue', '/app/communications/inbox?filter=needs')
                ->middleware('front_door:attention')
                ->name('operations.communications.attention-queue');

            Route::redirect('/app/work/queues/comms', '/app/communications/inbox?filter=needs')
                ->middleware('front_door:attention');

            Route::get('/app/work/queues/{queue}', WorkQueueController::class)
                ->middleware('front_door:attention')
                ->name('operations.work.queue');

            Route::post('/app/work/follow-ups', AdvisorFollowUpStoreController::class)
                ->name('operations.work.follow-ups.store');

            Route::patch('/app/work/follow-ups/{followUp}/complete', AdvisorFollowUpCompleteController::class)
                ->name('operations.work.follow-ups.complete');

            Route::post('/app/work/tasks', AdvisorTaskStoreController::class)
                ->name('operations.work.tasks.store');

            Route::patch('/app/work/tasks/{task}/complete', AdvisorTaskCompleteController::class)
                ->name('operations.work.tasks.complete');

            Route::post('/app/work/decision-schedules', CustomerDecisionScheduleStoreController::class)
                ->name('operations.work.decision-schedules.store');

            Route::patch('/app/work/decision-schedules/{schedule}/clear', CustomerDecisionScheduleClearController::class)
                ->name('operations.work.decision-schedules.clear');

            Route::middleware('appointments.surface')->group(function (): void {
                Route::get('/app/appointments', AppointmentIndexController::class)
                    ->name('operations.appointments.index');

                Route::post('/app/appointments/board-view', AppointmentScheduleBoardViewController::class)
                    ->name('operations.appointments.board-view');

                // Canonical product entry — prefer over /app/appointments/create for new CTAs.
                Route::get('/app/schedule', ScheduleEntryController::class)
                    ->name('operations.schedule');

                Route::get('/app/appointments/create', AppointmentCreateController::class)
                    ->name('operations.appointments.create');

                Route::post('/app/appointments', AppointmentStoreController::class)
                    ->name('operations.appointments.store');

                Route::get('/app/appointments/{appointment}', AppointmentShowController::class)
                    ->name('operations.appointments.show');

                Route::patch('/app/appointments/{appointment}', AppointmentUpdateController::class)
                    ->name('operations.appointments.update');

                Route::patch('/app/appointments/{appointment}/reschedule', AppointmentRescheduleController::class)
                    ->name('operations.appointments.reschedule');

                Route::patch('/app/appointments/{appointment}/assign', AppointmentAssignController::class)
                    ->name('operations.appointments.assign');

                Route::patch('/app/appointments/{appointment}/status', AppointmentStatusController::class)
                    ->name('operations.appointments.status');

                Route::post('/app/repair-orders/{repairOrder}/appointments', ScheduleRepairOrderAppointmentController::class)
                    ->name('operations.repair-orders.appointments.store');

                Route::post('/app/appointments/{appointment}/sms/confirmation', AppointmentConfirmationSmsController::class)
                    ->name('operations.appointments.sms.confirmation');

                Route::patch('/app/appointments/{appointment}/sms/reminders', AppointmentReminderSettingsController::class)
                    ->name('operations.appointments.sms.reminders');

                Route::post('/app/appointments/request-availability', AppointmentRequestExceptionController::class)
                    ->name('operations.appointments.request-availability');
            });
        });

        Route::get('/app/workboard', OperationsIndexController::class)
            ->middleware('front_door:workboard')
            ->name('operations.workboard');

        Route::get('/app/api/workboard/triage', OperationsWorkboardTriageFragmentController::class)
            ->middleware('permission:'.ArkCapability::OperationsAccess->value)
            ->name('operations.workboard.triage.fragment');

        Route::get('/app/learn', [LearnArkController::class, 'index'])
            ->name('operations.learn.index');

        Route::get('/app/learn/print', LearnArkPrintController::class)
            ->name('operations.learn.print');

        Route::post('/app/learn/progress/heartbeat', [LearnArkProgressController::class, 'heartbeat'])
            ->name('operations.learn.progress.heartbeat');

        Route::post('/app/learn/progress/checkpoint', [LearnArkProgressController::class, 'checkpoint'])
            ->name('operations.learn.progress.checkpoint');

        Route::post('/app/learn/progress/complete', [LearnArkProgressController::class, 'complete'])
            ->name('operations.learn.progress.complete');

        Route::post('/app/learn/progress/snooze', [LearnArkProgressController::class, 'snooze'])
            ->name('operations.learn.progress.snooze');

        Route::post('/app/learn/training-gate', [LearnArkProgressController::class, 'trainingGate'])
            ->name('operations.learn.training-gate');

        Route::post('/app/learn/progress/video', [LearnArkProgressController::class, 'video'])
            ->name('operations.learn.progress.video');

        Route::get('/app/learn/media/{media}', LearnArticleMediaShowController::class)
            ->name('operations.learn.media.show');

        Route::post('/app/learn/{role}/{article}/media', [LearnArkMediaController::class, 'store'])
            ->middleware('permission:'.ArkCapability::SettingsManage->value)
            ->name('operations.learn.media.store');

        Route::delete('/app/learn/{role}/{article}/media/{media}', [LearnArkMediaController::class, 'destroy'])
            ->middleware('permission:'.ArkCapability::SettingsManage->value)
            ->name('operations.learn.media.destroy');

        Route::get('/app/learn-team-progress', LearnArkTeamProgressController::class)
            ->middleware('permission:'.ArkCapability::StaffManage->value)
            ->name('operations.learn.team-progress');

        Route::get('/app/learn/preview/{role}/{article}', LearnArkPreviewController::class)
            ->name('operations.learn.preview');

        Route::get('/app/learn/{role}/{article}', [LearnArkController::class, 'show'])
            ->name('operations.learn.show');

        Route::post('/app/workspace/activity', WorkspaceTabActivityController::class)
            ->name('operations.workspace.activity');

        Route::get('/app/intake', AdvisorIntakeIndexController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.intake.index');

        Route::get('/app/intake/new', AdvisorIntakeCreateController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.intake.create');

        Route::get('/app/intake/customers/search', AdvisorIntakeCustomerSearchController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.intake.customers.search');

        Route::get('/app/intake/customers/duplicates', AdvisorIntakeCustomerDuplicateController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.intake.customers.duplicates');

        Route::get('/app/intake/customers/{customer}', AdvisorIntakeCustomerShowController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.intake.customers.show');

        Route::get('/app/intake/vehicles/lookup', AdvisorIntakeVehicleLookupController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.intake.vehicles.lookup');

        Route::post('/app/intake', AdvisorIntakeStoreController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.intake.store');

        Route::post('/app/intake/leads', AdvisorIntakeWebsiteLeadStoreController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.intake.leads.store');

        Route::get('/app/leads', AdvisorLeadIndexController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.leads.index');

        Route::patch('/app/leads/{lead}/state', AdvisorLeadStateController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.leads.state');

        Route::get('/app/leads/{lead}/intake', AdvisorLeadIntakeController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.leads.intake');

        Route::get('/app/leads/{lead}/create-contact', [AdvisorLeadCreateContactController::class, 'create'])
            ->middleware('permission:'.ArkCapability::CustomersManage->value)
            ->name('operations.leads.create-contact');

        Route::post('/app/leads/{lead}/create-contact', [AdvisorLeadCreateContactController::class, 'store'])
            ->middleware('permission:'.ArkCapability::CustomersManage->value)
            ->name('operations.leads.create-contact.store');

        Route::get('/app/ingress/create-contact', [AdvisorIngressCreateContactController::class, 'create'])
            ->middleware('permission:'.ArkCapability::CustomersManage->value)
            ->name('operations.ingress.create-contact');

        Route::post('/app/ingress/create-contact', [AdvisorIngressCreateContactController::class, 'store'])
            ->middleware('permission:'.ArkCapability::CustomersManage->value)
            ->name('operations.ingress.create-contact.store');

        Route::get('/repair-orders', RepairOrderIndexController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersView->value)
            ->name('operations.repair-orders.index');

        Route::get('/app/reports', ReportsIndexController::class)
            ->middleware('permission:'.ArkCapability::FinancialView->value)
            ->name('operations.reports.index');

        Route::get('/app/reports/end-of-day', ReportsEndOfDayController::class)
            ->middleware('permission:'.ArkCapability::FinancialView->value)
            ->name('operations.reports.end-of-day');

        Route::get('/app/reports/operations', OperationalReportController::class)
            ->middleware('permission:'.ArkCapability::FinancialView->value)
            ->name('operations.reports.operational');

        Route::middleware('owner.workspace')->group(function (): void {
            Route::get('/app/owner/day-review', OwnerDayReviewController::class)
                ->name('operations.owner.day-review');
            // Legacy path retained for bookmarks and older links.
            Route::get('/app/owner/bookend', OwnerDayReviewController::class)
                ->name('operations.owner.bookend');

            Route::get('/app/owner/technician-production', [TechnicianProductionAssistController::class, 'index'])
                ->name('operations.owner.technician-production.index');

            Route::get('/app/owner/technician-production/{user}', [TechnicianProductionAssistController::class, 'show'])
                ->name('operations.owner.technician-production.show');

            Route::post('/app/owner/technician-production/{user}/time', [TechnicianProductionAssistController::class, 'storeTime'])
                ->name('operations.owner.technician-production.time');

            Route::get('/app/owner/parts-matrix-tune', PartsMatrixTuneController::class)
                ->name('operations.owner.parts-matrix-tune');

            Route::get('/app/owner/call-intelligence', [OwnerCallIntelligenceController::class, 'index'])
                ->name('operations.owner.call-intelligence');

            Route::get('/app/owner/call-intelligence/sms/{slice}', [OwnerCallIntelligenceController::class, 'showSms'])
                ->name('operations.owner.call-intelligence.sms.show');

            Route::post('/app/owner/call-intelligence/sms/{slice}/analyze', [OwnerCallIntelligenceController::class, 'analyzeSms'])
                ->name('operations.owner.call-intelligence.sms.analyze');

            Route::post('/app/owner/call-intelligence/sms/{slice}/follow-up', ToggleSmsIntelligenceCoachingFollowUpController::class)
                ->name('operations.owner.call-intelligence.sms.follow-up.toggle');

            Route::get('/app/owner/call-intelligence/{callSession}', [OwnerCallIntelligenceController::class, 'show'])
                ->name('operations.owner.call-intelligence.show');

            Route::post('/app/owner/call-intelligence/{callSession}/analyze', [OwnerCallIntelligenceController::class, 'analyze'])
                ->name('operations.owner.call-intelligence.analyze');

            Route::post('/app/owner/call-intelligence/{callSession}/follow-up', ToggleCallSessionCoachingFollowUpController::class)
                ->name('operations.owner.call-intelligence.follow-up.toggle');

            Route::get('/app/owner/call-intelligence/{callSession}/coaching-pdf', CallSessionCoachingPdfController::class)
                ->name('operations.owner.call-intelligence.coaching-pdf');

            Route::post('/app/owner/call-intelligence/{callSession}/coaching-log', StoreStaffCoachingLogController::class)
                ->name('operations.owner.call-intelligence.coaching-log.store');

            Route::get('/app/owner/staff/{user}/coaching', OwnerStaffCoachingController::class)
                ->name('operations.owner.staff.coaching');
        });

        Route::get('/app/customers/search', CustomerSearchController::class)
            ->middleware('permission:'.ArkCapability::CustomersManage->value)
            ->name('operations.customers.search');

        Route::get('/app/vehicles/search', VehicleSearchController::class)
            ->middleware('permission:'.ArkCapability::VehiclesManage->value)
            ->name('operations.vehicles.search');

        Route::get('/app/caller-lookup', CallerLookupController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersView->value)
            ->name('operations.caller-lookup');

        Route::get('/app/api/telephony/incoming-call/active', IncomingCallActiveController::class)
            ->middleware('permission:'.ArkCapability::OperationsAccess->value)
            ->name('operations.telephony.incoming-call.active');

        Route::post('/app/api/telephony/incoming-call/dismiss', IncomingCallDismissController::class)
            ->middleware('permission:'.ArkCapability::OperationsAccess->value)
            ->name('operations.telephony.incoming-call.dismiss');

        Route::get('/app/api/comms/interrupts', CommsInterruptController::class)
            ->middleware('permission:'.ArkCapability::OperationsAccess->value)
            ->name('operations.comms.interrupts');

        Route::post('/app/api/leads/website-interrupt/dismiss', WebsiteLeadInterruptDismissController::class)
            ->middleware('permission:'.ArkCapability::OperationsAccess->value)
            ->name('operations.leads.website-interrupt.dismiss');

        Route::post('/app/api/portal/customer-activity-interrupt/dismiss', PortalCustomerActivityInterruptDismissController::class)
            ->middleware('permission:'.ArkCapability::OperationsAccess->value)
            ->name('operations.portal.customer-activity-interrupt.dismiss');

        Route::get('/app/api/telephony/call-queue', IncomingCallQueueController::class)
            ->middleware('permission:'.ArkCapability::OperationsAccess->value)
            ->name('operations.telephony.call-queue');

        Route::get('/app/api/telephony/call-sessions/{callSession}/caller-context', CallSessionCallerContextController::class)
            ->middleware('permission:'.ArkCapability::OperationsAccess->value)
            ->name('operations.telephony.call-sessions.caller-context');

        Route::post('/app/api/staff/presence/heartbeat', StaffPresenceHeartbeatController::class)
            ->middleware('permission:'.ArkCapability::OperationsAccess->value)
            ->name('operations.staff.presence.heartbeat');

        Route::post('/app/workstation/bind', BindWorkstationController::class)
            ->middleware('permission:'.ArkCapability::OperationsAccess->value)
            ->name('operations.workstation.bind');

        Route::post('/app/workstation/bind/dismiss', [BindWorkstationController::class, 'dismiss'])
            ->middleware('permission:'.ArkCapability::OperationsAccess->value)
            ->name('operations.workstation.bind.dismiss');

        Route::post('/app/workstation/lock', [WorkstationOperatorController::class, 'lock'])
            ->middleware('permission:'.ArkCapability::OperationsAccess->value)
            ->name('operations.workstation.lock');

        Route::post('/app/workstation/unlock', [WorkstationOperatorController::class, 'unlock'])
            ->middleware('permission:'.ArkCapability::OperationsAccess->value)
            ->name('operations.workstation.unlock');

        Route::post('/app/workstation/pin', [WorkstationOperatorController::class, 'storePin'])
            ->middleware('permission:'.ArkCapability::OperationsAccess->value)
            ->name('operations.workstation.pin.store');

        Route::patch('/app/workstation/pin', [WorkstationOperatorController::class, 'updatePin'])
            ->middleware('permission:'.ArkCapability::OperationsAccess->value)
            ->name('operations.workstation.pin.update');

        Route::get('/app/api/workstation/staff', [WorkstationOperatorController::class, 'staff'])
            ->middleware('permission:'.ArkCapability::OperationsAccess->value)
            ->name('operations.workstation.staff');

        Route::redirect('/app/communications/queue', '/app/communications/inbox?filter=needs')
            ->middleware('permission:'.ArkCapability::OperationsAccess->value)
            ->name('operations.communications.queue');

        Route::get('/app/api/communications/queue', CommunicationsQueueApiController::class)
            ->middleware('permission:'.ArkCapability::OperationsAccess->value)
            ->name('operations.communications.queue.api');

        Route::get('/app/api/communications/recent-activity', CommunicationsRecentActivityFragmentController::class)
            ->middleware('permission:'.ArkCapability::OperationsAccess->value)
            ->name('operations.communications.recent-activity.fragment');

        Route::get('/app/api/communications/workboard', CommunicationWorkboardFragmentController::class)
            ->middleware('permission:'.ArkCapability::OperationsAccess->value)
            ->name('operations.communications.workboard.fragment');

        Route::post('/app/api/conversations/{conversation}/read', MarkConversationReadController::class)
            ->middleware('permission:'.ArkCapability::OperationsAccess->value)
            ->name('operations.conversations.read');

        Route::post('/app/api/conversations/{conversation}/resolve', ResolveConversationController::class)
            ->middleware('permission:'.ArkCapability::OperationsAccess->value)
            ->name('operations.conversations.resolve');

        Route::post('/app/api/conversations/{conversation}/link-customer', LinkMessengerConversationController::class)
            ->middleware('permission:'.ArkCapability::OperationsAccess->value)
            ->name('operations.conversations.link-customer');

        Route::get('/app/conversations/{conversation}/reply', [CommunicationsLegacySurfaceRedirectController::class, 'reply'])
            ->middleware('permission:'.ArkCapability::OperationsAccess->value)
            ->name('operations.conversations.reply');

        Route::post('/app/api/conversations/{conversation}/messages', SendConversationContactMessageController::class)
            ->middleware('permission:'.ArkCapability::OperationsAccess->value)
            ->name('operations.conversations.messages.store');

        Route::post('/app/api/conversations/{conversation}/send-estimate', SendConversationEstimateLinkController::class)
            ->middleware('permission:'.ArkCapability::OperationsAccess->value)
            ->name('operations.communications.conversations.send-estimate');

        Route::post('/app/api/conversations/{conversation}/send-payment', SendConversationPaymentLinkController::class)
            ->middleware('permission:'.ArkCapability::OperationsAccess->value)
            ->name('operations.communications.conversations.send-payment');

        Route::post('/app/api/conversations/{conversation}/send-deposit', SendConversationDepositRequestLinkController::class)
            ->middleware('permission:'.ArkCapability::OperationsAccess->value)
            ->name('operations.communications.conversations.send-deposit');

        Route::post('/app/api/telephony/call-queue/{callSession}/worked', MarkCallSessionWorkedController::class)
            ->middleware('permission:'.ArkCapability::OperationsAccess->value)
            ->name('operations.telephony.call-queue.worked');

        Route::post('/app/telephony/calls/{callSession}/claim', ClaimCallSessionController::class)
            ->middleware('permission:'.ArkCapability::OperationsAccess->value)
            ->name('operations.telephony.calls.claim');

        Route::post('/app/api/telephony/callback', TelephonyCallbackController::class)
            ->middleware('permission:'.ArkCapability::OperationsAccess->value)
            ->name('operations.telephony.callback');

        Route::get('/app/telephony/call-sessions/{callSession}/recording', CallRecordingPlaybackController::class)
            ->middleware('permission:'.ArkCapability::OperationsAccess->value)
            ->name('operations.telephony.call-sessions.recording');

        Route::post('/app/api/customers/{customer}/conversation-messages', SendConversationMessageController::class)
            ->middleware('permission:'.ArkCapability::OperationsAccess->value)
            ->name('operations.customers.conversation-messages.store');

        Route::post('/app/api/customers/{customer}/conversation-actions/cancel-scheduled-sms', CancelScheduledOutboundSmsController::class)
            ->middleware('permission:'.ArkCapability::OperationsAccess->value)
            ->name('operations.customers.conversation-actions.cancel-scheduled-sms');

        Route::post('/app/api/customers/{customer}/conversation-actions/send-address', SendShopAddressController::class)
            ->middleware('permission:'.ArkCapability::OperationsAccess->value)
            ->name('operations.customers.conversation-actions.send-address');

        Route::post('/app/api/customers/{customer}/conversation-actions/{messageAction}', SendAdvisorMessageActionController::class)
            ->middleware('permission:'.ArkCapability::OperationsAccess->value)
            ->whereIn('messageAction', ['address', 'pickup', 'hours', 'tow', 'wifi'])
            ->name('operations.customers.conversation-actions.send');

        Route::get('/app/api/customers/{customer}/hub-comms/updates', CustomerHubCommsUpdatesController::class)
            ->middleware('permission:'.ArkCapability::OperationsAccess->value)
            ->name('operations.customers.hub-comms.updates');

        Route::post('/app/api/repair-orders/{repairOrder}/conversation-actions/send-estimate', SendEstimateLinkController::class)
            ->middleware('permission:'.ArkCapability::OperationsAccess->value)
            ->name('operations.repair-orders.conversation-actions.send-estimate');

        Route::post('/app/api/repair-orders/{repairOrder}/conversation-actions/cancel-scheduled-estimate', CancelScheduledOutboundEstimateController::class)
            ->middleware('permission:'.ArkCapability::OperationsAccess->value)
            ->name('operations.repair-orders.conversation-actions.cancel-scheduled-estimate');

        Route::get('/app/api/repair-orders/{repairOrder}/estimate-portal-link', RepairOrderEstimatePortalLinkController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.estimate-portal-link');

        Route::get('/app/repair-orders/{repairOrder}/customer-display', RepairOrderCustomerDisplayController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.customer-display');

        Route::get('/app/repair-orders/{repairOrder}/customer-display/fragment', [RepairOrderCustomerDisplayController::class, 'fragment'])
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.customer-display.fragment');

        Route::get('/app/repair-orders/{repairOrder}/portal-preview', RepairOrderPortalEstimatePreviewController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.portal-preview');

        Route::get('/app/api/repair-orders/{repairOrder}/inspection-portal-link', RepairOrderInspectionPortalLinkController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.inspection-portal-link');

        Route::get('/app/repair-orders/{repairOrder}/inspection-portal-preview', RepairOrderPortalInspectionPreviewController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.inspection-portal-preview');

        Route::get('/app/repair-orders/{repairOrder}/inspection/print', [RepairOrderInspectionPrintController::class, 'print'])
            ->middleware('permission:'.ArkCapability::RepairOrdersView->value)
            ->name('operations.repair-orders.inspection.print');

        Route::get('/app/repair-orders/{repairOrder}/inspection/pdf', [RepairOrderInspectionPrintController::class, 'pdf'])
            ->middleware('permission:'.ArkCapability::RepairOrdersView->value)
            ->name('operations.repair-orders.inspection.pdf');

        Route::get('/app/api/repair-orders/{repairOrder}/payment-portal-link', RepairOrderPaymentPortalLinkController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.payment-portal-link');

        Route::get('/app/repair-orders/{repairOrder}/payment-portal-preview', RepairOrderPortalPaymentPreviewController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.payment-portal-preview');

        Route::get('/app/repair-orders/{repairOrder}/portal-session', RepairOrderPortalSessionController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.portal-session');

        Route::post('/app/api/repair-orders/{repairOrder}/conversation-actions/send-payment', SendPaymentLinkController::class)
            ->middleware('permission:'.ArkCapability::OperationsAccess->value)
            ->name('operations.repair-orders.conversation-actions.send-payment');

        Route::post('/app/api/repair-orders/{repairOrder}/conversation-actions/send-deposit', SendDepositRequestLinkController::class)
            ->middleware('permission:'.ArkCapability::OperationsAccess->value)
            ->name('operations.repair-orders.conversation-actions.send-deposit');

        Route::post('/app/api/repair-orders/{repairOrder}/conversation-actions/send-inspection', SendInspectionLinkController::class)
            ->middleware('permission:'.ArkCapability::OperationsAccess->value)
            ->name('operations.repair-orders.conversation-actions.send-inspection');

        Route::post('/app/repair-orders/{repairOrder}/commitments', OperationalCommitmentStoreController::class)
            ->middleware('permission:'.ArkCapability::OperationsAccess->value)
            ->name('operations.repair-orders.commitments.store');

        Route::get('/app/conversations/{conversation}/messages/{message}/attachments/{attachment}', ConversationAttachmentShowController::class)
            ->middleware('permission:'.ArkCapability::OperationsAccess->value)
            ->name('operations.conversation-attachments.show');

        Route::get('/app/staff', fn () => redirect()->route('operations.settings.shop.edit', ['section' => 'staff']))
            ->middleware('permission:'.ArkCapability::StaffManage->value)
            ->name('operations.staff.index');

        require __DIR__.'/operations/settings.php';

        Route::post('/app/customers', CustomerStoreController::class)
            ->middleware('permission:'.ArkCapability::CustomersManage->value)
            ->name('operations.customers.store');

        Route::get('/app/customers/{customer}', CustomerShowController::class)
            ->middleware('permission:'.ArkCapability::CustomersManage->value)
            ->name('operations.customers.show');

        Route::patch('/app/customers/{customer}', CustomerUpdateController::class)
            ->middleware('permission:'.ArkCapability::CustomersManage->value)
            ->name('operations.customers.update');

        Route::post('/app/customers/{customer}/documents', DocumentUploadController::class)
            ->middleware('permission:'.ArkCapability::CustomersManage->value)
            ->name('operations.customers.documents.store');
        Route::post('/app/customers/{customer}/documents/scan', DocumentScanController::class)
            ->middleware('permission:'.ArkCapability::CustomersManage->value)
            ->name('operations.customers.documents.scan');
        Route::get('/app/customers/{customer}/documents/{document}/view', DocumentViewerController::class)
            ->middleware('permission:'.ArkCapability::CustomersManage->value.'|'.ArkCapability::RepairOrdersView->value)
            ->name('operations.customers.documents.viewer');
        Route::get('/app/customers/{customer}/documents/{document}', DocumentStreamController::class)
            ->middleware('permission:'.ArkCapability::CustomersManage->value.'|'.ArkCapability::RepairOrdersView->value)
            ->name('operations.customers.documents.show');
        Route::get('/app/customers/{customer}/documents/{document}/download', DocumentDownloadController::class)
            ->middleware('permission:'.ArkCapability::CustomersManage->value.'|'.ArkCapability::RepairOrdersView->value)
            ->name('operations.customers.documents.download');
        Route::post('/app/customers/{customer}/documents/{document}/email', DocumentEmailController::class)
            ->middleware('permission:'.ArkCapability::CustomersManage->value.'|'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.customers.documents.email');
        Route::post('/app/customers/{customer}/documents/{document}/attach', DocumentAttachController::class)
            ->middleware('permission:'.ArkCapability::CustomersManage->value)
            ->name('operations.customers.documents.attach');
        Route::post('/app/customers/{customer}/documents/{document}/visibility', DocumentVisibilityController::class)
            ->middleware('permission:'.ArkCapability::CustomersManage->value)
            ->name('operations.customers.documents.visibility');
        Route::post('/app/customers/{customer}/documents/{document}/rotate', DocumentRotateController::class)
            ->middleware('permission:'.ArkCapability::CustomersManage->value)
            ->name('operations.customers.documents.rotate');
        Route::delete('/app/customers/{customer}/documents/{document}', DocumentRetireController::class)
            ->middleware('permission:'.ArkCapability::CustomersManage->value)
            ->name('operations.customers.documents.retire');

        Route::post('/app/customers/{customer}/vehicles', VehicleStoreController::class)
            ->middleware('permission:'.ArkCapability::VehiclesManage->value)
            ->name('operations.customers.vehicles.store');

        Route::post('/app/vehicles/decode-vin', VehicleVinDecodeController::class)
            ->middleware('permission:'.ArkCapability::VehiclesManage->value)
            ->name('operations.vehicles.decode-vin');

        Route::patch('/app/customers/{customer}/vehicles/{vehicle}', VehicleUpdateController::class)
            ->middleware('permission:'.ArkCapability::VehiclesManage->value)
            ->name('operations.customers.vehicles.update');

        Route::delete('/app/customers/{customer}/vehicles/{vehicle}', VehicleDestroyController::class)
            ->middleware('permission:'.ArkCapability::VehiclesManage->value)
            ->name('operations.customers.vehicles.destroy');

        Route::post('/app/customers/{customer}/repair-orders/drafts', RepairOrderDraftStoreController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.customers.repair-orders.drafts.store');

        Route::get('/app/repair-orders/{repairOrder}', RepairOrderShowController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersView->value)
            ->name('operations.repair-orders.show');

        // Temporary GET bridges — bookmarks only. Removal gate: 2026-09-01 + zero access-log hits.
        // Do not restore competing Repair Order page variants on these paths.
        Route::get('/app/repair-orders/{repairOrder}/edit', RepairOrderLegacySurfaceRedirectController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersView->value);

        Route::get('/app/repair-orders/{repairOrder}/builder', RepairOrderLegacySurfaceRedirectController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersView->value);

        Route::get('/app/repair-orders/{repairOrder}/estimate-review', RepairOrderLegacySurfaceRedirectController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersView->value)
            ->name('operations.repair-orders.estimate-review');

        Route::get('/app/repair-orders/{repairOrder}/workspace-tabs/{tab}', RepairOrderWorkspaceTabController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersView->value)
            ->whereIn('tab', RepairOrderWorkspaceTabPresenter::TABS)
            ->name('operations.repair-orders.workspace-tabs.show');

        Route::get('/app/repair-orders/{repairOrder}/print-key-tag', KeyTagPrintController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersView->value)
            ->name('operations.repair-orders.print-key-tag');

        Route::get('/app/repair-orders/{repairOrder}/print-oil-change-sticker', OilChangeStickerPrintController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersView->value)
            ->name('operations.repair-orders.print-oil-change-sticker');

        Route::post('/app/repair-orders/{repairOrder}/evidence', RepairOrderEvidenceStoreController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.evidence.store');
        Route::get('/app/repair-orders/{repairOrder}/evidence/{evidence}', RepairOrderEvidenceShowController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersView->value)
            ->name('operations.repair-orders.evidence.show');
        Route::post('/app/repair-orders/{repairOrder}/evidence/{evidence}/visibility', RepairOrderEvidenceVisibilityController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.evidence.visibility');
        Route::post('/app/repair-orders/{repairOrder}/evidence/{evidence}/primary', RepairOrderEvidencePrimaryController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.evidence.primary');
        Route::delete('/app/repair-orders/{repairOrder}/evidence/{evidence}', RepairOrderEvidenceRetireController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.evidence.retire');

        Route::post('/app/repair-orders/{repairOrder}/documents', RepairOrderDocumentUploadController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.documents.store');
        Route::post('/app/repair-orders/{repairOrder}/documents/scan', RepairOrderDocumentScanController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.documents.scan');
        Route::post('/app/repair-orders/{repairOrder}/documents/attach', RepairOrderDocumentAttachController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.documents.attach');

        Route::post('/app/repair-orders/{repairOrder}/maintenance/engine-oil', AddEngineOilServiceController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.maintenance.engine-oil.store');

        Route::post('/app/repair-orders/{repairOrder}/maintenance/{maintenanceService}/extra-quarts', AddExtraOilQuartsAtCostController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.maintenance.extra-quarts.store');

        Route::get('/app/repair-orders/{repairOrder}/maintenance/{maintenanceService}/confirm', [ConfirmEngineOilInstalledController::class, 'show'])
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.maintenance.confirm');

        Route::post('/app/repair-orders/{repairOrder}/maintenance/{maintenanceService}/confirm', [ConfirmEngineOilInstalledController::class, 'store'])
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.maintenance.confirm.store');

        Route::post('/app/repair-orders/{repairOrder}/work-authorization/testing', AuthorizeTestingPackageController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.work-authorization.testing.store');

        Route::get('/app/work-templates/search', WorkTemplateSearchController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.work-templates.search');

        Route::get('/app/repair-orders/{repairOrder}/work-templates/{workTemplate}/historical-recall', HistoricalWorkRecallController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.work-templates.historical-recall');

        Route::post('/app/repair-orders/{repairOrder}/work-templates/{workTemplate}/historical-recall/assist', HistoricalWorkRecallAssistController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.work-templates.historical-recall.assist');

        Route::get('/app/repair-orders/{repairOrder}/dragon-assist/{assistRequest}', HistoricalWorkRecallAssistStatusController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.dragon-assist.show');

        Route::post('/app/repair-orders/{repairOrder}/concerns/{concern}/dragon-service-advisor', RequestServiceAdvisorRewriteController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.concerns.dragon-service-advisor');

        Route::post('/app/repair-orders/{repairOrder}/concerns/{concern}/dragon-service-advisor/{assistRequest}/apply', ApplyServiceAdvisorRewriteController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.concerns.dragon-service-advisor.apply');

        Route::post('/app/repair-orders/{repairOrder}/dragon-service-advisor/visit-reason', RequestVisitReasonRewriteController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.dragon-service-advisor.visit-reason');

        Route::post('/app/repair-orders/{repairOrder}/dragon-service-advisor/visit-reason/{assistRequest}/apply', ApplyVisitReasonRewriteController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.dragon-service-advisor.visit-reason.apply');

        Route::post('/app/repair-orders/{repairOrder}/lines/{line}/dragon-service-advisor', RequestLineNoteRewriteController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.lines.dragon-service-advisor');

        Route::post('/app/repair-orders/{repairOrder}/lines/{line}/dragon-service-advisor/{assistRequest}/apply', ApplyLineNoteRewriteController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.lines.dragon-service-advisor.apply');

        Route::post('/app/repair-orders/{repairOrder}/dragon-service-advisor/{application}/revert', RevertServiceAdvisorRewriteController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.dragon-service-advisor.revert');

        Route::post('/app/repair-orders/{repairOrder}/review-estimate-notes', RequestReviewEstimateNotesController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.review-estimate-notes');

        Route::post('/app/repair-orders/{repairOrder}/review-estimate-notes/{assistRequest}/apply', ApplyReviewEstimateNotesProposalController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.review-estimate-notes.apply');

        Route::post('/app/repair-orders/{repairOrder}/work-templates/apply', ApplyWorkTemplateController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.work-templates.apply');

        Route::get('/app/repair-orders/{repairOrder}/work-authorization/{workAuthorization}/outcome', [RecordTestingPackageOutcomeController::class, 'create'])
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.work-authorization.outcome');

        Route::post('/app/repair-orders/{repairOrder}/work-authorization/{workAuthorization}/outcome', [RecordTestingPackageOutcomeController::class, 'store'])
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.work-authorization.outcome.store');

        Route::get('/app/repair-orders/{repairOrder}/lines/{line}/print-parts-label', PartsLabelPrintController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersView->value)
            ->name('operations.repair-orders.lines.print-parts-label');

        Route::post('/app/api/qz/sign-message', [QzTraySignController::class, 'signMessage'])
            ->middleware('permission:'.ArkCapability::RepairOrdersView->value)
            ->name('operations.printing.qz.sign');

        Route::get('/app/api/qz/sign-health', [QzTraySignController::class, 'health'])
            ->middleware('permission:'.ArkCapability::SettingsManage->value)
            ->name('operations.printing.qz.sign-health');

        Route::get('/app/api/printing/health', [QzTraySignController::class, 'printingHealth'])
            ->middleware('permission:'.ArkCapability::SettingsManage->value)
            ->name('operations.printing.health');

        if (app()->environment('local')) {
            Route::get('/dev/qz-poc', QzPrintingPocController::class)
                ->middleware('permission:'.ArkCapability::RepairOrdersView->value)
                ->name('dev.qz-poc');
        }

        if (app()->environment('local', 'testing')) {
            Route::post('/app/dev/telephony/simulate-incoming-call', SimulateIncomingCallController::class)
                ->middleware('permission:'.ArkCapability::SettingsManage->value)
                ->name('dev.telephony.simulate-incoming-call');
        }

        Route::get('/app/api/printing/printer', [PrintRoutingController::class, 'show'])
            ->middleware('permission:'.ArkCapability::RepairOrdersView->value)
            ->name('operations.printing.printer');

        Route::post('/app/repair-orders/{repairOrder}/estimate-documents', EstimateDocumentStoreController::class)
            ->middleware('permission:'.ArkCapability::EstimateDocumentsManage->value)
            ->name('operations.repair-orders.estimate-documents.store');

        Route::get('/app/repair-orders/{repairOrder}/estimate', [EstimateDocumentOpenController::class, 'show'])
            ->middleware('permission:'.ArkCapability::RepairOrdersView->value)
            ->name('operations.repair-orders.estimate.show');

        Route::get('/app/repair-orders/{repairOrder}/estimate/pdf', [EstimateDocumentOpenController::class, 'pdf'])
            ->middleware('permission:'.ArkCapability::RepairOrdersView->value)
            ->name('operations.repair-orders.estimate.pdf');

        Route::get('/app/repair-orders/{repairOrder}/estimate/pdf/download', [EstimateDocumentOpenController::class, 'download'])
            ->middleware('permission:'.ArkCapability::RepairOrdersView->value)
            ->name('operations.repair-orders.estimate.pdf.download');

        Route::get('/app/repair-orders/{repairOrder}/sheets/intake/pdf', [RepairOrderOperationalSheetController::class, 'intakePdf'])
            ->middleware('permission:'.ArkCapability::RepairOrdersView->value)
            ->name('operations.repair-orders.sheets.intake.pdf');

        Route::get('/app/repair-orders/{repairOrder}/sheets/tech/pdf', [RepairOrderOperationalSheetController::class, 'techPdf'])
            ->middleware('permission:'.ArkCapability::RepairOrdersView->value)
            ->name('operations.repair-orders.sheets.tech.pdf');

        Route::post('/app/repair-orders/{repairOrder}/estimate/email', EstimateDocumentEmailController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.estimate.email');

        Route::get('/app/repair-orders/{repairOrder}/estimate-documents/{document}', EstimateDocumentShowController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersView->value)
            ->name('operations.repair-orders.estimate-documents.show');

        Route::get('/app/repair-orders/{repairOrder}/estimate-documents/{document}/pdf', [EstimateDocumentPdfController::class, 'show'])
            ->middleware('permission:'.ArkCapability::RepairOrdersView->value)
            ->name('operations.repair-orders.estimate-documents.pdf.show');

        Route::get('/app/repair-orders/{repairOrder}/estimate-documents/{document}/pdf/download', [EstimateDocumentPdfController::class, 'download'])
            ->middleware('permission:'.ArkCapability::RepairOrdersView->value)
            ->name('operations.repair-orders.estimate-documents.pdf.download');

        Route::post('/app/repair-orders/{repairOrder}/post', RepairOrderPostController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersCloseout->value)
            ->name('operations.repair-orders.post');

        Route::patch('/app/repair-orders/{repairOrder}/lifecycle', RepairOrderLifecycleController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value.'|'.ArkCapability::RepairOrdersLifecycle->value)
            ->name('operations.repair-orders.lifecycle.update');

        Route::post('/app/repair-orders/{repairOrder}/review-request', SendReviewRequestController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value.'|'.ArkCapability::RepairOrdersLifecycle->value)
            ->name('operations.repair-orders.review-request.send');

        Route::post('/app/repair-orders/{repairOrder}/invoice', RepairOrderInvoiceGenerateController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersCloseout->value)
            ->name('operations.repair-orders.invoice.store');

        Route::post('/app/repair-orders/{repairOrder}/invoice/refresh', RepairOrderInvoiceRefreshController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersCloseout->value)
            ->name('operations.repair-orders.invoice.refresh');

        Route::patch('/app/repair-orders/{repairOrder}/deposit', RepairOrderDepositController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersCloseout->value)
            ->name('operations.repair-orders.deposit.update');

        Route::patch('/app/repair-orders/{repairOrder}/payment', RepairOrderPaymentController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersCloseout->value)
            ->name('operations.repair-orders.payment.update');

        Route::post('/app/repair-orders/{repairOrder}/refund', RepairOrderLedgerRefundController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersCloseout->value)
            ->name('operations.repair-orders.refund.store');

        Route::post('/app/repair-orders/{repairOrder}/waive-balance', RepairOrderLedgerWriteOffController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersCloseout->value)
            ->name('operations.repair-orders.waive-balance.store');

        Route::delete('/app/repair-orders/{repairOrder}/ledger-entries/{entry}', RepairOrderLedgerVoidController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersCloseout->value)
            ->name('operations.repair-orders.ledger-entries.destroy');

        Route::post('/app/repair-orders/{repairOrder}/invoice/email', InvoiceDocumentEmailController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.invoice.email');

        Route::patch('/app/repair-orders/{repairOrder}/technician-assignment', RepairOrderTechnicianAssignmentController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.technician-assignment.update');

        Route::patch('/app/repair-orders/{repairOrder}/mileage', RepairOrderMileageUpdateController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.mileage.update');

        Route::patch('/app/repair-orders/{repairOrder}/vehicle', RepairOrderVehicleUpdateController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.vehicle.update');

        Route::patch('/app/repair-orders/{repairOrder}/visit-posture', RepairOrderVisitPostureUpdateController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.visit-posture.update');
        Route::patch('/app/repair-orders/{repairOrder}/visit-reason', RepairOrderVisitReasonUpdateController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.visit-reason.update');
        Route::post('/app/repair-orders/{repairOrder}/visit-reason/concerns/accept', RepairOrderVisitReasonConcernAcceptController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.visit-reason.concerns.accept');
        Route::post('/app/repair-orders/{repairOrder}/visit-reason/concerns/dismiss', RepairOrderVisitReasonConcernProposeDismissController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.visit-reason.concerns.dismiss');

        Route::get('/app/repair-orders/{repairOrder}/inspection', RepairOrderInspectionShowController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersView->value)
            ->name('operations.repair-orders.inspection.show');

        Route::post('/app/repair-orders/{repairOrder}/inspection/template', RepairOrderInspectionTemplateAssignController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.inspection.template.assign');

        Route::post('/app/repair-orders/{repairOrder}/inspection/reset', RepairOrderInspectionResetController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value.'|'.ArkCapability::RepairOrdersLifecycle->value)
            ->name('operations.repair-orders.inspection.reset');

        Route::post('/app/repair-orders/{repairOrder}/inspection/walk-link', RepairOrderInspectionWalkLinkSendController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersView->value)
            ->name('operations.repair-orders.inspection.walk-link.send');

        Route::patch('/app/repair-orders/{repairOrder}/inspection/notes', RepairOrderInspectionNotesUpdateController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value.'|'.ArkCapability::RepairOrdersLifecycle->value)
            ->name('operations.repair-orders.inspection.notes.update');

        Route::post('/app/repair-orders/{repairOrder}/inspection/findings', RepairOrderInspectionFindingStoreController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value.'|'.ArkCapability::RepairOrdersLifecycle->value)
            ->name('operations.repair-orders.inspection.findings.store');

        Route::post('/app/repair-orders/{repairOrder}/inspection/items', RepairOrderInspectionItemStoreController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value.'|'.ArkCapability::RepairOrdersLifecycle->value)
            ->name('operations.repair-orders.inspection.items.store');

        Route::patch('/app/repair-orders/{repairOrder}/inspection/points/{item}', RepairOrderInspectionPointUpdateController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value.'|'.ArkCapability::RepairOrdersLifecycle->value)
            ->name('operations.repair-orders.inspection.points.update');

        Route::patch('/app/repair-orders/{repairOrder}/inspection/items/{item}', RepairOrderInspectionItemUpdateController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value.'|'.ArkCapability::RepairOrdersLifecycle->value)
            ->name('operations.repair-orders.inspection.items.update');

        Route::post('/app/repair-orders/{repairOrder}/inspection/items/{item}/measurements', RepairOrderInspectionMeasurementStoreController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value.'|'.ArkCapability::RepairOrdersLifecycle->value)
            ->name('operations.repair-orders.inspection.measurements.store');

        Route::delete('/app/repair-orders/{repairOrder}/inspection/measurements/{measurement}', RepairOrderInspectionMeasurementDestroyController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value.'|'.ArkCapability::RepairOrdersLifecycle->value)
            ->name('operations.repair-orders.inspection.measurements.destroy');

        Route::post('/app/repair-orders/{repairOrder}/inspection/items/{item}/photos', RepairOrderInspectionPhotoStoreController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value.'|'.ArkCapability::RepairOrdersLifecycle->value)
            ->name('operations.repair-orders.inspection.photos.store');

        Route::get('/app/repair-orders/{repairOrder}/inspection/photos/{photo}', RepairOrderInspectionPhotoShowController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersView->value)
            ->name('operations.repair-orders.inspection.photos.show');

        Route::delete('/app/repair-orders/{repairOrder}/inspection/photos/{photo}', RepairOrderInspectionPhotoDestroyController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value.'|'.ArkCapability::RepairOrdersLifecycle->value)
            ->name('operations.repair-orders.inspection.photos.destroy');

        Route::post('/app/repair-orders/{repairOrder}/worksheet-sessions/heartbeat', [RepairOrderWorksheetSessionController::class, 'heartbeat'])
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.worksheet-sessions.heartbeat');

        Route::post('/app/repair-orders/{repairOrder}/worksheet-sessions/release', [RepairOrderWorksheetSessionController::class, 'release'])
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.worksheet-sessions.release');

        Route::post('/app/repair-orders/{repairOrder}/communications', OperationalCommunicationStoreController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.communications.store');

        Route::get('/app/repair-orders/{repairOrder}/concerns/vocabulary-suggest', RepairOrderConcernVocabularySuggestController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.concerns.vocabulary-suggest');

        Route::get('/app/repair-orders/{repairOrder}/labor-memory-suggest', RepairOrderLaborMemorySuggestController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.labor-memory-suggest');

        Route::get('/app/repair-orders/{repairOrder}/concern-memory-suggest', RepairOrderConcernMemorySuggestController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.concern-memory-suggest');

        Route::post('/app/repair-orders/{repairOrder}/ai-rewrite', RepairOrderAiRewriteController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.ai-rewrite');

        Route::post('/app/shop-memory/suggestion-events', ShopMemorySuggestionEventStoreController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.shop-memory.suggestion-events.store');

        Route::post('/app/repair-orders/{repairOrder}/concerns', RepairOrderConcernStoreController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.concerns.store');

        Route::patch('/app/repair-orders/{repairOrder}/concerns/{concern}/move', RepairOrderConcernMoveController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.concerns.move');

        Route::post('/app/repair-orders/{repairOrder}/concerns/{concern}/move-to-new-ro', RepairOrderConcernMoveToNewRepairOrderController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.concerns.move-to-new-ro');

        Route::patch('/app/repair-orders/{repairOrder}/concerns/{concern}/disposition', RepairOrderConcernDispositionController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.concerns.disposition');

        Route::patch('/app/repair-orders/{repairOrder}/concerns/{concern}/production-status', RepairOrderConcernProductionStatusController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value.'|'.ArkCapability::ProductionAccess->value)
            ->name('operations.repair-orders.concerns.production-status');

        Route::patch('/app/repair-orders/{repairOrder}/concerns/{concern}/billing-posture', RepairOrderConcernBillingPostureController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.concerns.billing-posture');

        Route::patch('/app/repair-orders/{repairOrder}/concerns/{concern}/recommendation-intent', RepairOrderConcernRecommendationIntentController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.concerns.recommendation-intent');

        Route::post('/app/repair-orders/{repairOrder}/authorization', RepairOrderAuthorizationStoreController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.authorization.store');

        Route::post('/app/repair-orders/{repairOrder}/authorization/{approvalEvent}/revoke', RepairOrderAuthorizationRevokeController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.authorization.revoke');

        Route::patch('/app/repair-orders/{repairOrder}/concerns/{concern}', RepairOrderConcernUpdateController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.concerns.update');

        Route::delete('/app/repair-orders/{repairOrder}/concerns/{concern}', RepairOrderConcernDestroyController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersDestructive->value)
            ->name('operations.repair-orders.concerns.destroy');

        Route::post('/app/repair-orders/{repairOrder}/concerns/{concern}/work-groups', RepairOrderWorkGroupStoreController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.concerns.work-groups.store');

        Route::delete('/app/repair-orders/{repairOrder}/work-groups/{workGroup}', RepairOrderWorkGroupDestroyController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersDestructive->value)
            ->name('operations.repair-orders.work-groups.destroy');

        Route::patch('/app/repair-orders/{repairOrder}/work-groups/{workGroup}/owner', RepairOrderWorkGroupOwnerController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.work-groups.owner.update');

        Route::patch('/app/repair-orders/{repairOrder}/work-groups/{workGroup}/communication', RepairOrderWorkGroupCommunicationController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.work-groups.communication.update');

        Route::post('/app/repair-orders/{repairOrder}/lines', RepairOrderLineStoreController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.lines.store');

        Route::post('/app/repair-orders/{repairOrder}/lines/pricing-preview', RepairOrderLinePricingPreviewController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.lines.pricing-preview');

        Route::post('/app/repair-orders/{repairOrder}/dealer-quotes/analyze', [RepairOrderDealerQuoteCaptureController::class, 'analyze'])
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.dealer-quotes.analyze');
        Route::post('/app/repair-orders/{repairOrder}/dealer-quotes', [RepairOrderDealerQuoteCaptureController::class, 'store'])
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.dealer-quotes.store');
        Route::get('/app/repair-orders/{repairOrder}/dealer-quotes/{dealerQuote}', DealerQuoteShowController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.dealer-quotes.show');
        Route::get('/app/repair-orders/{repairOrder}/dealer-quotes/{dealerQuote}/download', [DealerQuoteShowController::class, 'download'])
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.dealer-quotes.download');

        Route::get('/app/repair-orders/{repairOrder}/labor-guides/{provider}', [RepairOrderLaborGuideController::class, 'redirect'])
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->where('provider', 'alldata|prodemand')
            ->name('operations.repair-orders.labor-guides.redirect');

        Route::get('/app/repair-orders/{repairOrder}/rte-labor/search', RepairOrderRteLaborSearchController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.rte-labor.search');

        Route::post('/app/repair-orders/{repairOrder}/rte-labor/apply', RepairOrderRteLaborApplyController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.rte-labor.apply');

        Route::patch('/app/repair-orders/{repairOrder}/lines/{line}', RepairOrderLineUpdateController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.lines.update');

        Route::patch('/app/repair-orders/{repairOrder}/lines/{line}/procurement', RepairOrderLineProcurementController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersManage->value)
            ->name('operations.repair-orders.lines.procurement.update');

        Route::delete('/app/repair-orders/{repairOrder}/lines/{line}', RepairOrderLineDestroyController::class)
            ->middleware('permission:'.ArkCapability::RepairOrdersDestructive->value)
            ->name('operations.repair-orders.lines.destroy');
    });

    require __DIR__.'/auth.php';
});
