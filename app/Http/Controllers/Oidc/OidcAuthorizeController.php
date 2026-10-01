<?php

namespace App\Http\Controllers\Oidc;

use App\Ark\Runtime\Identity\Oidc\OidcAuthorizationDeniedException;
use App\Ark\Runtime\Identity\Oidc\OidcAuthorizationService;
use App\Ark\Runtime\Identity\Oidc\OidcClient;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

final class OidcAuthorizeController extends Controller
{
    public function __invoke(Request $request, OidcAuthorizationService $authorization): RedirectResponse
    {
        $validated = $request->validate([
            'client_id' => ['required', 'string'],
            'redirect_uri' => ['required', 'url'],
            'response_type' => ['required', 'in:code'],
            'scope' => ['nullable', 'string'],
            'state' => ['nullable', 'string'],
            'code_challenge' => ['required', 'string'],
            'code_challenge_method' => ['required', 'in:S256'],
        ]);

        $client = OidcClient::query()->where('client_id', $validated['client_id'])->first();

        if ($client === null || ! $client->allowsRedirectUri($validated['redirect_uri'])) {
            abort(400, 'Invalid client or redirect URI.');
        }

        if (! Auth::check()) {
            $request->session()->put('url.intended', $request->fullUrl());

            return redirect()->route('login');
        }

        $user = Auth::user();

        try {
            $authorization->assertUserMayAuthorize($user, $client);
        } catch (OidcAuthorizationDeniedException $exception) {
            return $this->redirectError(
                $validated['redirect_uri'],
                $exception->errorCode,
                $exception->getMessage(),
                $validated['state'] ?? null,
            );
        }

        $scopes = collect(explode(' ', (string) ($validated['scope'] ?? 'openid')))
            ->filter()
            ->values()
            ->all();

        $code = $authorization->createAuthorizationCode(
            user: $user,
            client: $client,
            redirectUri: $validated['redirect_uri'],
            codeChallenge: $validated['code_challenge'],
            codeChallengeMethod: $validated['code_challenge_method'],
            scopes: $scopes,
        );

        $query = http_build_query(array_filter([
            'code' => $code->code,
            'state' => $validated['state'] ?? null,
        ]));

        return redirect()->away($validated['redirect_uri'].'?'.$query);
    }

    private function redirectError(string $redirectUri, string $error, string $description, ?string $state): RedirectResponse
    {
        $query = http_build_query(array_filter([
            'error' => $error,
            'error_description' => $description,
            'state' => $state,
        ]));

        return redirect()->away($redirectUri.(Str::contains($redirectUri, '?') ? '&' : '?').$query);
    }
}
