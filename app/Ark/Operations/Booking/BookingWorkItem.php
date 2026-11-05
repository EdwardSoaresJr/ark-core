<?php

namespace App\Ark\Operations\Booking;

/**
 * One thing the customer asked for in an online booking: a catalog service with its choices and
 * answers, a problem in their own words, or work the shop recommended before. A visit holds several.
 *
 * Selecting recommended work only says the customer wants to talk about it at this visit: it does
 * not approve an estimate, change the recommendation, or reopen the repair order it came from.
 */
final class BookingWorkItem
{
    public const SERVICE = 'service';

    public const CONCERN = 'concern';

    public const RECOMMENDATION = 'recommendation';

    public const DEFAULT_MINUTES = 60;

    /**
     * @param  list<array{label: string, answer: string}>  $answers
     */
    public function __construct(
        public readonly string $kind,
        public readonly string $name,
        public readonly ?string $option = null,
        public readonly ?string $reference = null,
        public readonly ?int $minutes = null,
        public readonly ?float $hours = null,
        public readonly ?string $details = null,
        public readonly array $answers = [],
        public readonly ?int $recommendationId = null,
        public readonly ?int $vehicleId = null,
        public readonly bool $requiresApproval = false,
    ) {}

    public static function recommendation(int $id, string $title, ?int $vehicleId = null, ?int $minutes = null): self
    {
        return new self(self::RECOMMENDATION, $title, reference: 'recommendation:'.$id, minutes: $minutes, recommendationId: $id, vehicleId: $vehicleId);
    }

    public function visitMinutes(): int
    {
        return max(15, min(480, $this->minutes ?? self::DEFAULT_MINUTES));
    }

    public function workloadHours(): float
    {
        return round($this->hours ?? $this->visitMinutes() / 60, 2);
    }

    public function label(): string
    {
        $option = trim((string) $this->option);

        return $option !== '' ? "{$this->name} · {$option}" : $this->name;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'kind' => $this->kind,
            'name' => $this->name,
            'option' => $this->option,
            'reference' => $this->reference,
            'duration_minutes' => $this->visitMinutes(),
            'labor_hours' => $this->workloadHours(),
            'details' => $this->details,
            'answers' => $this->answers ?: null,
            'recommendation_id' => $this->recommendationId,
            'vehicle_id' => $this->vehicleId,
            'requires_approval' => $this->requiresApproval ?: null,
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
