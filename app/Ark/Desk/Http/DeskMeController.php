<?php

namespace App\Ark\Desk\Http;

use App\Ark\Desk\DeskWorkProjection;
use App\Ark\Mobile\MobileUserPresenter;
use App\Ark\Operations\Workstations\Workstation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DeskMeController
{
    public function __invoke(Request $request, MobileUserPresenter $presenter, DeskWorkProjection $projection): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $workstation = Workstation::query()->where('current_operator_user_id', $user->id)->first();

        return response()->json([
            'product' => 'ark_desk',
            'shop' => $projection->shopContext(),
            'workstation' => $workstation === null ? null : [
                'id' => $workstation->id,
                'name' => $workstation->displayLocation(),
            ],
            'stations' => $projection->stations($user),
            ...$presenter->presentShell($user),
        ]);
    }
}
