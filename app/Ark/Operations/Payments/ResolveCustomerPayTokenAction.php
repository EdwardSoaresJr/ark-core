<?php

namespace App\Ark\Operations\Payments;

final class ResolveCustomerPayTokenAction
{
    public function execute(string $plainToken, bool $touchUsage = true): ?CustomerDocumentAccessToken
    {
        $plainToken = trim($plainToken);

        if ($plainToken === '') {
            return null;
        }

        $token = CustomerDocumentAccessToken::query()
            ->where('token_hash', hash('sha256', $plainToken))
            ->whereIn('scope', [
                CustomerDocumentAccessToken::SCOPE_PAY_INVOICE,
                CustomerDocumentAccessToken::SCOPE_PAY_DEPOSIT,
            ])
            ->first();

        if ($token === null || ! $token->isUsable()) {
            return null;
        }

        if ($touchUsage) {
            $token->forceFill(['last_used_at' => now()])->save();

            return $token->fresh();
        }

        return $token;
    }
}
