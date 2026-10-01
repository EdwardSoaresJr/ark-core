<?php

namespace App\Ark\Desk\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DeskLogoutController
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Signed out.']);
    }
}
