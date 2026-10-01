<?php

namespace App\Ark\Tech\Http;

use App\Ark\Tech\TechFindingRewriteService;
use App\Ark\Tech\TechStaffGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class TechDragonRewriteController
{
    public function __invoke(Request $request, TechStaffGate $gate, TechFindingRewriteService $rewrite): JsonResponse
    {
        abort_unless($gate->canUseTech($request->user()), 403);

        $data = $request->validate([
            'text' => ['required', 'string', 'max:5000'],
        ]);

        try {
            $proposed = $rewrite->propose($data['text']);
        } catch (RuntimeException $exception) {
            return response()->json([
                'available' => false,
                'message' => $exception->getMessage(),
                'original' => $data['text'],
            ], 503);
        }

        return response()->json([
            'available' => true,
            'original' => $data['text'],
            'proposed' => $proposed,
            'applied' => false,
        ]);
    }
}
