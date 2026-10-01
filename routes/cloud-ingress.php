<?php

use App\Ark\Platform\Http\FabricIngressController;
use App\Ark\Platform\Http\VerifyPlatformFabricSignature;
use App\Ark\Platform\Website\Http\Controllers\WebsiteManagementReadController;
use Illuminate\Support\Facades\Route;

/*
| Cloud → Core fabric ingress (signed, no staff session).
| CSRF: bootstrap/app.php excepts webhooks/*.
*/

Route::post('/webhooks/cloud/fabric/events', FabricIngressController::class)
    ->middleware(VerifyPlatformFabricSignature::class)
    ->name('webhooks.cloud.fabric.events');

Route::get('/webhooks/cloud/website/{publicHost}', WebsiteManagementReadController::class)
    ->middleware(VerifyPlatformFabricSignature::class)
    ->where('publicHost', '[A-Za-z0-9][A-Za-z0-9.-]*')
    ->name('webhooks.cloud.website.show');
