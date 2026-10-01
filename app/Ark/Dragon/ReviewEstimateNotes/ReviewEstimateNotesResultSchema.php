<?php

namespace App\Ark\Dragon\ReviewEstimateNotes;

use App\Ark\Dragon\Assist\DragonAssistTaskType;
use App\Ark\Dragon\ServiceAdvisor\ServiceAdvisorField;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Validates whole-estimate note critique + optional field rewrite proposals.
 * Proposals are advisory until an advisor explicitly Applies.
 *
 * Targets: concern narrative fields, visit_reason (RO), line_note (note lines).
 */
final class ReviewEstimateNotesResultSchema
{
    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    public static function validate(array $result): array
    {
        $validator = Validator::make($result, [
            'summary' => ['required', 'string', 'max:2000'],
            'strengths' => ['nullable', 'array', 'max:20'],
            'strengths.*' => ['string', 'max:500'],
            'gaps' => ['nullable', 'array', 'max:30'],
            'gaps.*' => ['string', 'max:500'],
            'inconsistencies' => ['nullable', 'array', 'max:30'],
            'inconsistencies.*' => ['string', 'max:500'],
            'customer_readiness' => ['nullable', 'string', 'max:1000'],
            'suggested_actions' => ['nullable', 'array', 'max:20'],
            'suggested_actions.*' => ['string', 'max:500'],
            'warnings' => ['nullable', 'array', 'max:20'],
            'warnings.*' => ['string', 'max:500'],
            'confidence' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'proposals' => ['nullable', 'array', 'max:40'],
            'proposals.*.concern_id' => ['nullable', 'integer', 'min:1'],
            'proposals.*.line_id' => ['nullable', 'integer', 'min:1'],
            'proposals.*.field' => ['required', 'string', Rule::in(ServiceAdvisorField::values())],
            'proposals.*.original_text' => ['nullable', 'string', 'max:8000'],
            'proposals.*.proposed_text' => ['required', 'string', 'max:8000'],
            'proposals.*.reason' => ['nullable', 'string', 'max:500'],
            'price' => ['prohibited'],
            'prices' => ['prohibited'],
            'parts' => ['prohibited'],
            'labor' => ['prohibited'],
            'labor_hours' => ['prohibited'],
            'mutations' => ['prohibited'],
            'status' => ['prohibited'],
            'disposition' => ['prohibited'],
            'rewrites' => ['prohibited'],
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $summary = trim((string) $result['summary']);
        if ($summary === '') {
            throw ValidationException::withMessages([
                'summary' => ['Critique summary must not be empty.'],
            ]);
        }

        $proposals = [];
        foreach ($result['proposals'] ?? [] as $index => $row) {
            if (! is_array($row)) {
                continue;
            }
            $proposed = trim((string) ($row['proposed_text'] ?? ''));
            if ($proposed === '') {
                continue;
            }

            $field = ServiceAdvisorField::from((string) $row['field']);
            $concernId = isset($row['concern_id']) ? (int) $row['concern_id'] : null;
            $lineId = isset($row['line_id']) ? (int) $row['line_id'] : null;

            if ($field->isConcernNarrative()) {
                if ($concernId === null || $concernId < 1) {
                    throw ValidationException::withMessages([
                        "proposals.{$index}.concern_id" => ['Concern narrative proposals require concern_id.'],
                    ]);
                }
                $lineId = null;
            } elseif ($field === ServiceAdvisorField::VisitReason) {
                $concernId = null;
                $lineId = null;
            } elseif ($field === ServiceAdvisorField::LineNote) {
                if ($lineId === null || $lineId < 1) {
                    throw ValidationException::withMessages([
                        "proposals.{$index}.line_id" => ['Line note proposals require line_id.'],
                    ]);
                }
            }

            $proposals[] = [
                'concern_id' => $concernId,
                'line_id' => $lineId,
                'field' => $field->value,
                'original_text' => isset($row['original_text']) ? trim((string) $row['original_text']) : null,
                'proposed_text' => $proposed,
                'reason' => isset($row['reason']) ? trim((string) $row['reason']) : null,
            ];
        }

        return [
            'summary' => $summary,
            'strengths' => self::stringList($result['strengths'] ?? []),
            'gaps' => self::stringList($result['gaps'] ?? []),
            'inconsistencies' => self::stringList($result['inconsistencies'] ?? []),
            'customer_readiness' => isset($result['customer_readiness'])
                ? trim((string) $result['customer_readiness'])
                : null,
            'suggested_actions' => self::stringList($result['suggested_actions'] ?? []),
            'warnings' => self::stringList($result['warnings'] ?? []),
            'confidence' => isset($result['confidence']) ? (float) $result['confidence'] : null,
            'proposals' => $proposals,
            'task_type' => DragonAssistTaskType::ReviewEstimateNotes->value,
        ];
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($item): ?string => is_string($item) ? trim($item) : null,
            $items,
        )));
    }
}
