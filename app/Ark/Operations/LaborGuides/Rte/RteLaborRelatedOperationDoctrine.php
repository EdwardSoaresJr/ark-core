<?php

namespace App\Ark\Operations\LaborGuides\Rte;

use App\Ark\Operations\Labor\LaborDiagnosticLaborMatcher;

/**
 * Separates repair-related RTE operations from diagnostic-related operations.
 *
 * Repair-related operations auto-select with the primary job.
 * Diagnostic-related operations are suggested but not selected by default.
 */
final class RteLaborRelatedOperationDoctrine
{
    public function __construct(
        private readonly LaborDiagnosticLaborMatcher $matcher = new LaborDiagnosticLaborMatcher,
    ) {}

    public function isDiagnosticRelatedOperation(array $relatedRow): bool
    {
        return $this->matcher->isDiagnosticTestingDescription(
            (string) ($relatedRow['description'] ?? ''),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $relatedRows
     * @return array{
     *     repair_related: list<array<string, mixed>>,
     *     optional_diagnostic: list<array<string, mixed>>,
     * }
     */
    public function partition(array $relatedRows): array
    {
        $repairRelated = [];
        $optionalDiagnostic = [];

        foreach ($relatedRows as $row) {
            if ($this->isDiagnosticRelatedOperation($row)) {
                $optionalDiagnostic[] = $row;

                continue;
            }

            $repairRelated[] = $row;
        }

        return [
            'repair_related' => $repairRelated,
            'optional_diagnostic' => $optionalDiagnostic,
        ];
    }
}
