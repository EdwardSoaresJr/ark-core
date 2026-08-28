<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Intake\IntakeConcernParser;
use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Propose estimate concerns from visit_reason. Never writes visit_reason.
 * OpenAI when configured; otherwise heuristics + IntakeConcernParser.
 */
final class ProposeConcernsFromVisitReason
{
    public function __construct(
        private readonly IntakeConcernParser $parser,
        private readonly ShopIntegrationCredentials $credentials,
    ) {}

    /**
     * @return list<array{summary: string, customer_states: string|null, scope_entry_kind: string}>
     */
    public function propose(string $visitReason, bool $useOpenAi = false): array
    {
        $visitReason = trim($visitReason);

        if ($visitReason === '') {
            return [];
        }

        if ($useOpenAi) {
            $fromModel = $this->proposeWithOpenAi($visitReason);

            if ($fromModel !== []) {
                return $fromModel;
            }
        }

        $heuristic = $this->proposeWithHeuristics($visitReason);

        if ($heuristic !== []) {
            return $heuristic;
        }

        return array_map(
            fn (array $row): array => [
                'summary' => $row['summary'],
                'customer_states' => null,
                'scope_entry_kind' => ScopeEntryKind::CustomerConcern->value,
            ],
            $this->parser->parse($visitReason),
        );
    }

    /**
     * @return list<array{summary: string, customer_states: string|null, scope_entry_kind: string}>
     */
    private function proposeWithOpenAi(string $visitReason): array
    {
        $apiKey = $this->credentials->openaiApiKey();

        if ($apiKey === null || $apiKey === '') {
            return [];
        }

        try {
            $response = Http::timeout(45)
                ->withToken($apiKey)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $this->credentials->openaiAnalysisModel(),
                    'temperature' => 0.2,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => <<<'PROMPT'
You help an auto repair advisor turn a customer's reason for visit into short estimate concern titles.

Return strict JSON: {"concerns":[{"summary":"...","kind":"customer_concern|customer_requested|diagnostic"}]}

Rules:
- summary is a short shop headline the advisor can approve as a RepairOrderConcern (max ~80 chars).
- Prefer splitting independent approvals (e.g. front brakes vs rear brakes) when the customer mentioned both.
- Do not invent vehicle details, prices, or parts not implied by the customer statement.
- kind: customer_concern for symptoms/noise; customer_requested for named services; diagnostic when investigation is the ask.
- 1–4 concerns. Never empty when the statement has any repair-related content.
PROMPT
                        ],
                        [
                            'role' => 'user',
                            'content' => $visitReason,
                        ],
                    ],
                ]);

            if (! $response->successful()) {
                return [];
            }

            $content = (string) data_get($response->json(), 'choices.0.message.content', '');
            $decoded = json_decode($content, true);

            if (! is_array($decoded)) {
                return [];
            }

            $rows = $decoded['concerns'] ?? null;

            if (! is_array($rows)) {
                return [];
            }

            $out = [];

            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $summary = Str::limit(trim((string) ($row['summary'] ?? '')), 255, '');

                if ($summary === '') {
                    continue;
                }

                $kind = ScopeEntryKind::tryFrom(trim((string) ($row['kind'] ?? '')))
                    ?? ScopeEntryKind::CustomerConcern;

                $out[] = [
                    'summary' => $summary,
                    'customer_states' => null,
                    'scope_entry_kind' => $kind->value,
                ];
            }

            return $out;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return list<array{summary: string, customer_states: string|null, scope_entry_kind: string}>
     */
    private function proposeWithHeuristics(string $visitReason): array
    {
        $lower = mb_strtolower($visitReason);
        $mentionsBrakes = str_contains($lower, 'brake');
        $mentionsFront = str_contains($lower, 'front');
        $mentionsRear = str_contains($lower, 'rear') || str_contains($lower, 'back');

        if ($mentionsBrakes && $mentionsFront && $mentionsRear) {
            return [
                [
                    'summary' => 'Front Brake Inspection',
                    'customer_states' => null,
                    'scope_entry_kind' => ScopeEntryKind::CustomerConcern->value,
                ],
                [
                    'summary' => 'Rear Brake Inspection',
                    'customer_states' => null,
                    'scope_entry_kind' => ScopeEntryKind::CustomerConcern->value,
                ],
            ];
        }

        return [];
    }
}
