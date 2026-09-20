<?php

namespace App\Http\Middleware;

use App\Ark\Operations\Telephony\MobileVoice\MobileVoiceEndpointRegistrar;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiStaffActive
{
    public function __construct(
        private readonly MobileVoiceEndpointRegistrar $mobileVoiceEndpoints,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($user->isActive()) {
            $token = $user->currentAccessToken();

            $this->mobileVoiceEndpoints->touchDeviceFromTokenName(
                $user,
                $token instanceof PersonalAccessToken ? $token->name : null,
            );

            return $next($request);
        }

        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json(['message' => 'This account has been disabled.'], 403);
    }
}
