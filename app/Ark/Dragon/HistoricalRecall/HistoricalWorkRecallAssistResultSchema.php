<?php

namespace App\Ark\Dragon\HistoricalRecall;

use App\Ark\Dragon\Assist\DragonAssistTaskType;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Validates Dragon advisory output for Historical Work Recall.
 * Rejects any attempt to change tier or invent authoritative labor.
 */
final class HistoricalWorkRecallAssistResultSchema
{
    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    public static function validate(array $result): array
    {
        $validator = Validator::make($result, [
            'summary' => ['required', 'string', 'max:2000'],
            'confidence_comment' => ['nullable', 'string', 'max:1000'],
            'cautions' => ['nullable', 'array', 'max:20'],
            'cautions.*' => ['string', 'max:500'],
            'recommendation' => ['nullable', 'string', 'max:1000'],
            'review_book_time' => ['nullable', 'boolean'],
            'sources' => ['nullable', 'array', 'max:50'],
            'sources.*.repair_action_id' => ['nullable', 'integer'],
            'sources.*.work_group_id' => ['nullable', 'integer'],
            'sources.*.repair_order_id' => ['nullable', 'integer'],
            'sources.*.reason' => ['nullable', 'string', 'max:300'],
            // Forbidden authority fields — must not appear.
            'tier' => ['prohibited'],
            'match_tier' => ['prohibited'],
            'median_hours' => ['prohibited'],
            'labor_hours' => ['prohibited'],
            'approved_hours' => ['prohibited'],
            'mutations' => ['prohibited'],
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        return [
            'summary' => trim((string) $result['summary']),
            'confidence_comment' => isset($result['confidence_comment']) ? trim((string) $result['confidence_comment']) : null,
            'cautions' => array_values(array_filter(array_map(
                static fn ($c) => is_string($c) ? trim($c) : null,
                $result['cautions'] ?? [],
            ))),
            'recommendation' => isset($result['recommendation']) ? trim((string) $result['recommendation']) : null,
            'review_book_time' => (bool) ($result['review_book_time'] ?? false),
            'sources' => array_values($result['sources'] ?? []),
            'task_type' => DragonAssistTaskType::HistoricalWorkRecallReview->value,
        ];
    }
}
