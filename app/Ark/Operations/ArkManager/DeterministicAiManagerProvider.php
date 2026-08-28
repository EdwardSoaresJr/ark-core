<?php

namespace App\Ark\Operations\ArkManager;

final class DeterministicAiManagerProvider implements AiManagerProvider
{
    public function __construct(
        private readonly ArkManagerContextBuilder $contextBuilder,
    ) {}

    public function morningBrief(ArkManagerContext $context, string $recommendedFocus): ArkManagerMorningBrief
    {
        $paragraphs = [];

        $paragraphs[] = 'Good morning.';

        $revenueLabel = $this->contextBuilder->revenueInFlightLabel($context);
        if ($revenueLabel !== null) {
            $paragraphs[] = "Revenue in flight: {$revenueLabel}";
        }

        if ($context->flowConstraint !== null) {
            $constraint = $context->flowConstraint;
            $paragraphs[] = implode("\n", array_filter([
                'Primary constraint:',
                (string) ($constraint['label'] ?? 'Operational bottleneck'),
                (string) ($constraint['headline'] ?? ''),
            ]));
        }

        $pipelineLines = $this->emphasizedPipelineLines($context);
        if ($pipelineLines !== []) {
            $paragraphs[] = "Pipeline pressure:\n".implode("\n", $pipelineLines);
        }

        $commitmentLine = $this->commitmentSummary($context);
        if ($commitmentLine !== null) {
            $paragraphs[] = $commitmentLine;
        }

        $topRecommendations = $this->topRecommendationLines($context);
        if ($topRecommendations !== []) {
            $paragraphs[] = "Top recommended actions:\n".implode("\n", $topRecommendations);
        }

        return new ArkManagerMorningBrief(
            paragraphs: $paragraphs,
            recommendedFocus: $recommendedFocus,
            source: 'deterministic',
            aiEnhanced: false,
        );
    }

    public function explainRecommendation(ArkManagerContext $context, array $recommendation): ArkManagerRecommendationExplanation
    {
        $customer = trim((string) ($recommendation['customer_name'] ?? 'This customer'));
        $title = trim((string) ($recommendation['title'] ?? 'This repair order'));
        $impact = trim((string) ($recommendation['impact_label'] ?? ''));
        $action = trim((string) ($recommendation['suggested_action'] ?? ''));
        $reasons = array_values(array_filter(
            $recommendation['why_reasons'] ?? [],
            fn (mixed $reason): bool => is_string($reason) && trim($reason) !== '',
        ));

        $reasonText = $reasons === []
            ? 'Operational signals surfaced this repair order for attention.'
            : implode(' ', array_map(
                fn (string $reason): string => rtrim($reason, '.').'.',
                $reasons,
            ));

        $impactSentence = $impact !== '' ? " Impact: {$impact}." : '';
        $actionSentence = $action !== '' ? " Suggested next step: {$action}." : '';

        $explanation = "{$customer} — {$title}. {$reasonText}{$impactSentence}{$actionSentence}";

        return new ArkManagerRecommendationExplanation(
            explanation: trim(preg_replace('/\s+/', ' ', $explanation) ?? $explanation),
            source: 'deterministic',
            aiEnhanced: false,
        );
    }

    public function draftCommunication(ArkManagerContext $context, array $draftContext): ArkManagerCommunicationDraft
    {
        $channel = strtolower(trim((string) ($draftContext['channel'] ?? 'sms')));
        $customer = trim((string) ($draftContext['customer_name'] ?? 'there'));
        $purpose = strtolower(trim((string) ($draftContext['purpose'] ?? 'general_follow_up')));
        $shopName = trim((string) ($draftContext['shop_name'] ?? 'the shop'));

        if ($channel === 'email') {
            return $this->emailDraft($customer, $purpose, $shopName, $draftContext);
        }

        return $this->smsDraft($customer, $purpose, $shopName, $draftContext);
    }

    /**
     * @return list<string>
     */
    private function emphasizedPipelineLines(ArkManagerContext $context): array
    {
        $lines = [];

        foreach ($context->pipeline as $metric) {
            if (! ($metric['emphasized'] ?? false)) {
                continue;
            }

            $label = (string) ($metric['label'] ?? 'Metric');
            $amount = (string) ($metric['amount_label'] ?? '');
            $count = (int) ($metric['repair_order_count'] ?? 0);
            $roLabel = $count === 1 ? '1 repair order' : "{$count} repair orders";

            $lines[] = "{$label}: {$amount} across {$roLabel}.";
        }

        return $lines;
    }

    private function commitmentSummary(ArkManagerContext $context): ?string
    {
        if ($context->commitments === []) {
            return null;
        }

        $overdue = count(array_filter(
            $context->commitments,
            fn (array $row): bool => (bool) ($row['is_overdue'] ?? false),
        ));

        $dueToday = count($context->commitments);

        if ($overdue > 0) {
            return "{$overdue} overdue commitment".($overdue === 1 ? '' : 's')." and {$dueToday} due today need follow-through.";
        }

        return "{$dueToday} commitment".($dueToday === 1 ? '' : 's').' due today.';
    }

    /**
     * @return list<string>
     */
    private function topRecommendationLines(ArkManagerContext $context): array
    {
        $lines = [];

        foreach (array_slice($context->recommendations, 0, 3) as $recommendation) {
            $customer = trim((string) ($recommendation['customer_name'] ?? 'Customer'));
            $action = trim((string) ($recommendation['suggested_action'] ?? $recommendation['title'] ?? 'Review'));
            $lines[] = "• {$customer}: {$action}";
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $draftContext
     */
    private function smsDraft(string $customer, string $purpose, string $shopName, array $draftContext): ArkManagerCommunicationDraft
    {
        $firstName = explode(' ', $customer)[0] ?: 'there';

        $body = match ($purpose) {
            'approval_follow_up' => "Hi {$firstName}, this is {$shopName}. I wanted to follow up on your estimate and see if you had any questions or if you're ready to move forward. Reply here or call us anytime.",
            'pickup_follow_up' => "Hi {$firstName}, this is {$shopName}. Your vehicle is ready for pickup. Let us know when you'd like to come by or if you need anything before you arrive.",
            default => "Hi {$firstName}, this is {$shopName}. Following up on your repair order — let me know if you have questions or need anything from us today.",
        };

        return new ArkManagerCommunicationDraft(
            channel: 'sms',
            subject: '',
            body: $body,
            source: 'deterministic',
            aiEnhanced: false,
        );
    }

    /**
     * @param  array<string, mixed>  $draftContext
     */
    private function emailDraft(string $customer, string $purpose, string $shopName, array $draftContext): ArkManagerCommunicationDraft
    {
        $firstName = explode(' ', $customer)[0] ?: 'there';

        $subject = match ($purpose) {
            'approval_follow_up' => 'Following up on your estimate',
            'pickup_follow_up' => 'Your vehicle is ready for pickup',
            default => 'Following up on your repair order',
        };

        $body = match ($purpose) {
            'approval_follow_up' => "Hi {$firstName},\n\nThis is {$shopName}. I wanted to check in after you reviewed your estimate and see if you had any questions or would like to approve the recommended work.\n\nReply to this email or call the shop and we'll help you move forward.\n\nThank you,\n{$shopName}",
            'pickup_follow_up' => "Hi {$firstName},\n\nYour vehicle is ready for pickup at {$shopName}. Let us know when you plan to stop by or if you need anything before you arrive.\n\nThank you,\n{$shopName}",
            default => "Hi {$firstName},\n\nFollowing up from {$shopName} on your repair order. If you have questions or need an update, reply here and we'll get back to you.\n\nThank you,\n{$shopName}",
        };

        return new ArkManagerCommunicationDraft(
            channel: 'email',
            subject: $subject,
            body: $body,
            source: 'deterministic',
            aiEnhanced: false,
        );
    }
}
