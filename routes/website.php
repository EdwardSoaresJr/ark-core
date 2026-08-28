<?php

use App\Ark\Operations\Settings\ShopPublicSurfaceSettingsController;
use App\Ark\Runtime\Authorization\ArkCapability;
use App\Ark\Operations\Leads\Public\CommonProblemFeaturedMedia;
use App\Ark\Website\Http\Controllers\WebsiteCommonProblemMediaController;
use App\Ark\Website\Http\Controllers\WebsiteManageController;
use App\Ark\Website\Http\Controllers\WebsitePerformanceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:'.ArkCapability::SettingsManage->value])
    ->prefix('app/website')
    ->name('website.')
    ->group(function (): void {
        Route::redirect('/', '/app/website/performance')->name('index');

        Route::get('/manage', WebsiteManageController::class)->name('manage');
        Route::get('/performance', WebsitePerformanceController::class)->name('performance');

        Route::get('/page-media', [WebsiteCommonProblemMediaController::class, 'index'])->name('page-media.index');
        Route::get('/page-media/{slug}', [WebsiteCommonProblemMediaController::class, 'edit'])->name('page-media.edit');
        Route::patch('/page-media/{slug}', [WebsiteCommonProblemMediaController::class, 'update'])->name('page-media.update');

        Route::patch('/manage', [ShopPublicSurfaceSettingsController::class, 'update'])
            ->name('manage.update');
    });
