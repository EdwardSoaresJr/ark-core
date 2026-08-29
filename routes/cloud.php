<?php

use App\Ark\Platform\Cloud\CloudExperienceController;
use App\Ark\Runtime\Surfaces\SurfaceRouting;
use Illuminate\Support\Facades\Route;

/*
| ARK Cloud — company product click-through.
|
| Production: autorepairkeeper.com (and www → apex).
| Local / tests without COMPANY_DOMAIN: /cloud on the app host.
| Legacy app.demo-auto.test/cloud → company apex (301).
*/

$registerCloud = function (): void {
    Route::get('/', [CloudExperienceController::class, 'home'])->name('home');
    Route::get('/features', [CloudExperienceController::class, 'features'])->name('features');
    Route::get('/pricing', [CloudExperienceController::class, 'pricing'])->name('pricing');
    Route::get('/resources', [CloudExperienceController::class, 'resources'])->name('resources');
    Route::get('/demo', [CloudExperienceController::class, 'demo'])->name('demo');
    Route::get('/hosted', [CloudExperienceController::class, 'hosted'])->name('hosted');
    Route::get('/login', [CloudExperienceController::class, 'login'])->name('login');
    Route::post('/login', [CloudExperienceController::class, 'storeLogin'])->name('login.store');

    Route::get('/trial', [CloudExperienceController::class, 'trialShop'])->name('trial.shop');
    Route::post('/trial/shop', [CloudExperienceController::class, 'storeTrialShop'])->name('trial.shop.store');
    Route::get('/trial/workspace', [CloudExperienceController::class, 'trialWorkspace'])->name('trial.workspace');
    Route::post('/trial/workspace', [CloudExperienceController::class, 'storeTrialWorkspace'])->name('trial.workspace.store');
    Route::get('/trial/account', [CloudExperienceController::class, 'trialAccount'])->name('trial.account');
    Route::post('/trial/account', [CloudExperienceController::class, 'storeTrialAccount'])->name('trial.account.store');
    Route::get('/trial/provisioning', [CloudExperienceController::class, 'provisioning'])->name('trial.provisioning');
    Route::get('/welcome', [CloudExperienceController::class, 'welcome'])->name('welcome');
    Route::get('/dashboard', [CloudExperienceController::class, 'dashboard'])->name('dashboard');
    Route::get('/open-workspace', [CloudExperienceController::class, 'openWorkspace'])->name('workspace.open');
};

if (SurfaceRouting::companyEnabled()) {
    SurfaceRouting::companyRoutes(function () use ($registerCloud): void {
        Route::middleware('web')
            ->name('cloud.')
            ->group($registerCloud);
    });

    // Old review URL — send visitors to the company product host.
    SurfaceRouting::appRoutes(function (): void {
        $toCompany = function (?string $path = null) {
            $suffix = filled($path) ? '/'.ltrim($path, '/') : '';

            return redirect()->away(
                SurfaceRouting::urlForHost((string) SurfaceRouting::companyHost(), $suffix),
                301,
            );
        };

        Route::middleware('web')->get('/cloud', fn () => $toCompany())->name('cloud.app.redirect');
        Route::middleware('web')->get('/cloud/{path}', fn (string $path) => $toCompany($path))
            ->where('path', '.*')
            ->name('cloud.app.redirect.path');
    });
} else {
    SurfaceRouting::appRoutes(function () use ($registerCloud): void {
        Route::middleware('web')
            ->prefix('cloud')
            ->name('cloud.')
            ->group($registerCloud);
    });
}
