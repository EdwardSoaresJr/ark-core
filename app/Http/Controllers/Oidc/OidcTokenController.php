<?php

namespace App\Http\Controllers\Oidc;

use App\Ark\Runtime\Identity\Oidc\OidcAuthorizationDeniedException;
use App\Ark\Runtime\Identity\Oidc\OidcAuthorizationService;
use App\Ark\Runtime\Identity\Oidc\OidcClient;
use App\Ark\Runtime\Identity\Oidc\OidcJwtEncoder;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class OidcTokenController extends Controller
{
    public function __invoke(
        Request $request,
        OidcAuthorizationService $authorization,
        OidcJwtEncoder $jwt,
    ): JsonResponse {
        [$clientId, $clientSecret] = $this->resolveClientCredentials($request);

        $request->merge(array_filter([
            'client_id' => $clientId ?? $request->input('client_id'),
            'client_secret' => $clientSecret ?? $request->input('client_secret'),
        ], static fn (?string $value): bool => $value !== null));

        $validated = $request->validate([
            'grant_type' => ['required', 'in:authorization_code'],
            'code' => ['required', 'string'],
            'redirect_uri' => ['required', 'url'],
            'client_id' => ['required', 'string'],
            'client_secret' => ['nullable', 'string'],
            'code_verifier' => ['required', 'string'],
        ]);

        $client = OidcClient::query()->where('client_id', $validated['client_id'])->first();

        if ($client === null || ! $client->verifySecret($validated['client_secret'] ?? null)) {
            return $this->oauthError('invalid_client', 'Client authentication failed.', 401);
        }

        if (! $client->allowsRedirectUri($validated['redirect_uri'])) {
            return $this->oauthError('invalid_grant', 'Redirect URI mismatch.');
        }

        try {
            $authorizationCode = $authorization->exchangeAuthorizationCode(
                code: $validated['code'],
                client: $client,
                redirectUri: $validated['redirect_uri'],
                codeVerifier: $validated['code_verifier'],
            );
        } catch (OidcAuthorizationDeniedException $exception) {
            return $this->oauthError($exception->errorCode, $exception->getMessage());
        }

        $user = $authorizationCode->user;
        $clientId = $client->client_id;

        return response()->json([
            'access_token' => $jwt->accessToken($user, $clientId),
            'token_type' => 'Bearer',
            'expires_in' => (int) config('oidc.access_token_ttl_seconds'),
            'id_token' => $jwt->idToken($user, $clientId),
            'scope' => implode(' ', $authorizationCode->scopes ?? ['openid']),
        ]);
    }

    /**
     * BookStack sends confidential client credentials via HTTP Basic auth.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function resolveClientCredentials(Request $request): array
    {
        $authorization = (string) $request->header('Authorization', '');

        if (! str_starts_with($authorization, 'Basic ')) {
            return [null, null];
        }

        $decoded = base64_decode(substr($authorization, 6), true);

        if ($decoded === false || ! str_contains($decoded, ':')) {
            return [null, null];
        }

        [$clientId, $clientSecret] = explode(':', $decoded, 2);

        return [$clientId, $clientSecret];
    }

    private function oauthError(string $error, string $description, int $status = 400): JsonResponse
    {
        return response()->json([
            'error' => $error,
            'error_description' => $description,
        ], $status);
    }
}
