<?php

namespace App\Ark\Orientation;

/**
 * Operational orientation for a repair order — derived, never editable.
 *
 * @phpstan-type OrientationPayload array{
 *     situation: string,
 *     progress_stopped_because: string,
 *     owner: string,
 *     owner_signal: string,
 *     pressure_label: string,
 *     suggested_follow_up_lines: list<string>,
 *     confidence_items: list<string>,
 *     next_action: ?string,
 * }
 */
final readonly class OperationalOrientation
{
    /**
     * @param  list<string>  $suggestedFollowUpLines
     * @param  list<string>  $confidenceItems
     */
    public function __construct(
        public string $situation,
        public string $progressStoppedBecause,
        public string $owner,
        public string $ownerSignal,
        public string $pressureLabel,
        public array $suggestedFollowUpLines,
        public array $confidenceItems,
    ) {}

    public function primaryNextAction(): ?string
    {
        $actionable = [];

        foreach ($this->suggestedFollowUpLines as $line) {
            if (! str_starts_with($line, 'No action')) {
                $actionable[] = $line;
            }
        }

        if ($actionable !== []) {
            return $actionable[array_key_last($actionable)];
        }

        return $this->suggestedFollowUpLines[0] ?? null;
    }

    /**
     * @return OrientationPayload|array<string, mixed>
     */
    public function present(OrientationDensity $density): array
    {
        $nextAction = $this->primaryNextAction();

        $base = [
            'situation' => $this->situation,
            'progress_stopped_because' => $this->progressStoppedBecause,
            'owner' => $this->owner,
            'owner_signal' => $this->ownerSignal,
            'pressure_label' => $this->pressureLabel,
            'next_action' => $nextAction,
        ];

        return match ($density) {
            OrientationDensity::Full => array_merge($base, [
                'suggested_follow_up_lines' => $this->suggestedFollowUpLines,
                'confidence_items' => $this->confidenceItems,
            ]),
            OrientationDensity::Standard => array_merge($base, [
                'suggested_follow_up_lines' => array_slice($this->suggestedFollowUpLines, 0, 2),
                'confidence_items' => $this->confidenceItems,
            ]),
            OrientationDensity::Compact,
            OrientationDensity::Interrupt => $base,
        };
    }
}
