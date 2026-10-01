<?php

namespace App\Ark\Operations\ArkManager;

final readonly class ArkManagerMorningBrief
{
    /**
     * @param  list<string>  $paragraphs
     */
    public function __construct(
        public array $paragraphs,
        public string $recommendedFocus,
        public string $source,
        public bool $aiEnhanced,
    ) {}

    public function body(): string
    {
        return implode("\n\n", $this->paragraphs);
    }

    /**
     * @return array{
     *     paragraphs: list<string>,
     *     recommendedFocus: string,
     *     source: string,
     *     aiEnhanced: bool,
     * }
     */
    public function toCachePayload(): array
    {
        return [
            'paragraphs' => $this->paragraphs,
            'recommendedFocus' => $this->recommendedFocus,
            'source' => $this->source,
            'aiEnhanced' => $this->aiEnhanced,
        ];
    }

    /**
     * @param  array{
     *     paragraphs: list<string>,
     *     recommendedFocus: string,
     *     source: string,
     *     aiEnhanced: bool,
     * }  $payload
     */
    public static function fromCachePayload(array $payload): self
    {
        return new self(
            paragraphs: array_values($payload['paragraphs']),
            recommendedFocus: $payload['recommendedFocus'],
            source: $payload['source'],
            aiEnhanced: $payload['aiEnhanced'],
        );
    }
}
