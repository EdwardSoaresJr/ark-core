<?php

namespace App\Ark\Dragon\Agent\Tools;

use App\Ark\Dragon\Agent\DragonAgentTool;
use App\Ark\Dragon\ServiceAdvisor\ServiceAdvisorFactPreservationCheck;

final class EstimatesAdvisorContextTool implements DragonAgentTool
{
    public function __construct(
        private readonly EstimatesGetTool $estimates,
        private readonly ServiceAdvisorFactPreservationCheck $preservation,
    ) {}

    public function name(): string
    {
        return 'estimates.advisor_context';
    }

    public function description(): string
    {
        return 'Load structured estimate plus a rewrite or review check. Use when proposing customer-facing estimate language OR when reviewing an estimate for gaps (finding vs recommendation, missing measurements, unexplained diagnostic labor, missing companions this shop usually sells with the job). Returns a PROPOSAL / critique only. Never writes lines or prices. Do not second-guess pricing without authoritative pricing context.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'repair_order_id' => ['type' => 'string'],
                'draft_rewrite' => ['type' => 'string'],
                'source_note' => ['type' => 'string'],
            ],
            'required' => ['repair_order_id'],
        ];
    }

    public function invoke(array $arguments): array
    {
        $get = $this->estimates->invoke($arguments);
        if (($get['ok'] ?? false) !== true) {
            return $get;
        }

        $source = trim((string) ($arguments['source_note'] ?? ''));
        $draft = trim((string) ($arguments['draft_rewrite'] ?? ''));
        $check = null;
        if ($source !== '' && $draft !== '') {
            $check = $this->preservation->check($source, $draft);
        }

        return [
            'ok' => true,
            'read_only' => true,
            'proposal_only' => true,
            '_ark_categories' => ['estimate', 'advisor_language'],
            'estimate' => $get,
            'preservation_check' => $check,
            'instructions' => [
                'preserve_measurements',
                'preserve_side_location',
                'preserve_certainty',
                'do_not_invent_safety_or_urgency',
                'do_not_change_prices_or_lines',
                'return_language_proposal_only',
                'if_check_before_sending_needs_attention_tell_advisor_to_add_lines',
            ],
        ];
    }
}
