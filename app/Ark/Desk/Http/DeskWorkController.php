<?php

namespace App\Ark\Desk\Http;

use App\Ark\Desk\DeskWorkProjection;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DeskWorkController
{
    public function __invoke(Request $request, DeskWorkProjection $projection): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return response()->json($projection->forUser($user));
    }
}
