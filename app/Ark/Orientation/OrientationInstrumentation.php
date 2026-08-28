<?php

namespace App\Ark\Orientation;

/**
 * Internal product metric — Time to Orientation (TTO).
 *
 * Seconds until a shop employee has enough context to act confidently.
 * Instrumentation hook for future observation; not advisor-facing UI.
 */
final class OrientationInstrumentation
{
    public const METRIC_NAME = 'time_to_orientation_seconds';

    /**
     * @param  array<string, mixed>  $context
     */
    public function recordSurfaceReady(string $surface, float $seconds, array $context = []): void
    {
        // Observation-only hook. Wire to logging/analytics when TTO notebook begins.
    }
}
