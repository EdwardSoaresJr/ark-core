<?php

use App\Ark\Install\Http\SetupWizardController;
use App\Ark\Install\Middleware\EnforceInstallJourney;
use App\Ark\Install\Middleware\EnsureSetupAllowed;
use App\Ark\Install\Middleware\UseInstallerRuntime;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', UseInstallerRuntime::class, EnsureSetupAllowed::class, EnforceInstallJourney::class])
    ->prefix('setup')
    ->group(function (): void {
        Route::get('/', [SetupWizardController::class, 'welcome'])->name('install.welcome');
        Route::get('/system', [SetupWizardController::class, 'system'])->name('install.system');
        Route::get('/database', [SetupWizardController::class, 'database'])->name('install.database');
        Route::post('/database/test', [SetupWizardController::class, 'testDatabase'])->name('install.database.test');
        Route::get('/shop', [SetupWizardController::class, 'shop'])->name('install.shop');
        Route::post('/shop', [SetupWizardController::class, 'storeShop'])->name('install.shop.store');
        Route::get('/admin', [SetupWizardController::class, 'admin'])->name('install.admin');
        Route::post('/admin', [SetupWizardController::class, 'storeAdmin'])->name('install.admin.store');
        Route::get('/review', [SetupWizardController::class, 'review'])->name('install.review');
        Route::post('/install', [SetupWizardController::class, 'install'])->name('install.run');
        Route::get('/progress', [SetupWizardController::class, 'progress'])->name('install.progress');
        Route::get('/progress/status', [SetupWizardController::class, 'progressStatus'])->name('install.progress.status');
        Route::get('/complete', [SetupWizardController::class, 'complete'])->name('install.complete');
    });
