<?php

namespace App\Ark\Operations\Labor;

/**
 * Doctrine observation — possible duplicate diagnostic/testing labor on an RO.
 *
 * Advisory only. Does not mutate authority, hours, or customer presentation.
 */
final class LaborDiagnosticOverlapObservation
{
    public const ADVISOR_WARNING = 'Possible diagnostic overlap: this package includes diagnostic/testing labor, and this RO already has diagnostic labor. Confirm this test still needs to be performed.';

    public function __construct(
        private readonly LaborDiagnosticLaborMatcher $matcher = new LaborDiagnosticLaborMatcher,
    ) {}

    /**
     * @param  list<array{
     *     label: string,
     *     kind?: string,
     *     lab_id?: string|null,
     * }>  $packageLines
     * @param  list<array{
     *     id: int,
     *     description: string,
     *     repair_order_concern_id: int,
     *     concern_summary?: string|null,
     * }>  $existingLaborLines
     * @return array{
     *     advisor_summary: array{overlap_warning: string},
     *     advisor_detail: array{
     *         diagnostic_overlap: array{
     *             package_line: array{label: string, description: string},
     *             existing_line: array{
     *                 description: string,
     *                 concern_summary: string|null,
     *                 same_concern: bool,
     *             },
     *         },
     *     },
     *     engineering_detail: array<string, mixed>,
     * }|null
     */
    public function detect(
        array $packageLines,
        array $existingLaborLines,
        ?int $targetConcernId,
    ): ?array {
        $triggerLine = $this->firstDiagnosticPackageLine($packageLines);

        if ($triggerLine === null) {
            return null;
        }

        $existingLine = $this->firstExistingDiagnosticLine($existingLaborLines);

        if ($existingLine === null) {
            return null;
        }

        $sameConcern = $targetConcernId !== null
            && (int) $existingLine['repair_order_concern_id'] === $targetConcernId;

        return [
            'advisor_summary' => [
                'overlap_warning' => self::ADVISOR_WARNING,
            ],
            'advisor_detail' => [
                'diagnostic_overlap' => [
                    'package_line' => [
                        'label' => $this->detailLabel((string) $triggerLine['label']),
                        'description' => trim((string) $triggerLine['label']),
                    ],
                    'existing_line' => [
                        'description' => trim((string) $existingLine['description']),
                        'concern_summary' => filled($existingLine['concern_summary'] ?? null)
                            ? trim((string) $existingLine['concern_summary'])
                            : null,
                        'same_concern' => $sameConcern,
                    ],
                ],
            ],
            'engineering_detail' => [
                'target_concern_id' => $targetConcernId,
                'package_line' => $triggerLine,
                'existing_line' => $existingLine,
                'matcher' => 'LaborDiagnosticLaborMatcher',
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $packageLines
     * @return array<string, mixed>|null
     */
    private function firstDiagnosticPackageLine(array $packageLines): ?array
    {
        foreach ($packageLines as $line) {
            $kind = (string) ($line['kind'] ?? 'primary');
            $label = trim((string) ($line['label'] ?? ''));

            if ($label === '') {
                continue;
            }

            if ($kind === 'primary') {
                continue;
            }

            if ($this->matcher->isDiagnosticTestingDescription($label)) {
                return $line;
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $existingLaborLines
     * @return array<string, mixed>|null
     */
    private function firstExistingDiagnosticLine(array $existingLaborLines): ?array
    {
        foreach ($existingLaborLines as $line) {
            $description = trim((string) ($line['description'] ?? ''));

            if ($description === '') {
                continue;
            }

            if ($this->matcher->isDiagnosticTestingDescription($description)) {
                return $line;
            }
        }

        return null;
    }

    private function detailLabel(string $description): string
    {
        $normalized = strtoupper(trim($description));

        if (str_contains($normalized, 'COMBUSTION') && str_contains($normalized, 'COOL')) {
            return 'Combustion Test';
        }

        if (str_contains($normalized, 'PRESSURE') && str_contains($normalized, 'TEST')) {
            return 'Pressure Test';
        }

        if (str_contains($normalized, 'DIAGNOS')) {
            return 'Diagnostic';
        }

        return mb_convert_case(mb_strtolower(trim($description)), MB_CASE_TITLE, 'UTF-8');
    }
}
