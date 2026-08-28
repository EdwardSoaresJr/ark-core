<?php

namespace App\Ark\Operations\Leads\Public;

final readonly class PublicSurfaceContext
{
    public function __construct(
        public string $page,
        public string $variant = '',
        public string $placement = 'embedded_form',
        public ?string $source = null,
        public ?string $target = null,
    ) {}

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return array_filter([
            'page' => $this->page,
            'variant' => $this->variant !== '' ? $this->variant : null,
            'placement' => $this->placement !== '' ? $this->placement : null,
            'source' => $this->source,
            'target' => $this->target,
        ], fn (?string $value): bool => $value !== null && $value !== '');
    }

    public static function commonProblemsIndex(string $variantKey): self
    {
        return new self('common-problems.index', $variantKey, 'embedded_form');
    }

    public static function commonProblemShow(string $slug): self
    {
        return new self('common-problems.'.$slug, 'contextual', 'embedded_form');
    }

    public static function contact(): self
    {
        return new self('contact', 'hub', 'embedded_form');
    }

    public static function book(): self
    {
        return new self('book', 'appointment_request', 'embedded_form');
    }

    public static function homepageCommonProblemLink(string $source, string $target): self
    {
        return new self('homepage', source: $source, target: $target);
    }

    /**
     * @param  array<string, mixed>|null  $input
     */
    public static function fromInput(?array $input): ?self
    {
        if ($input === null) {
            return null;
        }

        $page = trim((string) ($input['page'] ?? ''));

        if ($page === '') {
            return null;
        }

        return new self(
            page: $page,
            variant: trim((string) ($input['variant'] ?? '')),
            placement: trim((string) ($input['placement'] ?? '')),
            source: self::optionalString($input['source'] ?? null),
            target: self::optionalString($input['target'] ?? null),
        );
    }

    private static function optionalString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
