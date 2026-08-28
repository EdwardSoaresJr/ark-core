<?php

namespace App\Ark\ShopMemory\Providers;

use App\Ark\ShopMemory\ShopMemoryProviderCatalog;
use App\Ark\ShopMemory\Suggestion\Suggestion;
use App\Ark\ShopMemory\Suggestion\SuggestionContext;
use App\Ark\ShopMemory\Suggestion\SuggestionCorpus;
use App\Ark\ShopMemory\Suggestion\SuggestionIdentity;
use App\Ark\ShopMemory\Suggestion\SuggestionProvider;
use Illuminate\Support\Facades\DB;

/**
 * Problem-language from inspection item notes and labels.
 */
final class InspectionFindingProvider implements SuggestionProvider
{
    public const KEY = ShopMemoryProviderCatalog::INSPECTION_FINDING;

    private const MIN_QUERY_LENGTH = 2;

    public function key(): string
    {
        return self::KEY;
    }

    public function name(): string
    {
        return 'Inspection Finding';
    }

    public function description(): string
    {
        return 'Surfaces inspection notes and labels as suggestions.';
    }

    public function version(): string
    {
        return '1';
    }

    public function corpora(): array
    {
        return [SuggestionCorpus::ProblemLanguage];
    }

    public function suggest(SuggestionContext $context): array
    {
        $needle = trim($context->query);
        $roScoped = $context->repairOrderId !== null && $needle === '';

        if (! $roScoped && mb_strlen($needle) < self::MIN_QUERY_LENGTH) {
            return [];
        }

        $like = $needle !== '' ? '%'.addcslashes($needle, '%_\\').'%' : null;
        $merged = [];

        $query = DB::table('inspection_items')
            ->join('inspections', 'inspections.id', '=', 'inspection_items.inspection_id')
            ->select([
                'inspection_items.id',
                'inspection_items.label',
                'inspection_items.notes',
                'inspection_items.observed_state',
                'inspections.repair_order_id',
            ])
            ->when($context->repairOrderId, fn ($q) => $q->where('inspections.repair_order_id', $context->repairOrderId))
            ->where(function ($q) use ($like): void {
                if ($like === null) {
                    $q->whereNotNull('inspection_items.notes')
                        ->where('inspection_items.notes', '!=', '');

                    return;
                }

                $q->where(function ($inner) use ($like): void {
                    $inner->where('inspection_items.notes', 'like', $like)
                        ->orWhere('inspection_items.label', 'like', $like);
                });
            })
            ->orderByDesc('inspection_items.id')
            ->limit(40);

        foreach ($query->get() as $row) {
            $notes = trim((string) $row->notes);
            $label = trim((string) $row->label);
            $text = $notes !== '' ? $notes : $label;

            if ($text === '' || mb_strlen($text) > 500) {
                continue;
            }

            $id = SuggestionIdentity::make(self::KEY, $text);

            if (isset($merged[$id])) {
                continue;
            }

            $merged[$id] = new Suggestion(
                id: $id,
                text: $text,
                providerKey: self::KEY,
                corpus: SuggestionCorpus::ProblemLanguage,
                relevance: in_array((string) $row->observed_state, ['needs_attention', 'fail', 'monitor'], true) ? 2.0 : 1.0,
                meta: [
                    'source' => $notes !== '' ? 'inspection_notes' : 'inspection_label',
                    'source_id' => (int) $row->id,
                    'observed_state' => (string) $row->observed_state,
                    'label' => $label,
                ],
            );
        }

        return array_slice(array_values($merged), 0, max(8, $context->limit));
    }
}
