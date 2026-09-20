<?php

use App\Ark\Operations\Payments\PortalEstimateDepositCompleteController;
use App\Ark\Operations\Payments\PortalEstimateDepositInitiateController;
use App\Ark\Operations\Payments\PortalInvoicePayCompleteController;
use App\Ark\Operations\Payments\PortalInvoicePayInitiateController;
use App\Ark\Operations\Payments\PortalInvoicePayShowController;
use App\Ark\Operations\Portal\PortalAccessChallengeStoreController;
use App\Ark\Operations\Portal\PortalAccessShowController;
use App\Ark\Operations\Portal\PortalAccessVerifyController;
use App\Ark\Operations\Portal\PortalAccessVerifyShowController;
use App\Ark\Operations\Evidence\PortalEvidenceShowController;
use App\Ark\Operations\Portal\RepairPortalEvidenceShowController;
use App\Ark\Operations\Portal\RepairPortalInspectionController;
use App\Ark\Operations\Portal\RepairPortalShowController;
use App\Ark\Operations\Portal\PortalEstimateAuthorizeController;
use App\Ark\Operations\Portal\PortalEstimatePdfController;
use App\Ark\Operations\Portal\PortalEstimateShowController;
use App\Ark\Operations\Portal\PortalInspectionPdfController;
use App\Ark\Operations\Portal\PortalInspectionPhotoShowController;
use App\Ark\Operations\Portal\PortalInspectionPrintController;
use App\Ark\Operations\Portal\PortalInspectionShowController;
use App\Ark\Operations\Portal\PortalHomeController;
use App\Ark\Operations\Portal\PortalLogoutController;
use App\Ark\Operations\Portal\PortalShortLinkRedirectController;
use App\Ark\Operations\Portal\PortalVehicleDocumentController;
use App\Ark\Operations\Portal\PortalVehicleInspectionController;
use App\Ark\Operations\Portal\PortalVehicleShowController;
use App\Ark\Runtime\Surfaces\SurfaceRouting;
use Illuminate\Support\Facades\Route;

SurfaceRouting::portalRoutes(function (): void {
    Route::middleware(['web', 'portal.noindex'])->group(function (): void {
        if (SurfaceRouting::enabled() && ! SurfaceRouting::portalOnPublicHost()) {
            Route::get('/', function () {
                if (auth('portal')->check()) {
                    return redirect()->route('portal.home');
                }

                return redirect()->route('portal.access');
            });
        }

        Route::get('/portal', function () {
            if (auth('portal')->check()) {
                return redirect()->route('portal.home');
            }

            return view('portal.index');
        })->name('portal.index');

        Route::get('/portal/access', PortalAccessShowController::class)->name('portal.access');
        Route::get('/access', PortalAccessShowController::class);

        Route::post('/portal/access/challenges', PortalAccessChallengeStoreController::class)
            ->middleware('throttle:6,1')
            ->name('portal.access.challenges.store');
        Route::post('/access/challenges', PortalAccessChallengeStoreController::class)
            ->middleware('throttle:6,1');

        Route::get('/portal/access/verify', PortalAccessVerifyShowController::class)->name('portal.access.verify');
        Route::get('/access/verify', PortalAccessVerifyShowController::class);

        Route::post('/portal/access/verify', PortalAccessVerifyController::class)
            ->middleware('throttle:12,1')
            ->name('portal.access.verify.store');
        Route::post('/access/verify', PortalAccessVerifyController::class)
            ->middleware('throttle:12,1');

        Route::middleware('auth:portal')->group(function (): void {
            Route::get('/portal/home', PortalHomeController::class)->name('portal.home');
            Route::get('/home', PortalHomeController::class);

            Route::get('/portal/vehicles/{vehicle}', PortalVehicleShowController::class)->name('portal.vehicles.show');
            Route::get('/vehicles/{vehicle}', PortalVehicleShowController::class);

            Route::get('/portal/vehicles/{vehicle}/documents/{document}/view', [PortalVehicleDocumentController::class, 'view'])
                ->name('portal.vehicles.documents.view');
            Route::get('/vehicles/{vehicle}/documents/{document}/view', [PortalVehicleDocumentController::class, 'view']);

            Route::get('/portal/vehicles/{vehicle}/documents/{document}/download', [PortalVehicleDocumentController::class, 'download'])
                ->name('portal.vehicles.documents.download');
            Route::get('/vehicles/{vehicle}/documents/{document}/download', [PortalVehicleDocumentController::class, 'download']);

            Route::get('/portal/vehicles/{vehicle}/inspections/{repairOrder}', [PortalVehicleInspectionController::class, 'show'])
                ->name('portal.vehicles.inspections.show');
            Route::get('/portal/vehicles/{vehicle}/inspections/{repairOrder}/print', [PortalVehicleInspectionController::class, 'print'])
                ->name('portal.vehicles.inspections.print');
            Route::get('/portal/vehicles/{vehicle}/inspections/{repairOrder}/pdf', [PortalVehicleInspectionController::class, 'pdf'])
                ->name('portal.vehicles.inspections.pdf');
            Route::get('/portal/vehicles/{vehicle}/inspections/{repairOrder}/photos/{photo}', [PortalVehicleInspectionController::class, 'photo'])
                ->name('portal.vehicles.inspections.photos.show');

            Route::post('/portal/logout', PortalLogoutController::class)->name('portal.logout');
            Route::post('/logout', PortalLogoutController::class);
        });

        Route::get('/go/{code}', PortalShortLinkRedirectController::class)
            ->name('portal.short.redirect');

        Route::middleware('throttle:portal-token-read')->group(function (): void {
            Route::get('/r/{code}', RepairPortalShowController::class)
                ->name('portal.repair.show');
            Route::get('/portal/r/{code}', RepairPortalShowController::class);

            Route::get('/r/{code}/evidence/{evidence}', RepairPortalEvidenceShowController::class)
                ->name('portal.repair.evidence.show');
            Route::get('/portal/r/{code}/evidence/{evidence}', RepairPortalEvidenceShowController::class);

            Route::get('/r/{code}/inspection', [RepairPortalInspectionController::class, 'show'])
                ->name('portal.repair.inspection.show');
            Route::get('/portal/r/{code}/inspection', [RepairPortalInspectionController::class, 'show']);
            Route::get('/r/{code}/inspection/print', [RepairPortalInspectionController::class, 'print'])
                ->name('portal.repair.inspection.print');
            Route::get('/portal/r/{code}/inspection/print', [RepairPortalInspectionController::class, 'print']);
            Route::get('/r/{code}/inspection/pdf', [RepairPortalInspectionController::class, 'pdf'])
                ->name('portal.repair.inspection.pdf');
            Route::get('/portal/r/{code}/inspection/pdf', [RepairPortalInspectionController::class, 'pdf']);
            Route::get('/r/{code}/inspection/photos/{photo}', [RepairPortalInspectionController::class, 'photo'])
                ->name('portal.repair.inspection.photos.show');
            Route::get('/portal/r/{code}/inspection/photos/{photo}', [RepairPortalInspectionController::class, 'photo']);
            Route::get('/portal/pay/{token}', PortalInvoicePayShowController::class)
                ->name('portal.invoice-pay.show');
            Route::get('/pay/{token}', PortalInvoicePayShowController::class);

            Route::get('/portal/estimates/{token}', PortalEstimateShowController::class)
                ->name('portal.estimates.show');
            Route::get('/estimates/{token}', PortalEstimateShowController::class);

            Route::get('/portal/estimates/{token}/evidence/{evidence}', PortalEvidenceShowController::class)
                ->name('portal.estimates.evidence.show');
            Route::get('/estimates/{token}/evidence/{evidence}', PortalEvidenceShowController::class);

            Route::get('/portal/estimates/{token}/pdf', [PortalEstimatePdfController::class, 'view'])
                ->name('portal.estimates.pdf.view');
            Route::get('/estimates/{token}/pdf', [PortalEstimatePdfController::class, 'view']);

            Route::get('/portal/estimates/{token}/pdf/download', [PortalEstimatePdfController::class, 'download'])
                ->name('portal.estimates.pdf.download');
            Route::get('/estimates/{token}/pdf/download', [PortalEstimatePdfController::class, 'download']);

            Route::get('/portal/inspections/{token}', PortalInspectionShowController::class)
                ->name('portal.inspections.show');
            Route::get('/inspections/{token}', PortalInspectionShowController::class);

            Route::get('/portal/inspections/{token}/print', PortalInspectionPrintController::class)
                ->name('portal.inspections.print');
            Route::get('/inspections/{token}/print', PortalInspectionPrintController::class);

            Route::get('/portal/inspections/{token}/pdf', PortalInspectionPdfController::class)
                ->name('portal.inspections.pdf');
            Route::get('/inspections/{token}/pdf', PortalInspectionPdfController::class);

            Route::get('/portal/inspections/{token}/photos/{photo}', PortalInspectionPhotoShowController::class)
                ->name('portal.inspections.photos.show');
            Route::get('/inspections/{token}/photos/{photo}', PortalInspectionPhotoShowController::class);
        });

        Route::middleware('throttle:portal-token-write')->group(function (): void {
            Route::post('/portal/estimates/{token}/authorize', PortalEstimateAuthorizeController::class)
                ->name('portal.estimates.authorize');
            Route::post('/estimates/{token}/authorize', PortalEstimateAuthorizeController::class);

            Route::post('/portal/estimates/{token}/deposits', PortalEstimateDepositInitiateController::class)
                ->name('portal.estimates.deposits.store');
            Route::post('/estimates/{token}/deposits', PortalEstimateDepositInitiateController::class);

            Route::post('/portal/estimates/{token}/deposits/{attempt}/complete', PortalEstimateDepositCompleteController::class)
                ->name('portal.estimates.deposits.complete');
            Route::post('/estimates/{token}/deposits/{attempt}/complete', PortalEstimateDepositCompleteController::class);

            Route::post('/portal/pay/{token}/attempts', PortalInvoicePayInitiateController::class)
                ->name('portal.invoice-pay.attempts.store');
            Route::post('/pay/{token}/attempts', PortalInvoicePayInitiateController::class);

            Route::post('/portal/pay/{token}/attempts/{attempt}/complete', PortalInvoicePayCompleteController::class)
                ->name('portal.invoice-pay.attempts.complete');
            Route::post('/pay/{token}/attempts/{attempt}/complete', PortalInvoicePayCompleteController::class);
        });
    });
});
