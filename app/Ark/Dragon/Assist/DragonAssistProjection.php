<?php

namespace App\Ark\Dragon\Assist;

/**
 * Advisor-facing projection of a Dragon Assist result.
 * Never mutates deterministic recall or RO authority.
 */
final class DragonAssistProjection
{
    /**
     * @return array<string, mixed>
     */
    public static function fromRequest(DragonAssistRequest $request): array
    {
        $result = $request->result?->result_json ?? [];
        $payload = $request->payload_json ?? [];

        $isRewrite = $request->task_type === DragonAssistTaskType::ServiceAdvisorRewrite;
        $isCritique = $request->task_type === DragonAssistTaskType::ReviewEstimateNotes;
        $proposal = isset($result['proposal']) ? (string) $result['proposal'] : null;
        $summary = isset($result['summary']) ? (string) $result['summary'] : null;

        $available = match (true) {
            $isRewrite => $request->status === DragonAssistStatus::Completed && filled($proposal),
            $isCritique => $request->status === DragonAssistStatus::Completed && filled($summary),
            default => $request->status === DragonAssistStatus::Completed && filled($summary),
        };

        return [
            'request_id' => $request->public_id,
            'status' => $request->status->value,
            'task_type' => $request->task_type->value,
            'summary' => $summary,
            'confidence_comment' => isset($result['confidence_comment']) ? (string) $result['confidence_comment'] : null,
            'cautions' => array_values($result['cautions'] ?? []),
            'recommendation' => isset($result['recommendation']) ? (string) $result['recommendation'] : null,
            'review_book_time' => (bool) ($result['review_book_time'] ?? false),
            'available' => $available,
            'proposal' => $proposal,
            'facts_preserved' => array_values($result['facts_preserved'] ?? []),
            'material_changes' => array_values($result['material_changes'] ?? []),
            'warnings' => array_values($result['warnings'] ?? []),
            'confidence' => isset($result['confidence']) ? (float) $result['confidence'] : null,
            'selected_field' => isset($payload['selected_field']) ? (string) $payload['selected_field'] : null,
            'selected_text' => isset($payload['selected_text']) ? (string) $payload['selected_text'] : null,
            'original_hash' => isset($payload['original_hash']) ? (string) $payload['original_hash'] : null,
            'mode' => isset($payload['mode']) ? (string) $payload['mode'] : null,
            'model_name' => $request->result?->model_name,
            'error' => $request->last_error,
            'strengths' => array_values($result['strengths'] ?? []),
            'gaps' => array_values($result['gaps'] ?? []),
            'inconsistencies' => array_values($result['inconsistencies'] ?? []),
            'customer_readiness' => isset($result['customer_readiness']) ? (string) $result['customer_readiness'] : null,
            'suggested_actions' => array_values($result['suggested_actions'] ?? []),
            'proposals' => array_values($result['proposals'] ?? []),
        ];
    }
}
