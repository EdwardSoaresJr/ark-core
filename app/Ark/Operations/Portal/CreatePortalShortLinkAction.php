<?php

namespace App\Ark\Operations\Portal;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

final class CreatePortalShortLinkAction
{
    public function execute(
        string $destinationUrl,
        ?Carbon $expiresAt = null,
        ?RepairOrder $repairOrder = null,
        ?PortalShortLinkPurpose $purpose = null,
    ): string {
        $destinationUrl = trim($destinationUrl);

        if ($repairOrder !== null && $purpose !== null) {
            $existing = PortalShortLink::query()
                ->where('repair_order_id', $repairOrder->id)
                ->where('purpose', $purpose->value)
                ->where(function ($query): void {
                    $query->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->orderBy('id')
                ->first();

            if ($existing !== null) {
                $existing->forceFill([
                    'destination_url' => $destinationUrl,
                    'expires_at' => $expiresAt,
                ])->save();

                return $this->url($existing);
            }
        } else {
            $existing = PortalShortLink::query()
                ->where('destination_url', $destinationUrl)
                ->where(function ($query): void {
                    $query->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->first();

            if ($existing !== null) {
                return $this->url($existing);
            }
        }

        $link = PortalShortLink::query()->create([
            'code' => $this->uniqueCode(),
            'repair_order_id' => $repairOrder?->id,
            'purpose' => $purpose,
            'destination_url' => $destinationUrl,
            'expires_at' => $expiresAt,
        ]);

        return $this->url($link);
    }

    private function url(PortalShortLink $link): string
    {
        return route('portal.short.redirect', ['code' => $link->code]);
    }

    private function uniqueCode(): string
    {
        do {
            $code = Str::lower(Str::random(10));
        } while (PortalShortLink::query()->where('code', $code)->exists());

        return $code;
    }
}
