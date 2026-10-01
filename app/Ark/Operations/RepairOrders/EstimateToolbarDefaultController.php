<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Runtime\Preferences\EstimateToolbarPreference;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EstimateToolbarDefaultController
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kind' => ['required', Rule::in([
                EstimateToolbarPreference::KIND_PARTS,
                EstimateToolbarPreference::KIND_LABOR,
            ])],
            'key' => ['required', 'string', 'max:32'],
        ]);

        $kind = $validated['kind'];
        $key = $validated['key'];

        $user = $request->user();
        abort_unless($user instanceof User, 403);
        abort_unless(in_array($key, EstimateToolbarPreference::allowedKeys($kind, $user), true), 422);

        EstimateToolbarPreference::persist($user, $kind, $key);

        return response()->json([
            'ok' => true,
            'kind' => $kind,
            'key' => EstimateToolbarPreference::stored($user->fresh(), $kind),
        ]);
    }
}
