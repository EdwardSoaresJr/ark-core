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
 * Problem-language from visit_reason — no VisitReason model.
 */
final class CustomerIntakeProvider implements SuggestionProvider
{
    public const KEY = ShopMemoryProviderCatalog::CUSTOMER_INTAKE;

    private const MIN_QUERY_LENGTH = 2;

    public function key(): string
    {
        return self::KEY;
    }

    public function name(): string
    {
        return 'Customer Intake';
    }

    public function description(): string
    {
        return 'Surfaces visit-reason language as concern suggestions.';
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
        $merged = [];

        if ($context->repairOrderId !== null) {
            $visitReason = DB::table('repair_orders')
                ->where('id', $context->repairOrderId)
                ->value('visit_reason');

            $text = trim((string) $visitReason);

            if ($text !== '' && mb_strlen($text) <= 500) {
                $id = SuggestionIdentity::make(self::KEY, $text);
                $merged[$id] = new Suggestion(
                    id: $id,
                    text: $text,
                    providerKey: self::KEY,
                    corpus: SuggestionCorpus::ProblemLanguage,
                    relevance: 3.0,
                    meta: [
                        'source' => 'current_visit_reason',
                        'repair_order_id' => $context->repairOrderId,
                    ],
                );
            }
        }

        $needle = trim($context->query);

        if (mb_strlen($needle) >= self::MIN_QUERY_LENGTH) {
            $like = '%'.addcslashes($needle, '%_\\').'%';

            $rows = DB::table('repair_orders')
                ->select(['visit_reason as text', DB::raw('COUNT(*) as usage_count'), DB::raw('MAX(id) as last_id')])
                ->whereNotNull('visit_reason')
                ->where('visit_reason', '!=', '')
                ->where('visit_reason', 'like', $like)
                ->groupBy('visit_reason')
                ->orderByDesc('usage_count')
                ->limit(24)
                ->get();

            foreach ($rows as $row) {
                $text = trim((string) $row->text);

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
                    relevance: (float) $row->usage_count,
                    meta: [
                        'source' => 'historical_visit_reason',
                        'usage_count' => (int) $row->usage_count,
                        'last_id' => (int) $row->last_id,
                    ],
                );
            }
        }

        return array_values($merged);
    }
}
