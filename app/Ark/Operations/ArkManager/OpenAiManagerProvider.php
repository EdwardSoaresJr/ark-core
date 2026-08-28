<?php

namespace App\Ark\Operations\ArkManager;

use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use Illuminate\Support\Facades\Http;

final class OpenAiManagerProvider implements AiManagerProvider
{
    public function __construct(
        private readonly ShopIntegrationCredentials $credentials,
        private readonly DeterministicAiManagerProvider $deterministicProvider,
    ) {}

    public function morningBrief(ArkManagerContext $context, string $recommendedFocus): ArkManagerMorningBrief
    {
        try {
            $decoded = $this->chatJson(
                system: $this->systemPrompt(),
                user: "Generate a morning brief for the advisor.\nRecommended focus (from deterministic rules, do not change): {$recommendedFocus}\n\nOperational context:\n".json_encode($context->toArray(), JSON_PRETTY_PRINT),
            );

            $paragraphs = $this->normalizeParagraphs($decoded['paragraphs'] ?? null);
            $focus = trim((string) ($decoded['recommended_focus'] ?? $recommendedFocus));

            if ($paragraphs === []) {
                return $this->deterministicProvider->morningBrief($context, $recommendedFocus);
            }

            return new ArkManagerMorningBrief(
                paragraphs: $paragraphs,
                recommendedFocus: $focus !== '' ? $focus : $recommendedFocus,
                source: 'openai',
                aiEnhanced: true,
            );
        } catch (\Throwable) {
            return $this->deterministicProvider->morningBrief($context, $recommendedFocus);
        }
    }

    public function explainRecommendation(ArkManagerContext $context, array $recommendation): ArkManagerRecommendationExplanation
    {
        try {
            $decoded = $this->chatJson(
                system: $this->systemPrompt(),
                user: "Explain this single recommendation in 2-4 sentences for an advisor. Do not reprioritize or add new actions.\n\nRecommendation:\n".json_encode($recommendation, JSON_PRETTY_PRINT)."\n\nShop context:\n".json_encode($context->toArray(), JSON_PRETTY_PRINT),
            );

            $explanation = trim((string) ($decoded['explanation'] ?? ''));

            if ($explanation === '') {
                return $this->deterministicProvider->explainRecommendation($context, $recommendation);
            }

            return new ArkManagerRecommendationExplanation(
                explanation: $explanation,
                source: 'openai',
                aiEnhanced: true,
            );
        } catch (\Throwable) {
            return $this->deterministicProvider->explainRecommendation($context, $recommendation);
        }
    }

    public function draftCommunication(ArkManagerContext $context, array $draftContext): ArkManagerCommunicationDraft
    {
        try {
            $decoded = $this->chatJson(
                system: $this->systemPrompt()."\n\nDraft communications only. Never claim a message was sent. Keep SMS under 320 characters when channel is sms.",
                user: "Draft a follow-up message. Human approval is required before sending.\n\nDraft request:\n".json_encode($draftContext, JSON_PRETTY_PRINT)."\n\nShop context:\n".json_encode($context->toArray(), JSON_PRETTY_PRINT),
            );

            $channel = strtolower(trim((string) ($draftContext['channel'] ?? 'sms')));
            $body = trim((string) ($decoded['body'] ?? ''));
            $subject = trim((string) ($decoded['subject'] ?? ''));

            if ($body === '') {
                return $this->deterministicProvider->draftCommunication($context, $draftContext);
            }

            return new ArkManagerCommunicationDraft(
                channel: $channel === 'email' ? 'email' : 'sms',
                subject: $subject,
                body: $body,
                source: 'openai',
                aiEnhanced: true,
            );
        } catch (\Throwable) {
            return $this->deterministicProvider->draftCommunication($context, $draftContext);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function chatJson(string $system, string $user): array
    {
        $response = Http::timeout(60)
            ->withToken((string) $this->credentials->openaiApiKey())
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->credentials->openaiAnalysisModel(),
                'temperature' => 0.3,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ],
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('ARK Manager request failed: '.$response->body());
        }

        $content = data_get($response->json(), 'choices.0.message.content');

        if (! is_string($content) || trim($content) === '') {
            throw new \RuntimeException('ARK Manager returned empty content.');
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You are ARK Manager — a read-only operational coaching layer for an independent auto repair shop advisor.

You ONLY explain operational truth provided in JSON context. You are NOT an authority and you do NOT mutate shop records.

Forbidden:
- Ranking or reordering recommendations (they are already ranked by deterministic rules)
- Changing flow scores, pipeline calculations, or observations
- Inventing repair orders, dollar amounts, counts, or customer facts not in context
- Claiming messages were sent or workflow state changed
- Generic motivational coaching disconnected from the provided data

Allowed:
- Morning brief narratives summarizing pipeline, flow constraint, commitments, and top recommendations
- Plain-language explanation of why a recommendation surfaced
- Draft SMS or email copy for human approval before sending

Return strict JSON only. Be concise, operational, and calm.
PROMPT;
    }

    /**
     * @return list<string>
     */
    private function normalizeParagraphs(mixed $paragraphs): array
    {
        if (! is_array($paragraphs)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (mixed $paragraph): string => trim((string) $paragraph),
            $paragraphs,
        ), fn (string $paragraph): bool => $paragraph !== ''));
    }
}
