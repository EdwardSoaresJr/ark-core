<?php

namespace App\Ark\Operations\Telephony;

use Illuminate\Support\Collection;

/**
 * Remove phantom CallSession rows created from Asterisk extension AMI legs.
 */
final class CleanupExtensionLegCallSessions
{
    /**
     * @return Collection<int, CallSession>
     */
    public function candidates(): Collection
    {
        return CallSession::query()
            ->whereNull('customer_id')
            ->orderBy('id')
            ->get()
            ->filter(fn (CallSession $session): bool => TelephonyExtensionLegDial::isExtensionLegSession($session))
            ->values();
    }

    /**
     * @return list<int> deleted call_session ids
     */
    public function delete(bool $dryRun = true): array
    {
        $ids = $this->candidates()->pluck('id')->map(fn ($id): int => (int) $id)->all();

        if ($dryRun || $ids === []) {
            return $ids;
        }

        CallSession::query()->whereIn('id', $ids)->delete();

        return $ids;
    }
}
