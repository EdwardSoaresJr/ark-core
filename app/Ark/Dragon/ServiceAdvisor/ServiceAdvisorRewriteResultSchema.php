<?php

namespace App\Ark\Dragon\ServiceAdvisor;

use App\Ark\Dragon\Assist\DragonAssistTaskType;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Validates Dragon Service Advisor rewrite output.
 * Prohibits authority mutations (prices, parts, labor, status).
 */
final class ServiceAdvisorRewriteResultSchema
{
    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    public static function validate(array $result): array
    {
        $validator = Validator::make($result, [
            'proposal' => ['required', 'string', 'max:8000'],
            'facts_preserved' => ['nullable', 'array', 'max:40'],
            'facts_preserved.*' => ['string', 'max:500'],
            'material_changes' => ['nullable', 'array', 'max:40'],
            'material_changes.*' => ['string', 'max:500'],
            'warnings' => ['nullable', 'array', 'max:40'],
            'warnings.*' => ['string', 'max:500'],
            'confidence' => ['nullable', 'numeric', 'min:0', 'max:1'],
            // Forbidden authority / mutation fields.
            'price' => ['prohibited'],
            'prices' => ['prohibited'],
            'parts' => ['prohibited'],
            'labor' => ['prohibited'],
            'labor_hours' => ['prohibited'],
            'mutations' => ['prohibited'],
            'status' => ['prohibited'],
            'disposition' => ['prohibited'],
            'tier' => ['prohibited'],
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $proposal = trim((string) $result['proposal']);
        if ($proposal === '') {
            throw ValidationException::withMessages([
                'proposal' => ['Proposal must not be empty.'],
            ]);
        }

        return [
            'proposal' => $proposal,
            'facts_preserved' => self::stringList($result['facts_preserved'] ?? []),
            'material_changes' => self::stringList($result['material_changes'] ?? []),
            'warnings' => self::stringList($result['warnings'] ?? []),
            'confidence' => isset($result['confidence']) ? (float) $result['confidence'] : null,
            'task_type' => DragonAssistTaskType::ServiceAdvisorRewrite->value,
        ];
    }

    /**
     * @param  mixed  $items
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
