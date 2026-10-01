<?php

namespace App\Ark\Operations\Today\Surface;

final readonly class TodayAction
{
    public function __construct(
        public string $key,
        public string $title,
        public string $ownerLabel,
        public string $url,
        public ?string $whyYouLabel = null,
        public ?string $expectedOutcome = null,
        public ?string $effortLabel = null,
        public ?string $reason = null,
        public ?string $detail = null,
        public ?string $recommendationKind = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'title' => $this->title,
            'owner_label' => $this->ownerLabel,
            'url' => $this->url,
            'why_you_label' => $this->whyYouLabel,
            'expected_outcome' => $this->expectedOutcome,
            'effort_label' => $this->effortLabel,
            'reason' => $this->reason,
            'detail' => $this->detail,
            'recommendation_kind' => $this->recommendationKind,
        ];
    }
}
