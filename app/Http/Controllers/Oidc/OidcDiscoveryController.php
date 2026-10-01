<?php

namespace App\Http\Controllers\Oidc;

use App\Ark\Runtime\Identity\Oidc\OidcDiscoveryDocument;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class OidcDiscoveryController extends Controller
{
    public function __invoke(OidcDiscoveryDocument $document): JsonResponse
    {
        return response()->json($document->toArray());
    }
}
