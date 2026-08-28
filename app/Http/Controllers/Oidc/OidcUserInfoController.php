<?php

namespace App\Http\Controllers\Oidc;

use App\Ark\Runtime\Identity\Oidc\OidcClaimsBuilder;
use App\Ark\Runtime\Identity\Oidc\OidcJwtDecoder;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class OidcUserInfoController extends Controller
{
    public function __invoke(Request $request, OidcJwtDecoder $decoder, OidcClaimsBuilder $claims): JsonResponse
    {
        $token = $this->bearerToken($request);

        if ($token === null) {
            return response()->json(['error' => 'invalid_token'], 401);
        }

        $payload = $decoder->decodeAndVerify($token);
        $userId = (int) ($payload['sub'] ?? 0);

        $user = User::query()->find($userId);

        if ($user === null || ! $user->isActive()) {
            return response()->json(['error' => 'invalid_token'], 401);
        }

        return response()->json($claims->forUser($user, (string) ($payload['aud'] ?? '')));
    }

    private function bearerToken(Request $request): ?string
    {
        $header = (string) $request->header('Authorization', '');

        if (preg_match('/Bearer\s+(\S+)/i', $header, $matches) === 1) {
            return $matches[1];
        }

        return $request->string('access_token')->toString() ?: null;
    }
}
