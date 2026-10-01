<?php

namespace App\Ark\Import;

use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use Illuminate\Support\Carbon;

final class LegacyRepairOrderTimeline
{
    /**
     * Legacy status slugs that represent operational completion for close-date resolution.
     *
     * @var list<string>
     */
    public const LEGACY_COMPLETION_SLUGS = [
        'closed',
        'completed',
        'invoiced',
        'paid',
        'archived',
        'cancelled',
        'void',
    ];

    /**
     * @param  array<string, mixed>  $legacy
     */
    public static function openedAt(array $legacy): ?Carbon
    {
        return self::parse($legacy['opened_at'] ?? $legacy['created_at'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $legacy
     */
    public static function closedAt(array $legacy, RepairOrderStatus $status, ?string $legacyStatusSlug = null): ?Carbon
    {
        if (! self::statusReceivesCloseDate($status)) {
            return null;
        }

        $slug = strtolower(trim((string) ($legacyStatusSlug ?? $legacy['status'] ?? '')));

        $candidates = [
            $legacy['legacy_closed_at'] ?? null,
            $legacy['closed_at'] ?? null,
        ];

        if ($slug !== '' && in_array($slug, self::LEGACY_COMPLETION_SLUGS, true)) {
            $candidates[] = $legacy['status_changed_at'] ?? null;
        }

        $candidates[] = $legacy['invoice_finalized_at'] ?? null;
        $candidates[] = $legacy['invoice_paid_at'] ?? $legacy['paid_at'] ?? null;
        $candidates[] = $legacy['customer_approved_at'] ?? null;

        if ($slug !== '' && in_array($slug, self::LEGACY_COMPLETION_SLUGS, true)) {
            $candidates[] = $legacy['updated_at'] ?? null;
        }

        foreach ($candidates as $candidate) {
            $parsed = self::parse($candidate);

            if ($parsed !== null) {
                return $parsed;
            }
        }

        return null;
    }

    public static function statusReceivesCloseDate(RepairOrderStatus $status): bool
    {
        return $status->isTerminal()
            || $status === RepairOrderStatus::ReadyPickup
            || $status === RepairOrderStatus::Invoiced;
    }

    private static function parse(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
