<?php

namespace App\Ark\Operations\Learn;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LearnArkPreviewController
{
    public function __invoke(Request $request, string $role, string $article): JsonResponse
    {
        $preview = LearnArkPreviewProjection::for($request->user(), $role, $article);

        if ($preview === null) {
            abort(404);
        }

        return response()->json($preview);
    }
}
