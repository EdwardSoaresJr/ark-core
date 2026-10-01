<?php

namespace App\Ark\Operations\Leads;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

final class LeadIngressContext
{
    public function __construct(
        public readonly ?string $ip,
        public readonly ?string $userAgent,
        public readonly ?string $referrer,
        public readonly ?Carbon $formRenderedAt,
        public readonly Carbon $submittedAt,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $renderedAt = self::parseFormRenderedAt($request->input('form_rendered_at'));

        return new self(
            ip: $request->ip(),
            userAgent: self::truncate($request->userAgent(), 1024),
            referrer: self::truncate($request->headers->get('referer'), 2048),
            formRenderedAt: $renderedAt,
            submittedAt: now(),
        );
    }

    public function submitDurationSeconds(): ?float
    {
        if ($this->formRenderedAt === null) {
            return null;
        }

        return (float) $this->formRenderedAt->diffInMilliseconds($this->submittedAt) / 1000;
    }

    /**
     * @return array<string, mixed>
     */
    public function observationAttributes(): array
    {
        return [
            'ingress_ip' => $this->ip,
            'ingress_user_agent' => $this->userAgent,
            'ingress_referrer' => $this->referrer,
            'form_rendered_at' => $this->formRenderedAt,
        ];
    }

    private static function parseFormRenderedAt(mixed $value): ?Carbon
    {
        if (! is_numeric($value)) {
            return null;
        }

        $timestamp = (int) $value;

        if ($timestamp <= 0) {
            return null;
        }

        $renderedAt = Carbon::createFromTimestamp($timestamp);

        if ($renderedAt->isFuture() || $renderedAt->lt(now()->subDay())) {
            return null;
        }

        return $renderedAt;
    }

    private static function truncate(?string $value, int $max): ?string
    {
        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return null;
        }

        return mb_substr($trimmed, 0, $max);
    }
}
