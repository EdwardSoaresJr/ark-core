<?php

namespace App\Ark\Operations\Documents;

use App\Ark\Operations\RepairOrders\LaborDescriptionPresentation;
use App\Ark\Operations\RepairOrders\RecommendationIntent;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\ScopeProductionStatus;

/**
 * Customer-document narrative from existing concern authority.
 */
final class CustomerDocumentScopeNarrative
{
    private const COMPLAINT_TITLE_CHARS = 72;

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    public function present(array $snapshot): array
    {
        $concerns = $snapshot['concerns'] ?? [];

        if (! is_array($concerns)) {
            return $snapshot;
        }

        foreach ($concerns as $index => $concern) {
            if (! is_array($concern)) {
                continue;
            }

            $concerns[$index] = $this->presentConcern($concern, $snapshot);
        }

        $snapshot['concerns'] = $concerns;

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $concern
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function presentConcern(array $concern, array $snapshot): array
    {
        $lines = $this->collectLines($concern);
        $noteTexts = $this->customerNoteTexts($lines);
        $findings = trim((string) ($concern['verified_findings'] ?? ''));
        $recommendation = trim((string) ($concern['recommendation'] ?? ''));

        if ($recommendation === '' && $findings !== '') {
            [$findings, $splitRecommendation] = $this->splitTrailingRecommendation($findings);
            if (is_string($splitRecommendation) && $splitRecommendation !== '') {
                $recommendation = $splitRecommendation;
            }
        }

        foreach ($noteTexts as $noteText) {
            if ($findings === '' && $recommendation === '') {
                [$noteFindings, $noteRecommendation] = $this->splitTrailingRecommendation($noteText);

                if (is_string($noteRecommendation) && $noteRecommendation !== '') {
                    $findings = $noteFindings;
                    $recommendation = $noteRecommendation;
                    continue;
                }
            }

            if ($findings === '') {
                $findings = $noteText;
                continue;
            }

            if (! str_contains($this->normalize($findings), $this->normalize($noteText))) {
                $findings .= "\n\n".$noteText;
            }
        }

        $concern['customer_title'] = $this->serviceTitle($concern, $snapshot, $lines);
        $concern['customer_findings'] = $findings !== '' ? $findings : null;
        $concern['customer_recommendation'] = $recommendation !== '' ? $recommendation : null;
        $concern['customer_dtcs'] = filled($concern['dtcs_summary'] ?? null)
            ? trim((string) $concern['dtcs_summary'])
            : null;
        $concern['customer_status_pills'] = $this->statusPills($concern);
        $concern['charge_lines'] = $this->chargeLines($lines);
        $chargeSubtotalCents = $this->chargeSubtotalCents($concern['charge_lines']);
        $concern['charge_subtotal_cents'] = $chargeSubtotalCents;
        $concern['charge_subtotal'] = $chargeSubtotalCents > 0 ? $this->formatCents($chargeSubtotalCents) : null;
        $concern['lines'] = $this->markPromotedNotes($concern['lines'] ?? []);

        $workGroups = $concern['work_groups'] ?? [];
        if (is_array($workGroups)) {
            foreach ($workGroups as $groupIndex => $workGroup) {
                if (! is_array($workGroup) || ! is_array($workGroup['lines'] ?? null)) {
                    continue;
                }

                $workGroup['lines'] = $this->markPromotedNotes($workGroup['lines']);
                $workGroups[$groupIndex] = $workGroup;
            }

            $concern['work_groups'] = $workGroups;
        }

        return $concern;
    }

    /**
     * @param  list<mixed>  $lines
     * @return list<mixed>
     */
    private function markPromotedNotes(array $lines): array
    {
        foreach ($lines as $index => $line) {
            if (! is_array($line) || ($line['type'] ?? '') !== RepairOrderLineType::Note->value) {
                continue;
            }

            $lines[$index]['customer_narrative'] = 'findings';
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $concern
     * @return list<array<string, mixed>>
     */
    private function collectLines(array $concern): array
    {
        $lines = [];

        foreach ($concern['work_groups'] ?? [] as $workGroup) {
            if (! is_array($workGroup)) {
                continue;
            }

            $groupTitle = trim((string) ($workGroup['title'] ?? ''));

            foreach ($workGroup['lines'] ?? [] as $line) {
                if (! is_array($line)) {
                    continue;
                }

                $line['work_group_title'] = $groupTitle;
                $lines[] = $line;
            }
        }

        foreach ($concern['lines'] ?? [] as $line) {
            if (! is_array($line)) {
                continue;
            }

            if (filled($line['repair_order_work_group_id'] ?? null)) {
                continue;
            }

            $lines[] = $line;
        }

        if ($lines === [] && is_array($concern['lines'] ?? null)) {
            foreach ($concern['lines'] as $line) {
                if (is_array($line)) {
                    $lines[] = $line;
                }
            }
        }

        return $lines;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<string>
     */
    private function customerNoteTexts(array $lines): array
    {
        $texts = [];

        foreach ($lines as $line) {
            if (($line['type'] ?? '') !== RepairOrderLineType::Note->value) {
                continue;
            }

            $text = trim((string) ($line['description'] ?? ''));

            if ($text !== '') {
                $texts[] = $text;
            }
        }

        return $texts;
    }

    /**
     * @param  array<string, mixed>  $concern
     * @param  array<string, mixed>  $snapshot
     * @param  list<array<string, mixed>>  $lines
     */
    private function serviceTitle(array $concern, array $snapshot, array $lines): string
    {
        $summary = trim((string) ($concern['summary'] ?? ''));

        if (! $this->isComplaintTitle($summary, $concern, $snapshot)) {
            return $summary !== '' ? $summary : $this->fallbackServiceTitle($concern, $lines);
        }

        $groupTitles = [];
        foreach ($concern['work_groups'] ?? [] as $workGroup) {
            if (! is_array($workGroup) || ($workGroup['lines'] ?? []) === []) {
                continue;
            }

            $title = trim((string) ($workGroup['title'] ?? ''));
            if ($title !== '' && ! $this->isComplaintTitle($title, $concern, $snapshot)) {
                $groupTitles[] = $title;
            }
        }

        if (count($groupTitles) === 1) {
            return CustomerRepairActionIncludes::groupHeading($groupTitles[0]);
        }

        return $this->fallbackServiceTitle($concern, $lines);
    }

    /**
     * @param  array<string, mixed>  $concern
     * @param  list<array<string, mixed>>  $lines
     */
    private function fallbackServiceTitle(array $concern, array $lines): string
    {
        $intent = RecommendationIntent::fromStored((string) ($concern['recommendation_intent'] ?? ''));
        $laborDescriptions = [];

        foreach ($lines as $line) {
            if (($line['type'] ?? '') !== RepairOrderLineType::Labor->value) {
                continue;
            }

            $description = trim((string) ($line['description'] ?? ''));
            if ($description !== '' && mb_strlen($description) <= self::COMPLAINT_TITLE_CHARS) {
                $laborDescriptions[] = $description;
            }
        }

        if ($laborDescriptions !== []) {
            return implode(' · ', $laborDescriptions);
        }

        if ($intent === RecommendationIntent::Diagnostic) {
            return 'Diagnostic Testing';
        }

        return $intent->customerLabel();
    }

    /**
     * @param  array<string, mixed>  $concern
     * @param  array<string, mixed>  $snapshot
     */
    private function isComplaintTitle(string $summary, array $concern, array $snapshot): bool
    {
        $normalized = $this->normalize($summary);

        if ($normalized === '') {
            return true;
        }

        $candidates = [
            (string) data_get($snapshot, 'intake.visit_reason', ''),
            (string) data_get($snapshot, 'intake.concern_summary', ''),
        ];

        foreach ($candidates as $candidate) {
            $other = $this->normalize($candidate);
            if ($other === '') {
                continue;
            }

            if ($this->isNearDuplicateVisitNarrative($normalized, $other)) {
                return true;
            }
        }

        return false;
    }

    private function isNearDuplicateVisitNarrative(string $summary, string $visit): bool
    {
        if ($summary === $visit) {
            return true;
        }

        if (mb_strlen($summary) < self::COMPLAINT_TITLE_CHARS && mb_strlen($visit) < self::COMPLAINT_TITLE_CHARS) {
            return false;
        }

        similar_text($summary, $visit, $percent);

        return $percent >= 80.0;
    }

    /**
     * @param  array<string, mixed>  $concern
     * @return list<string>
     */
    private function statusPills(array $concern): array
    {
        $pills = [];
        $dispositionLabel = trim((string) ($concern['disposition_label'] ?? ''));
        $disposition = (string) ($concern['disposition'] ?? '');

        if ($disposition !== '' && $disposition !== 'draft' && $dispositionLabel !== '') {
            $pills[] = $dispositionLabel;
        }

        $production = ScopeProductionStatus::fromStored(
            isset($concern['production_status']) ? (string) $concern['production_status'] : null,
        );

        if ($production === ScopeProductionStatus::Completed) {
            $pills[] = $production->label();
        }

        return $pills;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<array{kind: string, description: string, quantity_label: string|null, amount: string, amount_cents?: int, type: string}>
     */
    private function chargeLines(array $lines): array
    {
        $charges = [];
        $lastGroup = null;

        foreach ($lines as $line) {
            $type = RepairOrderLineType::tryFrom((string) ($line['type'] ?? ''));

            if ($type === null || $type === RepairOrderLineType::Note) {
                continue;
            }

            if (($line['customer_narrative'] ?? null) === 'findings') {
                continue;
            }

            $groupTitle = trim((string) ($line['work_group_title'] ?? ''));
            if ($groupTitle !== '' && $groupTitle !== $lastGroup) {
                $charges[] = [
                    'kind' => 'heading',
                    'description' => CustomerRepairActionIncludes::groupHeading($groupTitle),
                    'quantity_label' => null,
                    'amount' => '',
                    'type' => 'heading',
                ];
                $lastGroup = $groupTitle;
            }

            $description = trim((string) ($line['customer_part_description'] ?? $line['description'] ?? ''));

            if ($description === '') {
                $description = $groupTitle;
            }

            if ($description === '') {
                $description = $type->documentLabel();
            }

            $detailBits = array_values(array_filter([
                filled($line['customer_part_number'] ?? null) ? (string) $line['customer_part_number'] : null,
                filled($line['customer_part_vendor'] ?? null) ? (string) $line['customer_part_vendor'] : null,
            ], fn (?string $bit): bool => $bit !== null && $bit !== ''));

            $charges[] = [
                'kind' => 'item',
                'description' => $description,
                'detail' => $detailBits !== [] ? implode(' · ', $detailBits) : null,
                'quantity_label' => $this->quantityLabel($line, $type),
                'amount' => (string) ($line['subtotal'] ?? $line['total'] ?? ''),
                'amount_cents' => $this->lineAmountCents($line),
                'type' => $type->value,
            ];
        }

        return $charges;
    }

    /**
     * Visible labor and parts on the work card — shop supplies and tax belong in document totals.
     *
     * @param  list<array<string, mixed>>  $charges
     */
    private function chargeSubtotalCents(array $charges): int
    {
        $cents = 0;

        foreach ($charges as $charge) {
            if (($charge['kind'] ?? 'item') !== 'item') {
                continue;
            }

            $cents += (int) ($charge['amount_cents'] ?? $this->parseAmountCents((string) ($charge['amount'] ?? '')));
        }

        return $cents;
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private function lineAmountCents(array $line): int
    {
        if (array_key_exists('subtotal_cents', $line)) {
            return (int) $line['subtotal_cents'];
        }

        return $this->parseAmountCents((string) ($line['subtotal'] ?? $line['total'] ?? ''));
    }

    private function parseAmountCents(string $amount): int
    {
        if (preg_match('/-?\$?([0-9,]+(?:\.[0-9]+)?)/', $amount, $matches) !== 1) {
            return 0;
        }

        return (int) round(((float) str_replace(',', '', $matches[1])) * 100);
    }

    private function formatCents(int $cents): string
    {
        return '$'.number_format($cents / 100, 2, '.', ',');
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private function quantityLabel(array $line, RepairOrderLineType $type): ?string
    {
        $quantity = (float) ($line['quantity'] ?? 0);

        if ($type === RepairOrderLineType::Labor) {
            return LaborDescriptionPresentation::formatHours($quantity).' hr';
        }

        if ($quantity <= 0) {
            return null;
        }

        $formatted = LaborDescriptionPresentation::formatHours($quantity);

        if ($formatted === '1') {
            return null;
        }

        return $formatted;
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    private function splitTrailingRecommendation(string $text): array
    {
        $pattern = '/(?:\n+|(?<=[.!?])\s+)(Recommend(?:ation)?[:\s].+)$/is';

        if (preg_match($pattern, $text, $matches) !== 1) {
            return [$text, null];
        }

        $recommendation = trim($matches[1]);
        $findings = trim(str_replace($matches[0], '', $text));

        if ($findings === '' || $recommendation === '') {
            return [$text, null];
        }

        return [$findings, $recommendation];
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value) ?? $value));
    }
}
