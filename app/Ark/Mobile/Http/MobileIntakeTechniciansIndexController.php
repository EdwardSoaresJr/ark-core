<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Operations\Staff\SoloShopOperations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MobileIntakeTechniciansIndexController
{
    public function __invoke(Request $request, MobileStaffAccess $access, SoloShopOperations $soloShop): JsonResponse
    {
        abort_unless($access->canPerformIntake($request->user()), 403);

        $items = $soloShop->assignableTechnicians()
            ->map(fn ($user): array => [
                'id' => $user->id,
                'name' => $user->name,
            ])
            ->values()
            ->all();

        return response()->json([
            'items' => $items,
            'count' => count($items),
            'requires_assignment' => $soloShop->requiresTechnicianAssignment(),
        ]);
    }
}
