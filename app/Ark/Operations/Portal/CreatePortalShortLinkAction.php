<?php

namespace App\Ark\Operations\Portal;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

final class CreatePortalShortLinkAction
{
    public function execute(string $destinationUrl, ?Carbon $expiresAt = null): string
    {
        $destinationUrl = trim($destinationUrl);

        $existing = PortalShortLink::query()
            ->where('destination_url', $destinationUrl)
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();

        if ($existing !== null) {
            return route('portal.short.redirect', ['code' => $existing->code]);
        }

        $link = PortalShortLink::query()->create([
            'code' => $this->uniqueCode(),
            'destination_url' => $destinationUrl,
            'expires_at' => $expiresAt,
        ]);

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
