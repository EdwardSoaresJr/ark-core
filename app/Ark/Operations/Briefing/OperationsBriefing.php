<?php

namespace App\Ark\Operations\Briefing;

use Illuminate\Support\Carbon;

final readonly class OperationsBriefing
{
    /**
     * @param  list<array{label: string, value: string, hint: string|null}>  $yesterdaySummary
     * @param  list<BriefingSection>  $sections
     */
    public function __construct(
        public string $greeting,
        public string $narrativeIntro,
        public array $yesterdaySummary,
        public array $sections,
        public bool $hasAttentionItems,
        public string $emptyAttentionMessage,
        public Carbon $generatedAt,
        public string $briefingDateLabel,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'greeting' => $this->greeting,
            'narrative_intro' => $this->narrativeIntro,
            'yesterday_summary' => $this->yesterdaySummary,
            'sections' => array_map(
                static fn (BriefingSection $section): array => $section->toArray(),
                $this->sections,
            ),
            'has_attention_items' => $this->hasAttentionItems,
            'empty_attention_message' => $this->emptyAttentionMessage,
            'generated_at' => $this->generatedAt->toIso8601String(),
            'briefing_date_label' => $this->briefingDateLabel,
        ];
    }
}
