<?php

namespace App\Http\Controllers\Oidc;

use App\Ark\Runtime\Identity\Oidc\OidcKeyRepository;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class OidcJwksController extends Controller
{
    public function __invoke(OidcKeyRepository $keys): JsonResponse
    {
        return response()->json($keys->jwksDocument());
    }
}
