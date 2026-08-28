<?php

namespace App\Ark\Operations\Appointments;

/**
 * Canonical product entry for scheduling — /app/schedule.
 * Prefer this over /app/appointments/create for all new CTAs.
 */
final class ScheduleUrl
{
    /**
     * @param  array<string, mixed>  $context
     */
    public static function to(array $context = []): string
    {
        $params = array_filter([
            'customer' => self::intOrNull($context['customer'] ?? $context['customer_id'] ?? null),
            'vehicle' => self::intOrNull($context['vehicle'] ?? $context['vehicle_id'] ?? null),
            'repair_order' => self::intOrNull($context['repair_order'] ?? $context['repair_order_id'] ?? null),
            'conversation' => self::intOrNull($context['conversation'] ?? $context['conversation_id'] ?? null),
            'starts_at' => self::stringOrNull($context['starts_at'] ?? null),
            'ends_at' => self::stringOrNull($context['ends_at'] ?? null),
            'technician_user_id' => self::intOrNull($context['technician_user_id'] ?? null),
            'workstation_id' => self::intOrNull($context['workstation_id'] ?? null),
            'q' => self::stringOrNull($context['q'] ?? null),
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        return route('operations.schedule', $params);
    }

    private static function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $int = (int) $value;

        return $int > 0 ? $int : null;
    }

    private static function stringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string !== '' ? $string : null;
    }
}
