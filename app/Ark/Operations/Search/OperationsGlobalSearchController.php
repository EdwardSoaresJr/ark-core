<?php

namespace App\Ark\Operations\Search;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OperationsGlobalSearchController
{
    public function __invoke(Request $request, OperationsGlobalSearchProjection $projection): JsonResponse
    {
        return response()->json($projection->forQuery($request->string('q')->toString()));
    }
}
