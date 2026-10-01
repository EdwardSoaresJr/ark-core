<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileUserPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MobileMeController
{
    public function __invoke(Request $request, MobileUserPresenter $presenter): JsonResponse
    {
        return response()->json($presenter->presentShell($request->user()));
    }
}
