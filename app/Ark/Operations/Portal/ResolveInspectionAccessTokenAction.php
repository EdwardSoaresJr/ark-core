<?php

namespace App\Ark\Operations\Portal;

final class ResolveInspectionAccessTokenAction
{
    public function execute(string $plainToken, bool $touchViewed = false): ?InspectionAccessToken
    {
        $plainToken = trim($plainToken);

        if ($plainToken === '') {
            return null;
        }

        $token = InspectionAccessToken::query()
            ->where('token_hash', InspectionAccessToken::hashPlainToken($plainToken))
            ->first();

        if ($token === null || ! $token->isUsable()) {
            return null;
        }

        if ($touchViewed) {
            $token->forceFill(['last_viewed_at' => now()])->save();

            return $token->fresh();
        }

        return $token;
    }
}
