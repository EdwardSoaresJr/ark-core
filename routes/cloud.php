<?php

use App\Ark\Runtime\Surfaces\SurfaceRouting;
use Illuminate\Support\Facades\Route;

/*
| ARK Platform product portal does not live on Core Boxes.
|
| Manage / Connect targets ARK Platform (ARK_CLOUD_BASE_URL / cloud.arksms.com).
| Legacy /cloud bookmarks redirect away when a Cloud base URL is configured.
*/

$cloudBase = static function (): string {
    return rtrim((string) (
        config('services.ark_cloud.base_url')
        ?: config('services.ark_mail.base_url')
        ?: ''
    ), '/');
};

$redirectToCloud = static function (?string $path = null) use ($cloudBase) {
    $base = $cloudBase();
    if ($base === '') {
        abort(404);
    }

    $suffix = filled($path) ? '/'.ltrim($path, '/') : '';

    return redirect()->away($base.$suffix, 301);
};

SurfaceRouting::appRoutes(function () use ($redirectToCloud): void {
    Route::middleware('web')->get('/cloud', fn () => $redirectToCloud())->name('cloud.app.redirect');
    Route::middleware('web')->get('/cloud/{path}', fn (string $path) => $redirectToCloud($path))
        ->where('path', '.*')
        ->name('cloud.app.redirect.path');
});

if (SurfaceRouting::companyEnabled()) {
    SurfaceRouting::companyRoutes(function () use ($redirectToCloud): void {
        Route::middleware('web')->get('/', fn () => $redirectToCloud())->name('cloud.home');
        Route::middleware('web')->get('/{path}', fn (string $path) => $redirectToCloud($path))
            ->where('path', '.*')
            ->name('cloud.legacy.redirect');
    });
}
