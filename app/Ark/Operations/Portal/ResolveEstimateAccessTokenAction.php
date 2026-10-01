<?php

namespace App\Ark\Operations\Portal;

final class ResolveEstimateAccessTokenAction
{
    public function execute(string $plainToken, bool $touchViewed = false): ?EstimateAccessToken
    {
        $plainToken = trim($plainToken);

        if ($plainToken === '') {
            return null;
        }

        $token = EstimateAccessToken::query()
            ->where('token_hash', EstimateAccessToken::hashPlainToken($plainToken))
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
