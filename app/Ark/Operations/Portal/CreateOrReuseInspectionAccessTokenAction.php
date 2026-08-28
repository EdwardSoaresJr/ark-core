<?php

namespace App\Ark\Operations\Portal;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use Illuminate\Support\Str;

final class CreateOrReuseInspectionAccessTokenAction
{
    public function execute(
        RepairOrder $repairOrder,
        ?User $actor = null,
        bool $forStaffPreview = false,
    ): InspectionAccessTokenResult {
        if ($forStaffPreview) {
            $plainToken = Str::random(64);

            $previewToken = InspectionAccessToken::query()->create([
                'repair_order_id' => $repairOrder->id,
                'token_hash' => InspectionAccessToken::hashPlainToken($plainToken),
                'expires_at' => now()->addHour(),
                'created_by_user_id' => $actor?->id,
            ]);

            return new InspectionAccessTokenResult($previewToken, $plainToken, reused: false);
        }

        $hadActiveCustomerTokens = $this->hasActiveCustomerTokens($repairOrder);

        $plainToken = Str::random(64);

        $token = InspectionAccessToken::query()->create([
            'repair_order_id' => $repairOrder->id,
            'token_hash' => InspectionAccessToken::hashPlainToken($plainToken),
            'expires_at' => null,
            'created_by_user_id' => $actor?->id,
        ]);

        return new InspectionAccessTokenResult($token, $plainToken, reused: $hadActiveCustomerTokens);
    }

    private function hasActiveCustomerTokens(RepairOrder $repairOrder): bool
    {
        return InspectionAccessToken::query()
            ->where('repair_order_id', $repairOrder->id)
            ->whereNull('revoked_at')
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->exists();
    }
}
