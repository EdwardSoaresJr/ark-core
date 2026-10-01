<?php

namespace App\Ark\ShopMemory\Providers;

use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\ShopMemory\ShopMemoryProviderCatalog;
use App\Ark\ShopMemory\Suggestion\Suggestion;
use App\Ark\ShopMemory\Suggestion\SuggestionContext;
use App\Ark\ShopMemory\Suggestion\SuggestionCorpus;
use App\Ark\ShopMemory\Suggestion\SuggestionIdentity;
use App\Ark\ShopMemory\Suggestion\SuggestionProvider;
use Illuminate\Support\Facades\DB;

/**
 * Problem-language from verified findings and technician note lines.
 */
final class TechnicianObservationProvider implements SuggestionProvider
{
    public const KEY = ShopMemoryProviderCatalog::TECHNICIAN_OBSERVATION;

    private const MIN_QUERY_LENGTH = 2;

    public function key(): string
    {
        return self::KEY;
    }

    public function name(): string
    {
        return 'Technician Observation';
    }

    public function description(): string
    {
        return 'Surfaces verified findings and technician notes as suggestions.';
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

        $findings = DB::table('repair_order_concerns')
            ->select(['verified_findings as text', 'id', 'repair_order_id'])
            ->whereNotNull('verified_findings')
            ->where('verified_findings', '!=', '')
            ->when($context->repairOrderId, fn ($q) => $q->where('repair_order_id', $context->repairOrderId))
            ->when($like, fn ($q) => $q->where('verified_findings', 'like', $like))
            ->orderByDesc('id')
            ->limit(40)
            ->get();

        foreach ($findings as $row) {
            $this->push($merged, (string) $row->text, 'verified_findings', (int) $row->id, false);
        }

        $notes = DB::table('repair_order_lines')
            ->select(['description as text', 'id', 'is_private', 'repair_order_id'])
            ->where('type', RepairOrderLineType::Note->value)
            ->whereNotNull('description')
            ->where('description', '!=', '')
            ->when($context->repairOrderId, fn ($q) => $q->where('repair_order_id', $context->repairOrderId))
            ->when($like, fn ($q) => $q->where('description', 'like', $like))
            ->orderByDesc('id')
            ->limit(40)
            ->get();

        foreach ($notes as $row) {
            $this->push($merged, (string) $row->text, 'note_line', (int) $row->id, (bool) $row->is_private);
        }

        return array_slice(array_values($merged), 0, max(8, $context->limit));
    }

    /**
     * @param  array<string, Suggestion>  $merged
     */
    private function push(array &$merged, string $text, string $source, int $sourceId, bool $isPrivate): void
    {
        $text = trim($text);

        if ($text === '' || mb_strlen($text) > 500) {
            return;
        }

        $id = SuggestionIdentity::make(self::KEY, $text);

        if (isset($merged[$id])) {
            return;
        }

        $merged[$id] = new Suggestion(
            id: $id,
            text: $text,
            providerKey: self::KEY,
            corpus: SuggestionCorpus::ProblemLanguage,
            relevance: 1.0,
            meta: [
                'source' => $source,
                'source_id' => $sourceId,
                'is_private' => $isPrivate,
            ],
        );
    }
}
