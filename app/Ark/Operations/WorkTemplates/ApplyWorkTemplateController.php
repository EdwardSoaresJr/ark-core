<?php

namespace App\Ark\Operations\WorkTemplates;

use App\Ark\Operations\RepairOrders\RecommendationIntent;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcurrency;
use App\Ark\Operations\RepairOrders\WorksheetMutationIdempotency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class ApplyWorkTemplateController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        ApplyWorkTemplateAction $apply,
        RepairOrderConcurrency $concurrency,
    ): RedirectResponse {
        $repairOrder->ensureOpenForEditing();
        $concurrency->guard($request, $repairOrder);

        $idempotencyKey = WorksheetMutationIdempotency::keyFrom($request);
        $cached = WorksheetMutationIdempotency::recall($repairOrder, 'work_template.apply', $idempotencyKey);
        if (is_array($cached) && isset($cached['work_group_id'])) {
            return redirect()
                ->route('operations.repair-orders.show', $repairOrder)
                ->withFragment('repair-action-'.$cached['work_group_id'])
                ->with('status', 'Saved work added.');
        }

        $data = $request->validate([
            'work_template_id' => ['required', 'integer', 'exists:work_templates,id'],
            'repair_order_concern_id' => [
                'nullable',
                'integer',
                'exists:repair_order_concerns,id',
            ],
            'historical_labor_hours' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'historical_match_tier' => ['nullable', 'string', 'in:exact,likely,possible,none'],
            'historical_labor_confirmed' => ['nullable', 'boolean'],
            'recommendation_intent' => ['nullable', Rule::enum(RecommendationIntent::class)],
            WorksheetMutationIdempotency::FIELD => ['nullable', 'string', 'max:80'],
        ]);

        $template = WorkTemplate::query()->findOrFail($data['work_template_id']);

        $concern = null;
        if (filled($data['repair_order_concern_id'] ?? null)) {
            $concern = RepairOrderConcern::query()->findOrFail($data['repair_order_concern_id']);
            abort_unless((int) $concern->repair_order_id === (int) $repairOrder->id, 404);
        }

        $laborOverride = $this->resolveLaborOverride($data);

        $newConcernIntent = $concern === null && filled($data['recommendation_intent'] ?? null)
            ? RecommendationIntent::from($data['recommendation_intent'])
            : null;

        $result = $apply->handle(
            $repairOrder,
            $template,
            $request->user(),
            $concern,
            $laborOverride,
            $newConcernIntent,
        );

        WorksheetMutationIdempotency::remember($repairOrder, 'work_template.apply', $idempotencyKey, [
            'work_group_id' => $result['work_group']->id,
        ]);

        return redirect()
            ->route('operations.repair-orders.show', $repairOrder)
            ->withFragment('repair-action-'.$result['work_group']->id)
            ->with('status', 'Saved work added.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{hours: float, tier: string}|null
     */
    private function resolveLaborOverride(array $data): ?array
    {
        if (! filled($data['historical_labor_hours'] ?? null)) {
            return null;
        }

        $tier = HistoricalMatchTier::tryFrom((string) ($data['historical_match_tier'] ?? ''));

        if ($tier === null || ! $tier->mayPrepareLabor()) {
            return null;
        }

        if ($tier->requiresReviewBeforeApply() && ! (bool) ($data['historical_labor_confirmed'] ?? false)) {
            return null;
        }

        return [
            'hours' => (float) $data['historical_labor_hours'],
            'tier' => $tier->value,
        ];
    }
}
