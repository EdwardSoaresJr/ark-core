<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Intake\IntakeConcernParser;

/**
 * Propose estimate concerns from visit_reason. Never writes visit_reason.
 */
final class ProposeConcernsFromVisitReason
{
    public function __construct(
        private readonly IntakeConcernParser $parser,
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
