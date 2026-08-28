<?php

namespace App\Ark\Desk\Http;

use App\Ark\Desk\DeskWorkProjection;
use App\Ark\Operations\Workstations\AssignWorkstationOperatorAction;
use App\Ark\Operations\Workstations\Workstation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DeskWorkstationStoreController
{
    public function __invoke(
        Request $request,
        AssignWorkstationOperatorAction $assign,
        DeskWorkProjection $projection,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $data = $request->validate([
            'workstation_id' => ['required', 'integer', 'exists:workstations,id'],
        ]);

        $workstation = Workstation::query()->findOrFail((int) $data['workstation_id']);

        Workstation::query()
            ->where('current_operator_user_id', $user->id)
            ->where('id', '!=', $workstation->id)
            ->update(['current_operator_user_id' => null]);

        $assign->execute($workstation, $user);

        return response()->json($projection->forUser($user->fresh()));
    }
}
