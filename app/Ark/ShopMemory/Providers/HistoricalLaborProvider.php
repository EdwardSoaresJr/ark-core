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
 * Shop Memory — historical labor language.
 *
 * Observes work-language corpus only. Never coordinates with other providers.
 */
final class HistoricalLaborProvider implements SuggestionProvider
{
    public const KEY = ShopMemoryProviderCatalog::HISTORICAL_LABOR;

    public const VERSION = '1';

    private const MIN_QUERY_LENGTH = 2;

    public function key(): string
    {
        return self::KEY;
    }

    public function name(): string
    {
        return 'Historical Labor';
    }

    public function description(): string
    {
        return 'Reuses past labor line descriptions from this shop.';
    }

    public function version(): string
    {
        return self::VERSION;
    }

    public function corpora(): array
    {
        return [SuggestionCorpus::WorkLanguage];
    }

    public function suggest(SuggestionContext $context): array
    {
        $needle = trim($context->query);

        if (mb_strlen($needle) < self::MIN_QUERY_LENGTH) {
            return [];
        }

        $like = '%'.addcslashes($needle, '%_\\').'%';
        $fetchLimit = max(24, $context->limit * 3);

        $rows = DB::table('repair_order_lines')
            ->select([
                'description',
                DB::raw('COUNT(*) as usage_count'),
                DB::raw('MAX(id) as last_line_id'),
            ])
            ->where('type', RepairOrderLineType::Labor->value)
            ->whereNotNull('description')
            ->where('description', '!=', '')
            ->where('description', 'like', $like)
            ->groupBy('description')
            ->orderByDesc('usage_count')
            ->orderBy('description')
            ->limit($fetchLimit)
            ->get();

        $suggestions = [];

        foreach ($rows as $row) {
            $text = trim((string) $row->description);

            if ($text === '') {
                continue;
            }

            $usage = (int) $row->usage_count;

            $suggestions[] = new Suggestion(
                id: SuggestionIdentity::make(self::KEY, $text),
                text: $text,
                providerKey: self::KEY,
                corpus: SuggestionCorpus::WorkLanguage,
                relevance: (float) $usage,
                meta: [
                    'usage_count' => $usage,
                    'source' => 'repair_order_lines',
                    'last_line_id' => (int) $row->last_line_id,
                ],
            );
        }

        return $suggestions;
    }
}
