<?php

namespace App\Ark\Dragon\ReviewEstimateNotes;

use App\Ark\Dragon\Assist\DragonAssistRequest;
use App\Ark\Dragon\ServiceAdvisor\ServiceAdvisorContextBuilder;
use App\Ark\Dragon\ServiceAdvisor\ServiceAdvisorFactPreservationCheck;
use App\Ark\Dragon\ServiceAdvisor\ServiceAdvisorField;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;

/**
 * After Dragon completes a review, enrich proposals with live original_hash /
 * applyable flags via fact preservation. Never writes RO fields.
 */
final class EnrichReviewEstimateNotesProposals
{
    public function __construct(
        private readonly ServiceAdvisorFactPreservationCheck $factCheck = new ServiceAdvisorFactPreservationCheck,
    ) {}

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    public function enrich(DragonAssistRequest $assist, array $result): array
    {
        $proposals = $result['proposals'] ?? [];
        if (! is_array($proposals) || $proposals === []) {
            $result['proposals'] = [];

            return $result;
        }

        $scopeConcernId = (int) (($assist->payload_json['scope']['concern_id'] ?? 0));
        $wholeRo = $scopeConcernId <= 0;

        if ($scopeConcernId > 0) {
            $proposals = array_values(array_filter(
                $proposals,
                static function ($row) use ($scopeConcernId): bool {
                    if (! is_array($row)) {
                        return false;
                    }
                    $field = (string) ($row['field'] ?? '');
                    if ($field === ServiceAdvisorField::VisitReason->value) {
                        return false;
                    }
                    if ($field === ServiceAdvisorField::LineNote->value) {
                        return true; // line concern checked against DB below
                    }

                    return (int) ($row['concern_id'] ?? 0) === $scopeConcernId;
                },
            ));
        }

        $repairOrder = RepairOrder::query()->find($assist->repair_order_id);
        if ($repairOrder === null) {
            $result['proposals'] = [];

            return $result;
        }

        $concernIds = [];
        $lineIds = [];
        foreach ($proposals as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (! empty($row['concern_id'])) {
                $concernIds[] = (int) $row['concern_id'];
            }
            if (! empty($row['line_id'])) {
                $lineIds[] = (int) $row['line_id'];
            }
        }

        $concerns = RepairOrderConcern::query()
            ->where('repair_order_id', $repairOrder->id)
            ->whereIn('id', array_values(array_unique($concernIds)))
            ->get()
            ->keyBy('id');

        $lines = RepairOrderLine::query()
            ->where('repair_order_id', $repairOrder->id)
            ->whereIn('id', array_values(array_unique($lineIds)))
            ->get()
            ->keyBy('id');

        $enriched = [];
        foreach ($proposals as $row) {
            if (! is_array($row)) {
                continue;
            }

            $fieldValue = (string) ($row['field'] ?? '');
            $proposed = trim((string) ($row['proposed_text'] ?? ''));
            if ($proposed === '' || ! in_array($fieldValue, ServiceAdvisorField::values(), true)) {
                $enriched[] = $this->reject($row, 'Proposal target is missing or invalid.');

                continue;
            }

            $field = ServiceAdvisorField::from($fieldValue);

            if ($field->isConcernNarrative()) {
                $enriched[] = $this->enrichConcern($row, $field, $proposed, $concerns);
            } elseif ($field === ServiceAdvisorField::VisitReason) {
                if (! $wholeRo) {
                    $enriched[] = $this->reject($row, 'Visit reason proposals are only for whole-estimate review.');

                    continue;
                }
                $enriched[] = $this->enrichVisitReason($row, $proposed, $repairOrder);
            } else {
                $enriched[] = $this->enrichLineNote($row, $proposed, $lines, $scopeConcernId);
            }
        }

        $result['proposals'] = $enriched;

        return $result;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  \Illuminate\Support\Collection<int, RepairOrderConcern>  $concerns
     * @return array<string, mixed>
     */
    private function enrichConcern(array $row, ServiceAdvisorField $field, string $proposed, $concerns): array
    {
        $concernId = (int) ($row['concern_id'] ?? 0);
        $concern = $concerns->get($concernId);
        if ($concern === null) {
            return $this->reject($row, 'Proposal target is missing or invalid.');
        }

        $originalText = $this->readConcernField($concern, $field);
        $hash = ServiceAdvisorContextBuilder::hashText($originalText);

        if ($originalText === '') {
            return [
                'concern_id' => $concernId,
                'line_id' => null,
                'field' => $field->value,
                'original_text' => $originalText,
                'proposed_text' => $proposed,
                'reason' => $row['reason'] ?? null,
                'original_hash' => $hash,
                'applyable' => false,
                'rejected_reason' => 'Source field is empty — author the note first, then rewrite.',
            ];
        }

        $check = $this->factCheck->check($originalText, $proposed);

        return [
            'concern_id' => $concernId,
            'line_id' => null,
            'field' => $field->value,
            'original_text' => $originalText,
            'proposed_text' => $proposed,
            'reason' => isset($row['reason']) ? (string) $row['reason'] : null,
            'original_hash' => $hash,
            'applyable' => $check['ok'],
            'rejected_reason' => $check['ok'] ? null : ($check['reason'] ?? 'Fact preservation failed.'),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function enrichVisitReason(array $row, string $proposed, RepairOrder $repairOrder): array
    {
        $originalText = trim((string) ($repairOrder->visit_reason ?? ''));
        $hash = ServiceAdvisorContextBuilder::hashText($originalText);

        if ($originalText === '') {
            return [
                'concern_id' => null,
                'line_id' => null,
                'field' => ServiceAdvisorField::VisitReason->value,
                'original_text' => $originalText,
                'proposed_text' => $proposed,
                'reason' => $row['reason'] ?? null,
                'original_hash' => $hash,
                'applyable' => false,
                'rejected_reason' => 'Source field is empty — author the note first, then rewrite.',
            ];
        }

        $check = $this->factCheck->check($originalText, $proposed);

        return [
            'concern_id' => null,
            'line_id' => null,
            'field' => ServiceAdvisorField::VisitReason->value,
            'original_text' => $originalText,
            'proposed_text' => $proposed,
            'reason' => isset($row['reason']) ? (string) $row['reason'] : null,
            'original_hash' => $hash,
            'applyable' => $check['ok'],
            'rejected_reason' => $check['ok'] ? null : ($check['reason'] ?? 'Fact preservation failed.'),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  \Illuminate\Support\Collection<int, RepairOrderLine>  $lines
     * @return array<string, mixed>
     */
    private function enrichLineNote(array $row, string $proposed, $lines, int $scopeConcernId): array
    {
        $lineId = (int) ($row['line_id'] ?? 0);
        $line = $lines->get($lineId);
        if ($line === null || $line->type !== RepairOrderLineType::Note) {
            return $this->reject($row, 'Proposal target is missing or invalid.');
        }

        if ($scopeConcernId > 0 && (int) $line->repair_order_concern_id !== $scopeConcernId) {
            return $this->reject($row, 'Line note is outside this concern scope.');
        }

        $originalText = trim((string) ($line->description ?? ''));
        $hash = ServiceAdvisorContextBuilder::hashText($originalText);

        if ($originalText === '') {
            return [
                'concern_id' => $line->repair_order_concern_id !== null ? (int) $line->repair_order_concern_id : null,
                'line_id' => $lineId,
                'field' => ServiceAdvisorField::LineNote->value,
                'original_text' => $originalText,
                'proposed_text' => $proposed,
                'reason' => $row['reason'] ?? null,
                'original_hash' => $hash,
                'applyable' => false,
                'rejected_reason' => 'Source field is empty — author the note first, then rewrite.',
            ];
        }

        $check = $this->factCheck->check($originalText, $proposed);

        return [
            'concern_id' => $line->repair_order_concern_id !== null ? (int) $line->repair_order_concern_id : null,
            'line_id' => $lineId,
            'field' => ServiceAdvisorField::LineNote->value,
            'original_text' => $originalText,
            'proposed_text' => $proposed,
            'reason' => isset($row['reason']) ? (string) $row['reason'] : null,
            'original_hash' => $hash,
            'applyable' => $check['ok'],
            'rejected_reason' => $check['ok'] ? null : ($check['reason'] ?? 'Fact preservation failed.'),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function reject(array $row, string $reason): array
    {
        return [
            ...$row,
            'original_hash' => null,
            'applyable' => false,
            'rejected_reason' => $reason,
        ];
    }

    private function readConcernField(RepairOrderConcern $concern, ServiceAdvisorField $field): string
    {
        return match ($field) {
            ServiceAdvisorField::Summary => (string) ($concern->summary ?? ''),
            ServiceAdvisorField::CustomerStates => (string) ($concern->customer_states ?? ''),
            ServiceAdvisorField::VerifiedFindings => (string) ($concern->verified_findings ?? ''),
            ServiceAdvisorField::DtcsSummary => (string) ($concern->dtcs_summary ?? ''),
            ServiceAdvisorField::Recommendation => (string) ($concern->recommendation ?? ''),
            default => '',
        };
    }
}
