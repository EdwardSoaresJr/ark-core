<?php

namespace App\Ark\Operations\Telephony;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffPresenceHeartbeatController
{
    public function __invoke(Request $request, StaffCallPresence $presence): JsonResponse
    {
        $user = $request->user();

        if ($user instanceof User) {
            $presence->markPresent($user);
        }

        return response()->json(['ok' => true]);
    }
}
