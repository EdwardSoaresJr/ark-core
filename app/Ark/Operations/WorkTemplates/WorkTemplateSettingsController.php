<?php

namespace App\Ark\Operations\WorkTemplates;

use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RecommendationIntent;
use App\Ark\Operations\RepairOrders\RecommendationPriority;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Quiet Settings CRUD for Rapid Work Templates.
 */
final class WorkTemplateSettingsController
{
    public function store(Request $request): RedirectResponse
    {
        $payload = $this->validated($request);

        DB::transaction(function () use ($payload): void {
            $position = ((int) WorkTemplate::query()->max('position')) + 1;
            $template = WorkTemplate::query()->create([
                'title' => $payload['title'],
                'description' => $payload['description'] ?? null,
                'internal_note' => $payload['internal_note'] ?? null,
                'recommendation_intent' => $payload['recommendation_intent'],
                'recommendation_priority' => $payload['recommendation_priority'],
                'information_only' => $payload['information_only'],
                'position' => max(1, $position),
            ]);

            $this->syncLines($template, $payload['lines']);
        });

        return redirect()
            ->route('operations.settings.shop.edit', ['section' => 'workflow', 'workflow-tab' => 'saved-work'])
            ->with('status', 'Saved work created.');
    }

    public function update(Request $request, WorkTemplate $workTemplate): RedirectResponse
    {
        $payload = $this->validated($request);

        DB::transaction(function () use ($workTemplate, $payload): void {
            $workTemplate->update([
                'title' => $payload['title'],
                'description' => $payload['description'] ?? null,
                'internal_note' => $payload['internal_note'] ?? null,
                'recommendation_intent' => $payload['recommendation_intent'],
                'recommendation_priority' => $payload['recommendation_priority'],
                'information_only' => $payload['information_only'],
            ]);

            $workTemplate->lines()->delete();
            $this->syncLines($workTemplate, $payload['lines']);
        });

        return redirect()
            ->route('operations.settings.shop.edit', ['section' => 'workflow', 'workflow-tab' => 'saved-work'])
            ->with('status', 'Saved work updated.');
    }

    public function duplicate(WorkTemplate $workTemplate): RedirectResponse
    {
        $workTemplate->loadMissing('lines');

        DB::transaction(function () use ($workTemplate): void {
            $copy = WorkTemplate::query()->create([
                'title' => $workTemplate->title.' (copy)',
                'description' => $workTemplate->description,
                'internal_note' => $workTemplate->internal_note,
                'recommendation_intent' => $workTemplate->recommendationIntent()?->value,
                'recommendation_priority' => $workTemplate->recommendationPriority()?->value,
                'information_only' => $workTemplate->isInformationOnly(),
                'position' => ((int) WorkTemplate::query()->max('position')) + 1,
            ]);

            foreach ($workTemplate->lines as $line) {
                $copy->lines()->create([
                    'type' => $line->type,
                    'description' => $line->description,
                    'quantity' => $line->quantity,
                    'unit_price_cents' => $line->unit_price_cents,
                    'part_cost_cents' => $line->part_cost_cents,
                    'position' => $line->position,
                ]);
            }
        });

        return redirect()
            ->route('operations.settings.shop.edit', ['section' => 'workflow', 'workflow-tab' => 'saved-work'])
            ->with('status', 'Saved work duplicated.');
    }

    public function retire(WorkTemplate $workTemplate): RedirectResponse
    {
        $workTemplate->retire();

        return redirect()
            ->route('operations.settings.shop.edit', ['section' => 'workflow', 'workflow-tab' => 'saved-work'])
            ->with('status', 'Saved work retired.');
    }

    public function restore(WorkTemplate $workTemplate): RedirectResponse
    {
        $workTemplate->restoreFromRetirement();

        return redirect()
            ->route('operations.settings.shop.edit', ['section' => 'workflow', 'workflow-tab' => 'saved-work'])
            ->with('status', 'Saved work restored.');
    }

    /**
     * @return array{title: string, description: ?string, internal_note: ?string, recommendation_intent: string, lines: list<array<string, mixed>>}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:1000'],
            'internal_note' => ['nullable', 'string', 'max:2000'],
            'recommendation_intent' => ['nullable', 'string', 'max:32'],
            'recommendation_posture' => ['nullable', Rule::in(['must_do', 'should_do', 'could_do', 'information_only'])],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.type' => ['required', Rule::in([
                RepairOrderLineType::Labor->value,
                RepairOrderLineType::Part->value,
                RepairOrderLineType::Fee->value,
                RepairOrderLineType::Note->value,
            ])],
            'lines.*.description' => ['required', 'string', 'max:2000'],
            'lines.*.quantity' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'lines.*.unit_price' => ['nullable', 'string', 'max:32'],
            'lines.*.part_cost' => ['nullable', 'string', 'max:32'],
        ]);

        $assignment = $this->assignment($data);

        return [
            'title' => trim($data['title']),
            'description' => filled($data['description'] ?? null) ? trim((string) $data['description']) : null,
            'internal_note' => filled($data['internal_note'] ?? null) ? trim((string) $data['internal_note']) : null,
            'recommendation_intent' => $assignment['recommendation_intent'],
            'recommendation_priority' => $assignment['recommendation_priority'],
            'information_only' => $assignment['information_only'],
            'lines' => array_values($data['lines']),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{recommendation_intent: ?string, recommendation_priority: ?string, information_only: bool}
     */
    private function assignment(array $data): array
    {
        $semantic = RecommendationIntent::trySemantic(isset($data['recommendation_intent']) ? (string) $data['recommendation_intent'] : null);
        $posture = isset($data['recommendation_posture']) ? (string) $data['recommendation_posture'] : '';

        if ($posture === '' && $semantic === null) {
            $legacy = isset($data['recommendation_intent']) ? (string) $data['recommendation_intent'] : '';
            if (RecommendationPriority::isHistoricalInformationOnly($legacy)) {
                return [
                    'recommendation_intent' => null,
                    'recommendation_priority' => null,
                    'information_only' => true,
                ];
            }

            $priority = RecommendationPriority::fromHistoricalIntent($legacy);

            return [
                'recommendation_intent' => null,
                'recommendation_priority' => $priority?->value,
                'information_only' => false,
            ];
        }

        if ($posture === 'information_only') {
            return [
                'recommendation_intent' => $semantic?->value,
                'recommendation_priority' => null,
                'information_only' => true,
            ];
        }

        return [
            'recommendation_intent' => $semantic?->value ?? RecommendationIntent::Maintenance->value,
            'recommendation_priority' => $posture !== '' ? $posture : null,
            'information_only' => false,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function syncLines(WorkTemplate $template, array $lines): void
    {
        foreach ($lines as $index => $row) {
            $type = RepairOrderLineType::from($row['type']);
            $quantity = isset($row['quantity']) && $row['quantity'] !== ''
                ? (string) $row['quantity']
                : ($type->isLabor() ? '1.00' : '1.00');

            $unitPriceCents = null;
            if (in_array($type, [RepairOrderLineType::Fee, RepairOrderLineType::Part, RepairOrderLineType::Labor], true)) {
                $unitPriceCents = $this->dollarsToCents($row['unit_price'] ?? null);
            }

            $partCostCents = $type->isPart()
                ? $this->dollarsToCents($row['part_cost'] ?? null)
                : null;

            $template->lines()->create([
                'type' => $type,
                'description' => trim((string) $row['description']),
                'quantity' => $quantity,
                'unit_price_cents' => $unitPriceCents,
                'part_cost_cents' => $partCostCents,
                'position' => $index + 1,
            ]);
        }
    }

    private function dollarsToCents(mixed $raw): ?int
    {
        if ($raw === null) {
            return null;
        }

        $normalized = preg_replace('/[^0-9.]/', '', str_replace(',', '', trim((string) $raw))) ?? '';

        if ($normalized === '' || ! is_numeric($normalized)) {
            return null;
        }

        return (int) round(((float) $normalized) * 100);
    }
}
