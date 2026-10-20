<?php

namespace App\Ark\Operations\RepairOrders;

/**
 * Customer action priority. Diagnostic, Maintenance, and Repair Verification
 * are work semantics on RecommendationIntent, not priorities.
 */
enum RecommendationPriority: string
{
    case MustDo = 'must_do';
    case ShouldDo = 'should_do';
    case CouldDo = 'could_do';

    public function staffLabel(): string
    {
        return match ($this) {
            self::MustDo => 'Must Do',
            self::ShouldDo => 'Should Do',
            self::CouldDo => 'Could Do',
        };
    }

    public function customerLabel(): string
    {
        return $this->staffLabel();
    }

    public function helpText(): string
    {
        return match ($this) {
            self::MustDo => 'Address before the vehicle returns or is driven further.',
            self::ShouldDo => 'Schedule in the near term.',
            self::CouldDo => 'Worth doing when time and budget allow.',
        };
    }

    public function deferredFollowUpAction(): string
    {
        return match ($this) {
            self::MustDo => 'Revisit must-do item at next contact',
            self::ShouldDo => 'Schedule should-do work at next visit',
            self::CouldDo => 'Offer could-do work at next visit',
        };
    }

    public function continuityReminder(): string
    {
        return match ($this) {
            self::MustDo => 'Must-do item should be revisited calmly.',
            self::ShouldDo => 'Should-do work retained for the next visit.',
            self::CouldDo => 'Could-do work retained for the next visit.',
        };
    }

    /** Category accent. Authoritative for worksheet, review, and PDF. */
    public function accentColor(): string
    {
        return match ($this) {
            self::MustDo => '#9f1239',
            self::ShouldDo => '#4338ca',
            self::CouldDo => '#b45309',
        };
    }

    public function tintBackground(): string
    {
        return match ($this) {
            self::MustDo => '#fff1f2',
            self::ShouldDo => '#eef2ff',
            self::CouldDo => '#fffbeb',
        };
    }

    public function darkTintColor(): string
    {
        return $this->accentColor();
    }

    public function sortKey(): int
    {
        return match ($this) {
            self::MustDo => 0,
            self::ShouldDo => 1,
            self::CouldDo => 2,
        };
    }

    public static function tryFromStored(?string $value): ?self
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return self::tryFrom($value) ?? self::fromHistoricalIntent($value);
    }

    /**
     * Urgency values that already meant customer action. Work semantics are not mapped.
     */
    public static function fromHistoricalIntent(?string $value): ?self
    {
        return match ($value) {
            'must_do', 'immediate_attention', 'high', 'safety_drivability' => self::MustDo,
            'should_do', 'plan_soon', 'future_repair' => self::ShouldDo,
            'could_do' => self::CouldDo,
            default => null,
        };
    }

    public static function isHistoricalInformationOnly(?string $value): bool
    {
        return in_array($value, ['information_only', 'low', 'monitor'], true);
    }
}
