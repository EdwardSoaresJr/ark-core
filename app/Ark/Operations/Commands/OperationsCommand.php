<?php

namespace App\Ark\Operations\Commands;

/**
 * Interaction craft only — navigate/create/search/ops entry points.
 * No new authority or business logic.
 */
final readonly class OperationsCommand
{
    /**
     * @param  list<string>  $keywords
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $group,
        public array $keywords = [],
        public ?string $permission = null,
        public ?string $url = null,
        public ?string $disabledReason = null,
    ) {}

    /**
     * @return array{
     *     id: string,
     *     title: string,
     *     group: string,
     *     keywords: list<string>,
     *     url: string|null,
     *     disabled_reason: string|null
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'group' => $this->group,
            'keywords' => $this->keywords,
            'url' => $this->url,
            'disabled_reason' => $this->disabledReason,
        ];
    }
}
