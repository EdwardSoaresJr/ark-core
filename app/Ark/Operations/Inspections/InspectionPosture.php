<?php

namespace App\Ark\Operations\Inspections;

/**
 * Disposable read-model payload for Inspection Posture on an RO.
 * Rebuildable from inspection authority — owns no persistence.
 */
final class InspectionPosture
{
    public const NOT_STARTED = 'not_started';

    public const IN_PROGRESS = 'in_progress';

    public const COMPLETE = 'complete';

    public const NEEDS_REVIEW = 'needs_review';

    /**
     * @param  self::NOT_STARTED|self::IN_PROGRESS|self::COMPLETE|self::NEEDS_REVIEW  $key
     */
    public function __construct(
        public readonly string $key,
        public readonly string $headline,
        public readonly ?string $detail,
        public readonly ?int $percentComplete,
        public readonly int $checked,
        public readonly int $total,
        public readonly int $remaining,
        public readonly int $attentionCount,
        public readonly bool $started,
        public readonly ?string $templateName,
    ) {}

    /** Single-line label for chips and existing `posture_label` consumers. */
    public function label(): string
    {
        if ($this->key === self::IN_PROGRESS && $this->percentComplete !== null) {
            return $this->headline.' · '.$this->percentComplete.'%';
        }

        return $this->headline;
    }

    /**
     * @return array{
     *     key: string,
     *     headline: string,
     *     detail: ?string,
     *     label: string,
     *     percent_complete: ?int,
     *     checked: int,
     *     total: int,
     *     remaining: int,
     *     attention_count: int,
     *     started: bool,
     *     template_name: ?string,
     * }
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'headline' => $this->headline,
            'detail' => $this->detail,
            'label' => $this->label(),
            'percent_complete' => $this->percentComplete,
            'checked' => $this->checked,
            'total' => $this->total,
            'remaining' => $this->remaining,
            'attention_count' => $this->attentionCount,
            'started' => $this->started,
            'template_name' => $this->templateName,
        ];
    }
}
