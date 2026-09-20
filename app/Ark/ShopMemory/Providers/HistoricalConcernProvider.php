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
 * Problem-language corpus from historical concern summaries / customer states.
 */
final class HistoricalConcernProvider implements SuggestionProvider
{
    public const KEY = ShopMemoryProviderCatalog::HISTORICAL_CONCERN;

    private const MIN_QUERY_LENGTH = 2;

    public function key(): string
    {
        return self::KEY;
    }

    public function name(): string
    {
        return 'Historical Concern';
    }

    public function description(): string
    {
        return 'Reuses past concern summaries and customer problem language.';
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

        if (mb_strlen($needle) < self::MIN_QUERY_LENGTH) {
            return [];
        }

        $like = '%'.addcslashes($needle, '%_\\').'%';
        $fetchLimit = max(24, $context->limit * 3);
        $merged = [];

        $fromSummary = DB::table('repair_order_concerns')
            ->select(['summary as text', DB::raw('COUNT(*) as usage_count'), DB::raw('MAX(id) as last_id')])
            ->whereNotNull('summary')
            ->where('summary', '!=', '')
            ->where('summary', 'like', $like)
            ->groupBy('summary')
            ->orderByDesc('usage_count')
            ->limit($fetchLimit)
            ->get();

        foreach ($fromSummary as $row) {
            $this->push($merged, (string) $row->text, (int) $row->usage_count, 'summary', (int) $row->last_id);
        }

        $fromCustomer = DB::table('repair_order_concerns')
            ->select(['customer_states as text', DB::raw('COUNT(*) as usage_count'), DB::raw('MAX(id) as last_id')])
            ->whereNotNull('customer_states')
            ->where('customer_states', '!=', '')
            ->where('customer_states', 'like', $like)
            ->groupBy('customer_states')
            ->orderByDesc('usage_count')
            ->limit($fetchLimit)
            ->get();

        foreach ($fromCustomer as $row) {
            $this->push($merged, (string) $row->text, (int) $row->usage_count, 'customer_states', (int) $row->last_id);
        }

        return array_values($merged);
    }

    /**
     * @param  array<string, Suggestion>  $merged
     */
    private function push(array &$merged, string $text, int $usage, string $source, int $lastId): void
    {
        $text = trim($text);

        if ($text === '' || mb_strlen($text) > 500) {
            return;
        }

        $id = SuggestionIdentity::make(self::KEY, $text);
        $existing = $merged[$id] ?? null;

        if ($existing !== null && $existing->relevance >= $usage) {
            return;
        }

        $merged[$id] = new Suggestion(
            id: $id,
            text: $text,
            providerKey: self::KEY,
            corpus: SuggestionCorpus::ProblemLanguage,
            relevance: (float) $usage,
            meta: [
                'usage_count' => $usage,
                'source' => $source,
                'last_id' => $lastId,
            ],
        );
    }
}
