<?php

namespace App\Ark\Growth\Journey;

use Illuminate\Support\Carbon;

final class JourneyEvidenceFactory
{
    public static function item(
        JourneyEvidenceSource $source,
        Carbon $occurredAt,
        string $summary,
        ?string $detail = null,
        ?int $sourceId = null,
    ): JourneyEvidenceItem {
        return new JourneyEvidenceItem(
            source: $source,
            summary: $summary,
            occurredAt: $occurredAt,
            detail: $detail,
            sourceId: $sourceId,
        );
    }
}
