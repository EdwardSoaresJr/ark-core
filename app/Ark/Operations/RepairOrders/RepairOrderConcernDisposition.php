<?php

namespace App\Ark\Operations\RepairOrders;

enum RepairOrderConcernDisposition: string
{
    case Draft = 'draft';
    case Recommended = 'recommended';
    case Approved = 'approved';
    case Deferred = 'deferred';
    case Declined = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Recommended => 'Recommended',
            self::Approved => 'Approved',
            self::Deferred => 'Deferred',
            self::Declined => 'Declined',
        };
    }

    public function helpText(): string
    {
        return match ($this) {
            self::Draft => 'Still being built; not on the customer-facing total.',
            self::Recommended => 'Suggested work; on the estimate until other scopes are approved.',
            self::Approved => 'Customer authorized; counts toward invoice and balance due.',
            self::Deferred => 'Not approved today; retained for a future visit follow-up.',
            self::Declined => 'Customer declined this work; not a follow-up opportunity.',
        };
    }

    public static function advisorHelpOverview(): string
    {
        return collect(self::advisorHelpOverviewItems())
            ->map(fn (array $item): string => $item['label'].' — '.$item['detail'])
            ->implode("\n");
    }

    /**
     * @return list<array{label: string, detail: string}>
     */
    public static function advisorHelpOverviewItems(): array
    {
        return collect(self::cases())
            ->map(fn (self $disposition): array => [
                'label' => $disposition->label(),
                'detail' => $disposition->helpText(),
            ])
            ->all();
    }

    public function countsTowardEstimateTotal(bool $repairOrderHasApprovedWork = false): bool
    {
        return match (true) {
            in_array($this, [self::Draft, self::Deferred, self::Declined], true) => false,
            $repairOrderHasApprovedWork && $this === self::Recommended => false,
            default => true,
        };
    }

    /**
     * Scopes that may carry bay production status (In Progress, etc.).
     * Draft/Recommended must not block bay progress before authorization.
     */
    public function tracksProductionPath(): bool
    {
        return match ($this) {
            self::Draft, self::Recommended, self::Approved => true,
            default => false,
        };
    }

    public function showsInScopeHeader(): bool
    {
        return $this !== self::Draft;
    }

    public function visibleToCustomer(): bool
    {
        return $this->showsInScopeHeader();
    }

    /** Customer-facing scope header; recommended work awaiting decision reads as pending. */
    public function scopeHeaderLabel(): string
    {
        return match ($this) {
            self::Recommended => 'Pending',
            default => $this->label(),
        };
    }

    public function decisionMark(): string
    {
        return match ($this) {
            self::Approved => '✓',
            self::Deferred => '—',
            self::Declined => '✗',
            default => '',
        };
    }

    public function scopeHeaderDecisionClass(): string
    {
        return 'ops-scope-header-decision--'.$this->value;
    }

    /** Inline worksheet tone so disposition color survives form-plugin and build purging. */
    public function worksheetToneStyle(): string
    {
        return match ($this) {
            self::Draft => 'border-color:#cbd5e1;background-color:#f1f5f9;color:#334155;',
            self::Recommended => 'border-color:#93c5fd;background-color:#dbeafe;color:#1e3a8a;',
            self::Approved => 'border-color:#6ee7b7;background-color:#d1fae5;color:#064e3b;',
            self::Deferred => 'border-color:#fcd34d;background-color:#fef3c7;color:#78350f;',
            self::Declined => 'border-color:#fca5a5;background-color:#fee2e2;color:#7f1d1d;',
        };
    }

    public static function fromStored(string $value): ?self
    {
        return self::tryFrom($value);
    }
}
