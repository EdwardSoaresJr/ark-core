<?php

namespace App\Ark\Operations\Portal;

use App\Ark\Operations\Approvals\ApprovalEvent;
use App\Ark\Operations\Approvals\ApprovalSource;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use Brick\Money\Money;

/**
 * Customer-facing authorization records for the portal — not a communication log.
 *
 * Shows who authorized, when, and by what channel. Internal message history stays in ops.
 */
final class PortalCustomerAuthorizationPresentation
{
    public static function customerSourceLabel(ApprovalSource $source): string
    {
        return match ($source) {
            ApprovalSource::Portal => 'Online estimate',
            ApprovalSource::Phone => 'Phone',
            ApprovalSource::Sms => 'Text message',
            ApprovalSource::Email => 'Email',
            ApprovalSource::InPerson => 'In person at the shop',
        };
    }

    /**
     * @return array{
     *     approved_by: string,
     *     approved_at_label: string|null,
     *     source_label: string,
     *     approved_amount: string|null,
     * }
     */
    public static function fromApprovalEvent(ApprovalEvent $approval): array
    {
        return self::record(
            approvedBy: $approval->approved_by,
            approvedAt: $approval->approved_at,
            source: $approval->source,
            approvedAmountCents: $approval->approved_amount_cents,
        );
    }

    /**
     * @param  array<string, mixed>  $flash
     * @return array{
     *     approved_by: string,
     *     approved_at_label: string|null,
     *     source_label: string,
     *     approved_amount: string|null,
     * }
     */
    public static function fromSessionFlash(array $flash): array
    {
        $source = ApprovalSource::tryFrom((string) ($flash['source'] ?? '')) ?? ApprovalSource::Portal;

        return [
            'approved_by' => trim((string) ($flash['approved_by'] ?? '')) !== ''
                ? trim((string) $flash['approved_by'])
                : 'Customer',
            'approved_at_label' => filled($flash['approved_at_label'] ?? null)
                ? (string) $flash['approved_at_label']
                : null,
            'source_label' => self::customerSourceLabel($source),
            'approved_amount' => filled($flash['approved_amount'] ?? null)
                ? (string) $flash['approved_amount']
                : (((int) ($flash['approved_amount_cents'] ?? 0)) > 0
                    ? Money::ofMinor((int) $flash['approved_amount_cents'], 'USD')->formatTo('en_US')
                    : null),
        ];
    }

    /**
     * @return array{
     *     approved_by: string,
     *     approved_at_label: string|null,
     *     source_label: string,
     *     approved_amount: string|null,
     * }
     */
    private static function record(
        string $approvedBy,
        ?\Illuminate\Support\Carbon $approvedAt,
        ApprovalSource $source,
        int $approvedAmountCents,
    ): array {
        return [
            'approved_by' => trim($approvedBy) !== '' ? trim($approvedBy) : 'Customer',
            'approved_at_label' => $approvedAt !== null
                ? ShopDisplayTimezone::format($approvedAt)
                : null,
            'source_label' => self::customerSourceLabel($source),
            'approved_amount' => $approvedAmountCents > 0
                ? Money::ofMinor($approvedAmountCents, 'USD')->formatTo('en_US')
                : null,
        ];
    }
}
