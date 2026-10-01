<?php

namespace App\Ark\Operations\Portal;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use Illuminate\Support\Str;

final class CreateOrReuseEstimateAccessTokenAction
{
    public function execute(
        RepairOrder $repairOrder,
        ?User $actor = null,
        ?int $expiresInDays = null,
        bool $forStaffPreview = false,
    ): EstimateAccessTokenResult {
        if ($forStaffPreview) {
            $plainToken = Str::random(64);

            $previewToken = EstimateAccessToken::query()->create([
                'repair_order_id' => $repairOrder->id,
                'token_hash' => EstimateAccessToken::hashPlainToken($plainToken),
                'expires_at' => now()->addHour(),
                'created_by_user_id' => $actor?->id,
            ]);

            return new EstimateAccessTokenResult($previewToken, $plainToken, reused: false);
        }

        $hadActiveCustomerTokens = $this->hasActiveCustomerTokens($repairOrder);

        $plainToken = Str::random(64);

        $token = EstimateAccessToken::query()->create([
            'repair_order_id' => $repairOrder->id,
            'token_hash' => EstimateAccessToken::hashPlainToken($plainToken),
            'expires_at' => $expiresInDays !== null ? now()->addDays($expiresInDays) : null,
            'created_by_user_id' => $actor?->id,
        ]);

        return new EstimateAccessTokenResult($token, $plainToken, reused: $hadActiveCustomerTokens);
    }

    private function hasActiveCustomerTokens(RepairOrder $repairOrder): bool
    {
        return EstimateAccessToken::query()
            ->where('repair_order_id', $repairOrder->id)
            ->whereNull('revoked_at')
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->exists();
    }
}
