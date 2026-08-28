<?php

use App\Http\Controllers\Oidc\OidcAuthorizeController;
use App\Http\Controllers\Oidc\OidcDiscoveryController;
use App\Http\Controllers\Oidc\OidcJwksController;
use App\Http\Controllers\Oidc\OidcTokenController;
use App\Http\Controllers\Oidc\OidcUserInfoController;
use App\Http\Middleware\EnsureOidcIssuerEnabled;
use Illuminate\Support\Facades\Route;

Route::middleware([EnsureOidcIssuerEnabled::class])->group(function (): void {
    Route::get('.well-known/openid-configuration', OidcDiscoveryController::class)
        ->name('oidc.discovery');

    Route::get('.well-known/jwks.json', OidcJwksController::class)
        ->name('oidc.jwks');

    Route::post('oauth/token', OidcTokenController::class)
        ->name('oidc.token');

    Route::get('oauth/userinfo', OidcUserInfoController::class)
        ->name('oidc.userinfo');
});

Route::middleware(['web', EnsureOidcIssuerEnabled::class])->group(function (): void {
    Route::get('oauth/authorize', OidcAuthorizeController::class)
        ->name('oidc.authorize');
});
