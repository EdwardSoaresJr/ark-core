<?php

namespace App\Ark\Operations\Today\Surface;

final readonly class TodaySection
{
    public int $totalCount;

    /**
     * @param  list<TodayAction>  $actions  Visible (capped) rows
     * @param  array<string, mixed>|null  $panel
     */
    public function __construct(
        public string $key,
        public string $title,
        public array $actions,
        public ?array $panel = null,
        ?int $totalCount = null,
        public ?string $viewAllUrl = null,
        public ?string $viewAllLabel = null,
    ) {
        $this->totalCount = $totalCount ?? count($actions);
    }

    public function isEmpty(): bool
    {
        return $this->actions === [] && $this->panel === null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'title' => $this->title,
            'actions' => array_map(
                static fn (TodayAction $action): array => $action->toArray(),
                $this->actions,
            ),
            'panel' => $this->panel,
            'total_count' => $this->totalCount,
            'view_all_url' => $this->viewAllUrl,
            'view_all_label' => $this->viewAllLabel,
        ];
    }
}
