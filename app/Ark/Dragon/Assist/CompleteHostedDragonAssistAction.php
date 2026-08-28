<?php

namespace App\Ark\Dragon\Assist;

use App\Ark\Dragon\Agent\Contracts\DragonModelProvider;
use App\Ark\Dragon\Agent\DragonProviderUnavailable;
use App\Ark\Dragon\HistoricalRecall\HistoricalWorkRecallAssistResultSchema;
use App\Ark\Dragon\ReviewEstimateNotes\EnrichReviewEstimateNotesProposals;
use App\Ark\Dragon\ReviewEstimateNotes\ReviewEstimateNotesResultSchema;
use App\Ark\Dragon\ServiceAdvisor\CompleteHostedServiceAdvisorRewriteAction;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Completes Dragon assist tasks through ARK-hosted Dragon only.
 */
final class CompleteHostedDragonAssistAction
{
    public function __construct(
        private readonly DragonModelProvider $provider,
        private readonly CompleteHostedServiceAdvisorRewriteAction $rewrite,
        private readonly EnrichReviewEstimateNotesProposals $enrichReview,
        private readonly DragonAssistLifecycle $lifecycle = new DragonAssistLifecycle,
    ) {}

    public function execute(DragonAssistRequest $request): DragonAssistRequest
    {
        return match ($request->task_type) {
            DragonAssistTaskType::ServiceAdvisorRewrite => $this->rewrite->execute($request),
            DragonAssistTaskType::ReviewEstimateNotes => $this->completeStructured(
                $request,
                $this->reviewMessages($request),
                $this->reviewSchema(),
                fn (array $raw): array => $this->enrichReview->enrich(
                    $request,
                    ReviewEstimateNotesResultSchema::validate($raw),
                ),
            ),
            DragonAssistTaskType::HistoricalWorkRecallReview => $this->completeStructured(
                $request,
                $this->recallMessages($request),
                $this->recallSchema(),
                fn (array $raw): array => HistoricalWorkRecallAssistResultSchema::validate($raw),
            ),
        };
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $schema
     * @param  callable(array<string, mixed>): array<string, mixed>  $validate
     */
    private function completeStructured(
        DragonAssistRequest $request,
        array $messages,
        array $schema,
        callable $validate,
    ): DragonAssistRequest {
        $started = microtime(true);

        try {
            $raw = $this->provider->structured($messages, $schema);
            $result = $validate($raw);
        } catch (DragonProviderUnavailable $e) {
            return $this->lifecycle->markFailed($request, null, 'hosted_unavailable', $e->getMessage());
        } catch (ValidationException $e) {
            Log::warning('dragon.hosted_assist.invalid_schema', [
                'request_id' => $request->public_id,
                'task_type' => $request->task_type->value,
                'error' => $e->getMessage(),
            ]);

            return $this->lifecycle->markFailed(
                $request,
                null,
                'invalid_result_schema',
                'Dragon returned a result ARK could not use.',
            );
        } catch (Throwable $e) {
            Log::warning('dragon.hosted_assist.failed', [
                'request_id' => $request->public_id,
                'task_type' => $request->task_type->value,
                'error' => $e->getMessage(),
            ]);

            return $this->lifecycle->markFailed(
                $request,
                null,
                'hosted_assist_failed',
                'Dragon assist failed. Try again.',
            );
        }

        $result['transport'] = 'hosted';

        return $this->lifecycle->markCompleted(
            $request,
            null,
            $result,
            $this->provider->modelName(),
            $this->provider->providerName(),
            'hosted',
            (int) round((microtime(true) - $started) * 1000),
        );
    }

    /**
     * @return list<array{role: string, content: string}>
     */
    private function reviewMessages(DragonAssistRequest $request): array
    {
        return [
            [
                'role' => 'system',
                'content' => implode("\n", [
                    'You review estimate notes for an auto repair shop.',
                    'Return JSON matching the schema.',
                    'Critique gaps and inconsistencies. Optional rewrite proposals only.',
                    'Do not invent facts, prices, parts, labor, or urgency.',
                    'Nothing is applied until an advisor accepts a proposal.',
                ]),
            ],
            [
                'role' => 'user',
                'content' => json_encode($request->payload_json ?? [], JSON_UNESCAPED_UNICODE),
            ],
        ];
    }

    /**
     * @return list<array{role: string, content: string}>
     */
    private function recallMessages(DragonAssistRequest $request): array
    {
        return [
            [
                'role' => 'system',
                'content' => implode("\n", [
                    'You review Historical Work Recall for an advisor.',
                    'The deterministic tier and hours are already computed. Do not change them.',
                    'Return a short advisory summary and cautions only.',
                    'Do not invent labor hours or mutate saved work.',
                ]),
            ],
            [
                'role' => 'user',
                'content' => json_encode($request->payload_json ?? [], JSON_UNESCAPED_UNICODE),
            ],
        ];
    }

    /**
     * OpenAI json_schema strict mode requires every property key in `required`,
     * and optional values must be typed as union with null.
     *
     * @return array<string, mixed>
     */
    public static function reviewOpenAiSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'summary' => ['type' => 'string'],
                'strengths' => ['type' => 'array', 'items' => ['type' => 'string']],
                'gaps' => ['type' => 'array', 'items' => ['type' => 'string']],
                'inconsistencies' => ['type' => 'array', 'items' => ['type' => 'string']],
                'customer_readiness' => ['type' => ['string', 'null']],
                'suggested_actions' => ['type' => 'array', 'items' => ['type' => 'string']],
                'warnings' => ['type' => 'array', 'items' => ['type' => 'string']],
                'confidence' => ['type' => 'number'],
                'proposals' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'concern_id' => ['type' => ['integer', 'null']],
                            'line_id' => ['type' => ['integer', 'null']],
                            'field' => ['type' => 'string'],
                            'original_text' => ['type' => ['string', 'null']],
                            'proposed_text' => ['type' => 'string'],
                            'reason' => ['type' => ['string', 'null']],
                        ],
                        'required' => ['concern_id', 'line_id', 'field', 'original_text', 'proposed_text', 'reason'],
                    ],
                ],
            ],
            'required' => [
                'summary',
                'strengths',
                'gaps',
                'inconsistencies',
                'customer_readiness',
                'suggested_actions',
                'warnings',
                'confidence',
                'proposals',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reviewSchema(): array
    {
        return self::reviewOpenAiSchema();
    }

    /**
     * @return array<string, mixed>
     */
    public static function recallOpenAiSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'summary' => ['type' => 'string'],
                'confidence_comment' => ['type' => ['string', 'null']],
                'cautions' => ['type' => 'array', 'items' => ['type' => 'string']],
                'recommendation' => ['type' => ['string', 'null']],
                'review_book_time' => ['type' => 'boolean'],
                'sources' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
            'required' => [
                'summary',
                'confidence_comment',
                'cautions',
                'recommendation',
                'review_book_time',
                'sources',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function recallSchema(): array
    {
        return self::recallOpenAiSchema();
    }
}
