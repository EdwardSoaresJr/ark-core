<?php

use App\Ark\Import\ShopCsv\ShopCsvImportController;
use App\Ark\Operations\Appointments\RemoveScheduleBayController;
use App\Ark\Operations\Appointments\StoreScheduleBayController;
use App\Ark\Operations\Appointments\UpdateScheduleBayController;
use App\Ark\Operations\Inspections\InspectionTemplateSettingsController;
use App\Ark\Operations\Payments\SquareTerminalDeviceCodeController;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusCatalogSettingsController;
use App\Ark\Operations\Settings\DragonMemorySettingsController;
use App\Ark\Operations\Settings\LaborPolicySettingsController;
use App\Ark\Operations\Settings\ShopCommunicationsSettingsController;
use App\Ark\Operations\Settings\ShopFinancialSettingsController;
use App\Ark\Operations\Settings\ShopGeneralSettingsController;
use App\Ark\Operations\Settings\ShopIntegrationSettingsController;
use App\Ark\Operations\Settings\ShopOperationsSettingsController;
use App\Ark\Operations\Settings\ShopSettingsPageController;
use App\Ark\Operations\Staff\StaffMemberController;
use App\Ark\Operations\Telephony\SimulateIncomingCallController;
use App\Ark\Runtime\Authorization\ArkCapability;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:'.ArkCapability::SettingsManage->value)->group(function (): void {
    Route::get('/app/settings/shop', [ShopSettingsPageController::class, 'edit'])
        ->name('operations.settings.shop.edit');

    Route::middleware('permission:'.ArkCapability::StaffManage->value)->group(function (): void {
        Route::post('/app/settings/staff', [StaffMemberController::class, 'store'])
            ->name('operations.settings.staff.store');

        Route::patch('/app/settings/staff/{user}', [StaffMemberController::class, 'update'])
            ->name('operations.settings.staff.update');

        Route::patch('/app/settings/staff/{user}/deactivate', [StaffMemberController::class, 'deactivate'])
            ->name('operations.settings.staff.deactivate');

        Route::patch('/app/settings/staff/{user}/activate', [StaffMemberController::class, 'activate'])
            ->name('operations.settings.staff.activate');

        Route::post('/app/settings/staff/{user}/resend-invitation', [StaffMemberController::class, 'resendInvitation'])
            ->name('operations.settings.staff.resend-invitation');
    });

    Route::patch('/app/settings/shop/general', [ShopGeneralSettingsController::class, 'updateGeneral'])
        ->name('operations.settings.shop.general.update');

    Route::patch('/app/settings/shop/labor', [ShopFinancialSettingsController::class, 'updateLabor'])
        ->name('operations.settings.shop.labor.update');

    Route::patch('/app/settings/shop/labor-policies', [LaborPolicySettingsController::class, 'update'])
        ->name('operations.settings.shop.labor-policies.update');

    Route::patch('/app/settings/shop/tax', [ShopFinancialSettingsController::class, 'updateTax'])
        ->name('operations.settings.shop.tax.update');

    Route::patch('/app/settings/shop/fees', [ShopFinancialSettingsController::class, 'updateFees'])
        ->name('operations.settings.shop.fees.update');

    Route::patch('/app/settings/shop/deposits', [ShopFinancialSettingsController::class, 'updateDeposits'])
        ->name('operations.settings.shop.deposits.update');

    Route::patch('/app/settings/shop/customer-types', [ShopFinancialSettingsController::class, 'updateCustomerTypes'])
        ->name('operations.settings.shop.customer-types.update');

    Route::patch('/app/settings/shop/estimates', [ShopOperationsSettingsController::class, 'updateEstimates'])
        ->name('operations.settings.shop.estimates.update');

    Route::patch('/app/settings/shop/payments', [ShopIntegrationSettingsController::class, 'updatePayments'])
        ->name('operations.settings.shop.payments.update');

    Route::post('/app/settings/shop/payments/square-terminal-device-code', [SquareTerminalDeviceCodeController::class, 'store'])
        ->name('operations.settings.shop.payments.square-terminal-device-code.store');

    Route::get('/app/settings/shop/payments/square-terminal-device-code/{deviceCodeId}', [SquareTerminalDeviceCodeController::class, 'show'])
        ->name('operations.settings.shop.payments.square-terminal-device-code.show');

    Route::patch('/app/settings/shop/email', [ShopIntegrationSettingsController::class, 'updateEmail'])
        ->name('operations.settings.shop.email.update');

    Route::post('/app/settings/shop/email/ark-mail/enable', [ShopIntegrationSettingsController::class, 'enableArkMail'])
        ->name('operations.settings.shop.email.ark-mail.enable');

    Route::post('/app/settings/shop/email/ark-mail/claim', [ShopIntegrationSettingsController::class, 'claimArkMail'])
        ->name('operations.settings.shop.email.ark-mail.claim');

    Route::post('/app/settings/shop/email/ark-mail/disconnect', [ShopIntegrationSettingsController::class, 'disconnectArkMail'])
        ->name('operations.settings.shop.email.ark-mail.disconnect');

    Route::patch('/app/settings/shop/telephony', [ShopCommunicationsSettingsController::class, 'updateTelephony'])
        ->name('operations.settings.shop.telephony.update');

    Route::post('/app/settings/telephony/test-incoming-call', SimulateIncomingCallController::class)
        ->name('operations.settings.telephony.test-incoming-call');

    Route::patch('/app/settings/shop/workflow', [ShopOperationsSettingsController::class, 'updateWorkflow'])
        ->name('operations.settings.shop.workflow.update');

    Route::patch('/app/settings/shop/appointments', [ShopOperationsSettingsController::class, 'updateAppointments'])
        ->name('operations.settings.shop.appointments.update');

    Route::post('/app/settings/shop/appointments/bays', StoreScheduleBayController::class)
        ->name('operations.settings.shop.appointments.bays.store');

    Route::patch('/app/settings/shop/appointments/bays/{workstation}', UpdateScheduleBayController::class)
        ->name('operations.settings.shop.appointments.bays.update');

    Route::delete('/app/settings/shop/appointments/bays/{workstation}', RemoveScheduleBayController::class)
        ->name('operations.settings.shop.appointments.bays.destroy');

    Route::get('/app/settings/shop/import/template', [ShopCsvImportController::class, 'template'])
        ->name('operations.settings.shop.import.template');

    Route::post('/app/settings/shop/import/preview', [ShopCsvImportController::class, 'preview'])
        ->name('operations.settings.shop.import.preview');

    Route::post('/app/settings/shop/import', [ShopCsvImportController::class, 'commit'])
        ->name('operations.settings.shop.import.commit');

    Route::post('/app/settings/shop/operational-profile', [ShopOperationsSettingsController::class, 'applyOperationalProfile'])
        ->name('operations.settings.shop.operational-profile.apply');

    Route::patch('/app/settings/shop/status-catalog', RepairOrderStatusCatalogSettingsController::class)
        ->name('operations.settings.shop.status-catalog.update');

    Route::patch('/app/settings/shop/inspection-templates', InspectionTemplateSettingsController::class)
        ->name('operations.settings.shop.inspection-templates.update');

    Route::post('/app/settings/shop/work-templates', [\App\Ark\Operations\WorkTemplates\WorkTemplateSettingsController::class, 'store'])
        ->name('operations.settings.shop.work-templates.store');
    Route::put('/app/settings/shop/work-templates/{workTemplate}', [\App\Ark\Operations\WorkTemplates\WorkTemplateSettingsController::class, 'update'])
        ->name('operations.settings.shop.work-templates.update');
    Route::post('/app/settings/shop/work-templates/{workTemplate}/duplicate', [\App\Ark\Operations\WorkTemplates\WorkTemplateSettingsController::class, 'duplicate'])
        ->name('operations.settings.shop.work-templates.duplicate');
    Route::patch('/app/settings/shop/work-templates/{workTemplate}/retire', [\App\Ark\Operations\WorkTemplates\WorkTemplateSettingsController::class, 'retire'])
        ->name('operations.settings.shop.work-templates.retire');
    Route::patch('/app/settings/shop/work-templates/{workTemplate}/restore', [\App\Ark\Operations\WorkTemplates\WorkTemplateSettingsController::class, 'restore'])
        ->name('operations.settings.shop.work-templates.restore');

    Route::patch('/app/settings/shop/excellence', [ShopOperationsSettingsController::class, 'updateExcellence'])
        ->name('operations.settings.shop.excellence.update');

    Route::patch('/app/settings/shop/printing', [ShopOperationsSettingsController::class, 'updatePrinting'])
        ->name('operations.settings.shop.printing.update');

    Route::patch('/app/settings/shop/overhead', [ShopOperationsSettingsController::class, 'updateOverhead'])
        ->name('operations.settings.shop.overhead.update');

    Route::patch('/app/settings/shop/parts-matrices/{matrixKey}', [ShopFinancialSettingsController::class, 'updatePartsMatrix'])
        ->name('operations.settings.shop.parts-matrices.update');

    Route::delete('/app/settings/shop/parts-matrices/{matrixKey}', [ShopFinancialSettingsController::class, 'destroyPartsMatrix'])
        ->name('operations.settings.shop.parts-matrices.destroy');

    Route::patch('/app/settings/shop/dragon-memory/{memory}', [DragonMemorySettingsController::class, 'update'])
        ->name('operations.settings.shop.dragon-memory.update');

    Route::post('/app/settings/shop/dragon-memory/{memory}/forget', [DragonMemorySettingsController::class, 'forget'])
        ->name('operations.settings.shop.dragon-memory.forget');
});
