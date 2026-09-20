<?php

use App\Ark\Desk\Http\DeskCallHandleController;
use App\Ark\Desk\Http\DeskCallShowController;
use App\Ark\Desk\Http\DeskDragonChatController;
use App\Ark\Desk\Http\DeskLoginController;
use App\Ark\Desk\Http\DeskLogoutController;
use App\Ark\Desk\Http\DeskMeController;
use App\Ark\Desk\Http\DeskTaskCompleteController;
use App\Ark\Desk\Http\DeskTaskStoreController;
use App\Ark\Desk\Http\DeskWorkController;
use App\Ark\Desk\Http\DeskWorkstationStoreController;
use App\Ark\Dragon\Agent\Http\ChatDragonAgentController;
use App\Ark\Mobile\Http\MobileAdvisorBriefSuggestionFeedbackController;
use App\Ark\Mobile\Http\MobileAppointmentStatusController;
use App\Ark\Mobile\Http\MobileAppointmentStoreController;
use App\Ark\Mobile\Http\MobileAttentionIndexController;
use App\Ark\Mobile\Http\MobileAuthLoginController;
use App\Ark\Mobile\Http\MobileAuthLogoutController;
use App\Ark\Mobile\Http\MobileCallMarkHandledController;
use App\Ark\Mobile\Http\MobileCallRecordingPlaybackController;
use App\Ark\Mobile\Http\MobileCallsIndexController;
use App\Ark\Mobile\Http\MobileCommsHubController;
use App\Ark\Mobile\Http\MobileConcernDestroyController;
use App\Ark\Mobile\Http\MobileConcernDispositionController;
use App\Ark\Mobile\Http\MobileConcernNoteStoreController;
use App\Ark\Mobile\Http\MobileConcernProductionStatusController;
use App\Ark\Mobile\Http\MobileConcernShowController;
use App\Ark\Mobile\Http\MobileConcernStoreController;
use App\Ark\Mobile\Http\MobileConcernUpdateController;
use App\Ark\Mobile\Http\MobileContinuityBadgeController;
use App\Ark\Mobile\Http\MobileContinuityController;
use App\Ark\Mobile\Http\MobileConversationAttachmentShowController;
use App\Ark\Mobile\Http\MobileConversationInternalNoteStoreController;
use App\Ark\Mobile\Http\MobileConversationMarkReadController;
use App\Ark\Mobile\Http\MobileConversationMessageStoreController;
use App\Ark\Mobile\Http\MobileConversationsIndexController;
use App\Ark\Mobile\Http\MobileConversationsThreadController;
use App\Ark\Mobile\Http\MobileCustomerMessageStoreController;
use App\Ark\Mobile\Http\MobileCustomerWorkspaceController;
use App\Ark\Mobile\Http\MobileDeviceRegisterController;
use App\Ark\Mobile\Http\MobileEvidenceShowController;
use App\Ark\Mobile\Http\MobileEvidenceStoreController;
use App\Ark\Mobile\Http\MobileFindingShowController;
use App\Ark\Mobile\Http\MobileFindingStoreController;
use App\Ark\Mobile\Http\MobileGlobalSearchController;
use App\Ark\Mobile\Http\MobileIncomingCallContextController;
use App\Ark\Mobile\Http\MobileInspectionChecklistItemShowController;
use App\Ark\Mobile\Http\MobileInspectionChecklistItemUpdateController;
use App\Ark\Mobile\Http\MobileInspectionChecklistShowController;
use App\Ark\Mobile\Http\MobileInspectionPhotoShowController;
use App\Ark\Mobile\Http\MobileIntakeCustomerSearchController;
use App\Ark\Mobile\Http\MobileIntakeCustomerShowController;
use App\Ark\Mobile\Http\MobileIntakeCustomerStoreController;
use App\Ark\Mobile\Http\MobileIntakeStoreController;
use App\Ark\Mobile\Http\MobileIntakeTechniciansIndexController;
use App\Ark\Mobile\Http\MobileIntakeVehicleLookupController;
use App\Ark\Mobile\Http\MobileIntakeVehicleStoreController;
use App\Ark\Mobile\Http\MobileMeController;
use App\Ark\Mobile\Http\MobileNotificationsIndexController;
use App\Ark\Mobile\Http\MobileOrientationController;
use App\Ark\Mobile\Http\MobileOwnerBookendController;
use App\Ark\Mobile\Http\MobileOwnerOperationalReportController;
use App\Ark\Mobile\Http\MobileRepairOrderDepositStoreController;
use App\Ark\Mobile\Http\MobileRepairOrderInspectionPortalPreviewController;
use App\Ark\Mobile\Http\MobileRepairOrderLedgerVoidController;
use App\Ark\Mobile\Http\MobileRepairOrderLifecycleController;
use App\Ark\Mobile\Http\MobileRepairOrderLineDestroyController;
use App\Ark\Mobile\Http\MobileRepairOrderLineStoreController;
use App\Ark\Mobile\Http\MobileRepairOrderLineUpdateController;
use App\Ark\Mobile\Http\MobileRepairOrderPaymentCaptureCancelController;
use App\Ark\Mobile\Http\MobileRepairOrderPaymentCaptureRefreshController;
use App\Ark\Mobile\Http\MobileRepairOrderPaymentCaptureStoreController;
use App\Ark\Mobile\Http\MobileRepairOrderPaymentStoreController;
use App\Ark\Mobile\Http\MobileRepairOrderRefundStoreController;
use App\Ark\Mobile\Http\MobileRepairOrderShowController;
use App\Ark\Mobile\Http\MobileRepairOrderTechnicianAssignmentController;
use App\Ark\Mobile\Http\MobileScheduleIndexController;
use App\Ark\Mobile\Http\MobileTelephonyCallbackController;
use App\Ark\Mobile\Http\MobileTelephonyPeopleController;
use App\Ark\Mobile\Http\MobileTelephonyVoiceAnswerController;
use App\Ark\Mobile\Http\MobileTelephonyVoiceConnectController;
use App\Ark\Mobile\Http\MobileTelephonyVoiceRegistrationEventController;
use App\Ark\Mobile\Http\MobileTelephonyVoiceSessionController;
use App\Ark\Mobile\Http\MobileVehicleWorkspaceController;
use App\Ark\Mobile\Http\MobileVinDecodeController;
use App\Ark\Mobile\Http\MobileWorkController;
use App\Ark\Operations\Messaging\SendEstimateLinkController;
use App\Ark\Operations\Messaging\SendInspectionLinkController;
use App\Ark\Operations\Messaging\SendPaymentLinkController;
use App\Ark\Station\Http\AuthenticateStationDevice;
use App\Ark\Station\Http\StationAttentionNudgeController;
use App\Ark\Station\Http\StationDashboardController;
use App\Ark\Station\Http\StationDragonChatController;
use App\Ark\Station\Http\StationGlassCallHandleController;
use App\Ark\Station\Http\StationGlassCallShowController;
use App\Ark\Station\Http\StationGlassSettingsController;
use App\Ark\Station\Http\StationGlassTaskCompleteController;
use App\Ark\Station\Http\StationGlassTaskStoreController;
use App\Ark\Station\Http\StationMeController;
use App\Ark\Station\Http\StationRepairOrderController;
use App\Ark\Tech\Http\EnsureTechStaff;
use App\Ark\Tech\Http\TechDragonRewriteController;
use App\Ark\Tech\Http\TechInspectionItemUpdateController;
use App\Ark\Tech\Http\TechInspectionShowController;
use App\Ark\Tech\Http\TechLoginController;
use App\Ark\Tech\Http\TechMyWorkController;
use App\Ark\Tech\Http\TechRepairOrderShowController;
use App\Ark\Tech\Http\TechVoiceConfirmController;
use App\Ark\Tech\Http\TechVoiceUtteranceController;
use App\Ark\Voice\Lab\VoiceLabController;
use App\Http\Middleware\EnsureApiStaffActive;
use Illuminate\Support\Facades\Route;

Route::prefix('desk')->name('api.desk.')->group(function (): void {
    Route::post('/auth/login', DeskLoginController::class)->name('auth.login');

    Route::middleware(['auth:sanctum', EnsureApiStaffActive::class])->group(function (): void {
        Route::post('/auth/logout', DeskLogoutController::class)->name('auth.logout');
        Route::get('/me', DeskMeController::class)->name('me');
        Route::get('/work', DeskWorkController::class)->name('work');
        Route::post('/workstation', DeskWorkstationStoreController::class)->name('workstation');
        Route::get('/calls/{call}', DeskCallShowController::class)->name('calls.show');
        Route::post('/calls/{call}/handled', DeskCallHandleController::class)->name('calls.handled');
        Route::post('/tasks', DeskTaskStoreController::class)->name('tasks.store');
        Route::post('/tasks/{task}/complete', DeskTaskCompleteController::class)->name('tasks.complete');
        Route::post('/dragon/chat', DeskDragonChatController::class)->name('dragon.chat');
    });
});

Route::prefix('dragon-agent')->name('api.dragon-agent.')->middleware(['auth:sanctum', EnsureApiStaffActive::class])->group(function (): void {
    Route::post('/chat', ChatDragonAgentController::class)->name('chat');
});

Route::prefix('station')->name('api.station.')->middleware(AuthenticateStationDevice::class)->group(function (): void {
    Route::get('/me', StationMeController::class)->name('me');
    Route::get('/dashboard', StationDashboardController::class)->name('dashboard');
    Route::match(['GET', 'POST'], '/attention-nudge', StationAttentionNudgeController::class)->name('attention-nudge');
    Route::post('/dragon/chat', StationDragonChatController::class)->name('dragon.chat');
    Route::get('/repair-orders/{repairOrder}', StationRepairOrderController::class)->name('repair-orders.show');
    Route::get('/calls/{call}', StationGlassCallShowController::class)->name('calls.show');
    Route::post('/calls/{call}/handled', StationGlassCallHandleController::class)->name('calls.handled');
    Route::post('/settings', StationGlassSettingsController::class)->name('settings');
    Route::post('/tasks', StationGlassTaskStoreController::class)->name('tasks.store');
    Route::post('/tasks/{task}/complete', StationGlassTaskCompleteController::class)->name('tasks.complete');
});

Route::prefix('mobile')->name('api.mobile.')->group(function (): void {
    Route::post('/auth/login', MobileAuthLoginController::class)->name('auth.login');

    Route::middleware(['auth:sanctum', EnsureApiStaffActive::class])->group(function (): void {
        Route::post('/auth/logout', MobileAuthLogoutController::class)->name('auth.logout');
        Route::post('/device', MobileDeviceRegisterController::class)->name('device.register');
        Route::get('/me', MobileMeController::class)->name('me');
        Route::get('/orientation', MobileOrientationController::class)->name('orientation');
        Route::get('/continuity', MobileContinuityController::class)->name('continuity');
        Route::get('/continuity/badge', MobileContinuityBadgeController::class)->name('continuity.badge');
        Route::get('/incoming-call/context', MobileIncomingCallContextController::class)->name('incoming-call.context');
        Route::get('/calls', MobileCallsIndexController::class)->name('calls.index');
        Route::post('/calls/{callSession}/mark-handled', MobileCallMarkHandledController::class)->name('calls.mark-handled');
        Route::get('/work', MobileWorkController::class)->name('work');
        Route::post('/telephony/callback', MobileTelephonyCallbackController::class)
            ->name('telephony.callback');
        Route::post('/telephony/voice-session', MobileTelephonyVoiceSessionController::class)
            ->name('telephony.voice-session');
        Route::post('/telephony/voice-registration-event', MobileTelephonyVoiceRegistrationEventController::class)
            ->name('telephony.voice-registration-event');
        Route::post('/telephony/voice-connect', MobileTelephonyVoiceConnectController::class)
            ->name('telephony.voice-connect');
        Route::post('/telephony/voice-answer', MobileTelephonyVoiceAnswerController::class)
            ->name('telephony.voice-answer');
        Route::get('/telephony/people', MobileTelephonyPeopleController::class)
            ->name('telephony.people');
        Route::get('/repair-orders/{repairOrder}', MobileRepairOrderShowController::class)->name('repair-orders.show');
        Route::post('/repair-orders/{repairOrder}/send-estimate', SendEstimateLinkController::class)
            ->name('repair-orders.send-estimate');
        Route::post('/repair-orders/{repairOrder}/send-payment', SendPaymentLinkController::class)
            ->name('repair-orders.send-payment');
        Route::post('/repair-orders/{repairOrder}/send-inspection', SendInspectionLinkController::class)
            ->name('repair-orders.send-inspection');
        Route::get('/repair-orders/{repairOrder}/inspection-portal-preview', MobileRepairOrderInspectionPortalPreviewController::class)
            ->name('repair-orders.inspection-portal-preview');
        Route::patch('/repair-orders/{repairOrder}/payment', MobileRepairOrderPaymentStoreController::class)
            ->name('repair-orders.payment.store');
        Route::post('/repair-orders/{repairOrder}/payment-capture', MobileRepairOrderPaymentCaptureStoreController::class)
            ->name('repair-orders.payment-capture.store');
        Route::post('/repair-orders/{repairOrder}/payment-capture/{attempt}/refresh', MobileRepairOrderPaymentCaptureRefreshController::class)
            ->name('repair-orders.payment-capture.refresh');
        Route::delete('/repair-orders/{repairOrder}/payment-capture/{attempt}', MobileRepairOrderPaymentCaptureCancelController::class)
            ->name('repair-orders.payment-capture.destroy');
        Route::patch('/repair-orders/{repairOrder}/deposit', MobileRepairOrderDepositStoreController::class)
            ->name('repair-orders.deposit.store');
        Route::patch('/repair-orders/{repairOrder}/refund', MobileRepairOrderRefundStoreController::class)
            ->name('repair-orders.refund.store');
        Route::delete('/repair-orders/{repairOrder}/ledger-entries/{entry}', MobileRepairOrderLedgerVoidController::class)
            ->name('repair-orders.ledger-entries.destroy');
        Route::patch('/repair-orders/{repairOrder}/technician-assignment', MobileRepairOrderTechnicianAssignmentController::class)
            ->name('repair-orders.technician-assignment');
        Route::patch('/repair-orders/{repairOrder}/status', MobileRepairOrderLifecycleController::class)
            ->name('repair-orders.lifecycle');
        Route::post('/repair-orders/{repairOrder}/concerns', MobileConcernStoreController::class)
            ->name('repair-orders.concerns.store');
        Route::get('/repair-orders/{repairOrder}/concerns/{concern}', MobileConcernShowController::class)
            ->name('repair-orders.concerns.show');
        Route::patch('/repair-orders/{repairOrder}/concerns/{concern}', MobileConcernUpdateController::class)
            ->name('repair-orders.concerns.update');
        Route::post('/repair-orders/{repairOrder}/concerns/{concern}/notes', MobileConcernNoteStoreController::class)
            ->name('repair-orders.concerns.notes.store');
        Route::patch('/repair-orders/{repairOrder}/concerns/{concern}/production-status', MobileConcernProductionStatusController::class)
            ->name('repair-orders.concerns.production-status');
        Route::patch('/repair-orders/{repairOrder}/concerns/{concern}/disposition', MobileConcernDispositionController::class)
            ->name('repair-orders.concerns.disposition');
        Route::delete('/repair-orders/{repairOrder}/concerns/{concern}', MobileConcernDestroyController::class)
            ->name('repair-orders.concerns.destroy');
        Route::post('/repair-orders/{repairOrder}/lines', MobileRepairOrderLineStoreController::class)
            ->name('repair-orders.lines.store');
        Route::patch('/repair-orders/{repairOrder}/lines/{line}', MobileRepairOrderLineUpdateController::class)
            ->name('repair-orders.lines.update');
        Route::delete('/repair-orders/{repairOrder}/lines/{line}', MobileRepairOrderLineDestroyController::class)
            ->name('repair-orders.lines.destroy');
        Route::get('/repair-orders/{repairOrder}/findings/{finding}', MobileFindingShowController::class)
            ->name('repair-orders.findings.show');
        Route::post('/repair-orders/{repairOrder}/findings', MobileFindingStoreController::class)->name('repair-orders.findings.store');
        Route::get('/repair-orders/{repairOrder}/inspection-checklist', MobileInspectionChecklistShowController::class)
            ->name('repair-orders.inspection-checklist.show');
        Route::get('/repair-orders/{repairOrder}/inspection-checklist/items/{item}', MobileInspectionChecklistItemShowController::class)
            ->name('repair-orders.inspection-checklist.items.show');
        Route::patch('/repair-orders/{repairOrder}/inspection-checklist/items/{item}', MobileInspectionChecklistItemUpdateController::class)
            ->name('repair-orders.inspection-checklist.items.update');
        Route::get('/repair-orders/{repairOrder}/inspection-photos/{photo}', MobileInspectionPhotoShowController::class)
            ->name('repair-orders.inspection-photos.show');
        Route::post('/repair-orders/{repairOrder}/evidence', MobileEvidenceStoreController::class)
            ->name('repair-orders.evidence.store');
        Route::get('/repair-orders/{repairOrder}/evidence/{evidence}', MobileEvidenceShowController::class)
            ->name('repair-orders.evidence.show');
        Route::get('/comms/hub', MobileCommsHubController::class)->name('comms.hub');
        Route::get('/conversations', MobileConversationsIndexController::class)->name('conversations.index');
        Route::get('/conversations/{conversation}', MobileConversationsThreadController::class)->name('conversations.show');
        Route::get('/communications', MobileConversationsIndexController::class)->name('communications.index');
        Route::get('/communications/{conversation}', MobileConversationsThreadController::class)->name('communications.show');
        Route::post('/conversations/{conversation}/messages', MobileConversationMessageStoreController::class)
            ->name('conversations.messages.store');
        Route::post('/conversations/{conversation}/internal-notes', MobileConversationInternalNoteStoreController::class)
            ->name('conversations.internal-notes.store');
        Route::post('/conversations/{conversation}/read', MobileConversationMarkReadController::class)
            ->name('conversations.read');
        Route::post('/conversations/{conversation}/advisor-brief/suggestion-feedback', MobileAdvisorBriefSuggestionFeedbackController::class)
            ->name('conversations.advisor-brief.suggestion-feedback');
        Route::get('/conversations/{conversation}/messages/{message}/attachments/{attachment}', MobileConversationAttachmentShowController::class)
            ->name('conversations.attachments.show');
        Route::post('/communications/{conversation}/messages', MobileConversationMessageStoreController::class)
            ->name('communications.messages.store');
        Route::post('/communications/{conversation}/internal-notes', MobileConversationInternalNoteStoreController::class)
            ->name('communications.internal-notes.store');
        Route::get('/calls/{callSession}/recording', MobileCallRecordingPlaybackController::class)
            ->name('telephony.call-sessions.recording');
        Route::get('/notifications', MobileNotificationsIndexController::class)->name('notifications.index');
        Route::get('/attention', MobileAttentionIndexController::class)->name('attention.index');
        Route::get('/search', MobileGlobalSearchController::class)->name('search');
        Route::get('/schedule', MobileScheduleIndexController::class)->name('schedule.index');
        Route::post('/appointments', MobileAppointmentStoreController::class)->name('appointments.store');
        Route::patch('/appointments/{appointment}/status', MobileAppointmentStatusController::class)
            ->name('appointments.status');
        Route::get('/owner/bookend', MobileOwnerBookendController::class)->name('owner.bookend');
        Route::get('/owner/operational-report', MobileOwnerOperationalReportController::class)
            ->name('owner.operational-report');
        Route::post('/tools/vin-decode', MobileVinDecodeController::class)->name('tools.vin-decode');
        Route::get('/customers/{customer}', MobileCustomerWorkspaceController::class)->name('customers.show');
        Route::post('/customers/{customer}/messages', MobileCustomerMessageStoreController::class)
            ->name('customers.messages.store');
        Route::get('/vehicles/{vehicle}', MobileVehicleWorkspaceController::class)->name('vehicles.show');

        Route::prefix('intake')->name('intake.')->group(function (): void {
            Route::get('/customers/search', MobileIntakeCustomerSearchController::class)->name('customers.search');
            Route::post('/customers', MobileIntakeCustomerStoreController::class)->name('customers.store');
            Route::get('/customers/{customer}', MobileIntakeCustomerShowController::class)->name('customers.show');
            Route::get('/vehicles/lookup', MobileIntakeVehicleLookupController::class)->name('vehicles.lookup');
            Route::get('/technicians', MobileIntakeTechniciansIndexController::class)->name('technicians.index');
            Route::post('/customers/{customer}/vehicles', MobileIntakeVehicleStoreController::class)->name('vehicles.store');
            Route::post('/', MobileIntakeStoreController::class)->name('store');
        });
    });
});

Route::post('/voice/lab/utterance', VoiceLabController::class)
    ->middleware('throttle:20,1')
    ->name('api.voice.lab.utterance');

Route::prefix('tech')->name('api.tech.')->group(function (): void {
    Route::post('/auth/login', TechLoginController::class)->name('auth.login');

    Route::middleware(['auth:sanctum', EnsureApiStaffActive::class, EnsureTechStaff::class])->group(function (): void {
        Route::get('/me/work', TechMyWorkController::class)->name('me.work');
        Route::get('/repair-orders/{repairOrder}', TechRepairOrderShowController::class)->name('repair-orders.show');
        Route::get('/repair-orders/{repairOrder}/inspection', TechInspectionShowController::class)->name('inspection.show');
        Route::patch('/repair-orders/{repairOrder}/inspection-items/{item}', TechInspectionItemUpdateController::class)
            ->name('inspection-items.update');
        Route::post('/repair-orders/{repairOrder}/inspection-items/{item}/voice', TechVoiceUtteranceController::class)
            ->name('inspection-items.voice');
        Route::post('/repair-orders/{repairOrder}/inspection-items/{item}/voice/confirm', TechVoiceConfirmController::class)
            ->name('inspection-items.voice.confirm');
        Route::post('/dragon/rewrite', TechDragonRewriteController::class)->name('dragon.rewrite');
    });
});
