<?php

namespace App\Http\Controllers;

use App\Ark\Runtime\Broadcast\ReverbDeployment;
use Illuminate\Http\JsonResponse;

class ReverbDeploymentHealthController
{
    public function __invoke(): JsonResponse
    {
        return response()->json(ReverbDeployment::diagnostics());
    }
}
