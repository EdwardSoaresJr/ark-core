<?php

namespace App\Ark\Operations\EstimatePricing;

/**
 * Structured warranty coverage for shop defaults or a billing class.
 *
 * @phpstan-type WarrantyArray array{
 *     months: int|null,
 *     miles: int|null,
 *     covers_parts: bool,
 *     covers_labor: bool,
 *     description: string,
 *     exclusions: string,
 *     third_party: bool
 * }
 */
final class BillingClassWarranty
{
    public function __construct(
        public readonly ?int $months,
        public readonly ?int $miles,
        public readonly bool $coversParts,
        public readonly bool $coversLabor,
        public readonly string $description,
        public readonly string $exclusions,
        public readonly bool $thirdParty = false,
    ) {}

    public static function none(): self
    {
        return new self(null, null, false, false, '', '', false);
    }

    public static function shopDefault(): self
    {
        return new self(
            months: 24,
            miles: 24000,
            coversParts: true,
            coversLabor: true,
            description: 'Shop warranty on parts and labor for the covered repair.',
            exclusions: 'Customer-supplied and used parts may carry no warranty. Normal wear items and abuse are excluded.',
            thirdParty: false,
        );
    }

    /**
     * @param  array<string, mixed>|null  $raw
     */
    public static function fromArray(?array $raw): self
    {
        if ($raw === null) {
            return self::none();
        }

        $months = filled($raw['months'] ?? null) ? max(0, (int) $raw['months']) : null;
        $miles = filled($raw['miles'] ?? null) ? max(0, (int) $raw['miles']) : null;

        return new self(
            months: $months,
            miles: $miles,
            coversParts: (bool) ($raw['covers_parts'] ?? false),
            coversLabor: (bool) ($raw['covers_labor'] ?? false),
            description: trim((string) ($raw['description'] ?? '')),
            exclusions: trim((string) ($raw['exclusions'] ?? '')),
            thirdParty: (bool) ($raw['third_party'] ?? false),
        );
    }

    /**
     * @return WarrantyArray
     */
    public function toArray(): array
    {
        return [
            'months' => $this->months,
            'miles' => $this->miles,
            'covers_parts' => $this->coversParts,
            'covers_labor' => $this->coversLabor,
            'description' => $this->description,
            'exclusions' => $this->exclusions,
            'third_party' => $this->thirdParty,
        ];
    }

    public function isEmpty(): bool
    {
        return $this->months === null
            && $this->miles === null
            && ! $this->coversParts
            && ! $this->coversLabor
            && $this->description === ''
            && $this->exclusions === '';
    }

    public function summaryLabel(): string
    {
        if ($this->isEmpty() && ! $this->coversParts && ! $this->coversLabor) {
            return 'No warranty';
        }

        $duration = [];
        if ($this->months !== null && $this->months > 0) {
            $duration[] = $this->months.'-month';
        }
        if ($this->miles !== null && $this->miles > 0) {
            $duration[] = number_format($this->miles).'-mile';
        }

        $coverage = match (true) {
            $this->coversParts && $this->coversLabor => 'parts and labor',
            $this->coversParts => 'parts',
            $this->coversLabor => 'labor',
            default => 'coverage',
        };

        $prefix = $duration !== [] ? implode(' / ', $duration).' ' : '';

        return trim($prefix.$coverage.($this->thirdParty ? ' (third-party)' : ''));
    }
}
