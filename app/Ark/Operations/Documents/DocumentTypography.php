<?php

namespace App\Ark\Operations\Documents;

/**
 * Shop-configurable print typography for customer estimate/invoice documents.
 *
 * Scales only the document body (browser print and PDF); never application UI.
 * Standard is the existing appearance and emits no override at all.
 * The chosen preset is stamped into the document snapshot (settings.document_typography),
 * so issued documents keep the typography they were generated with.
 */
enum DocumentTypography: string
{
    case Compact = 'compact';
    case Standard = 'standard';
    case Large = 'large';

    public const DEFAULT = self::Standard;

    public static function fromStored(mixed $value): self
    {
        return is_string($value) ? (self::tryFrom(trim(mb_strtolower($value))) ?? self::DEFAULT) : self::DEFAULT;
    }

    /**
     * Resolve from a document snapshot; snapshots without the key (frozen/historical) stay Standard.
     *
     * @param  array<string, mixed>  $snapshot
     */
    public static function fromSnapshot(array $snapshot): self
    {
        return self::fromStored(data_get($snapshot, 'settings.document_typography'));
    }

    public function label(): string
    {
        return match ($this) {
            self::Compact => 'Compact',
            self::Standard => 'Standard',
            self::Large => 'Large',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Compact => 'Smaller type; fits more on each page.',
            self::Standard => 'The default document appearance.',
            self::Large => 'Larger, easier-to-read type; may use more pages.',
        };
    }

    public function scale(): float
    {
        return match ($this) {
            self::Compact => 0.9,
            self::Standard => 1.0,
            self::Large => 1.15,
        };
    }

    /**
     * @return array<string, float>
     */
    public static function scales(): array
    {
        $scales = [];

        foreach (self::cases() as $case) {
            $scales[$case->value] = $case->scale();
        }

        return $scales;
    }

    /**
     * CSS override for the document container. Empty for Standard.
     *
     * @param  string  $selector  The document body element (not the application shell).
     * @param  string|null  $minHeight  Fixed page-fill height to compensate so zoom does not force extra pages.
     */
    public function css(string $selector, ?string $minHeight = null): string
    {
        if ($this === self::Standard) {
            return '';
        }

        $css = sprintf('%s { zoom: %s; ', $selector, rtrim(rtrim(number_format($this->scale(), 3, '.', ''), '0'), '.'));

        if ($minHeight !== null) {
            $css .= sprintf('min-height: calc(%s / %s); ', $minHeight, $this->scale());
        }

        return $css.'}';
    }
}
