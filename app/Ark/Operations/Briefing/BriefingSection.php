<?php

namespace App\Ark\Operations\Briefing;

final readonly class BriefingSection
{
    /**
     * @param  list<BriefingItem>  $items
     */
    public function __construct(
        public string $key,
        public string $title,
        public array $items,
        public ?string $intro = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'title' => $this->title,
            'intro' => $this->intro,
            'items' => array_map(
                static fn (BriefingItem $item): array => $item->toArray(),
                $this->items,
            ),
        ];
    }
}
