<?php

namespace App\Ark\Operations\Attention;

use App\Ark\Operations\Observations\OperationalObservationType;

/**
 * One explainable contribution to an attention candidate score.
 */
final readonly class AttentionReason
{
    public function __construct(
        public string $label,
        public int $weight,
        public OperationalObservationType $observationType,
    ) {}
}
