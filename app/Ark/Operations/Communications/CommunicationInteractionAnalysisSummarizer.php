<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use App\Ark\Operations\Telephony\CallSessionAnalysisProjection;
use Illuminate\Support\Facades\Http;

final class CommunicationInteractionAnalysisSummarizer
{
    public function __construct(
        private readonly ShopIntegrationCredentials $credentials,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function summarize(string $interactionKind, array $context, string $transcript): array
    {
        $subject = match ($interactionKind) {
            'sms' => 'SMS text threads',
            default => 'phone calls',
        };

        $threadShape = (string) ($context['thread_shape'] ?? '');
        $oneSidedSmsGuidance = match ($threadShape) {
            'outbound_only' => <<<'GUIDANCE'

This is an outbound-only SMS thread (estimate link, payment link, or advisor follow-up with no customer reply that day). Score the advisor on clarity, ownership, and professionalism in what they sent. Use customer_intent to describe what the advisor was trying to accomplish. Use outcome for whether the message set a clear next step (estimate sent, callback offered, appointment proposed). coaching_improvements should focus on message quality: plain language, next step, warmth, and missed opportunity to invite questions or booking — not on things the customer never said.
GUIDANCE,
            'inbound_only' => <<<'GUIDANCE'

This is inbound-only SMS (customer texted; no staff reply recorded that day). Treat this as an operational failure to respond. Set follow_up_needed true. Score ownership_score low (1-2) because the shop left the customer waiting. Use customer_intent from what the customer asked. Use outcome to state that no advisor reply was recorded. coaching_priority should be medium or high when the customer asked a question, sent photos, requested an estimate, or showed urgency. coaching_improvements must name the missed follow-up (reply, callback, next step) — this is advisor coaching for a dropped text, not null scores. suggested_reply must be a ready-to-send customer text answering their message — never meta commentary about what the advisor should have done.
GUIDANCE,
            'two_way' => <<<'GUIDANCE'

This is a two-way SMS thread. Set follow_up_needed false when the shop already answered the customer's latest message and no new customer question remains open. When follow_up_needed is true, suggested_reply must be the exact SMS to send next — written to the customer in plain shop language, never meta commentary about advisor performance.
GUIDANCE,
            default => '',
        };

        $response = Http::timeout(120)
            ->withToken((string) $this->credentials->openaiApiKey())
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->credentials->openaiAnalysisModel(),
                'temperature' => 0.2,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => <<<PROMPT
You analyze {$subject} for an independent auto repair shop owner reviewing advisor performance. Return strict JSON with these keys:

summary (2-3 operational sentences),
customer_intent (one sentence),
outcome (one sentence: resolved, callback promised, estimate discussed, appointment set, complaint, voicemail-only, unclear),
sentiment (positive | neutral | concerned | frustrated),
follow_up_needed (boolean),
follow_up_notes (string or null — internal owner coaching on why follow-up matters; never customer-facing copy),
suggested_reply (string or null — when follow_up_needed is true, write the exact SMS the advisor should send to the customer in plain shop language; null when no reply is needed),

missed_upsell (boolean — true when the customer raised additional concern, deferred work, maintenance, inspection finding, or convenience item and the advisor did not offer next step, inspection, estimate, or booking),
missed_upsell_notes (string or null — specific missed revenue or service opportunity),

empathy_score (integer 1-5 — how well the advisor acknowledged emotion, urgency, cost anxiety, and confusion),
empathy_notes (string or null — evidence for empathy score),
ownership_score (integer 1-5 — did the advisor own the interaction with clear next steps and confidence),
clarity_score (integer 1-5 — plain language, no rambling, customer left understanding what happens next),
appointment_captured (boolean or null — when scheduling was relevant, was an appointment or drop-off time secured),
appointment_notes (string or null),

coaching_priority (none | low | medium | high — high when empathy <=2, missed_upsell true, frustrated sentiment, or vague next steps),
coaching_notes (string or null — concise owner coaching paragraph),
coaching_strengths (array of up to 3 short strings),
coaching_improvements (array of up to 3 specific actionable strings for the advisor),
topics (array of up to 5 short strings).

Score only when a staff member exchanged messages with the customer; for attachment-only threads with no readable text, use null scores and explain in coaching_notes. Outbound-only estimate or payment-link sends should receive ownership and clarity scores. Inbound-only threads with no advisor reply should receive low ownership scores and coaching on the missed follow-up.
Be factual. Do not invent vehicle details, prices, or repairs not in the transcript.
{$oneSidedSmsGuidance}
PROMPT,
                    ],
                    [
                        'role' => 'user',
                        'content' => "Interaction context:\n".json_encode($context, JSON_PRETTY_PRINT)."\n\nTranscript:\n".$transcript,
                    ],
                ],
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Analysis failed: '.$response->body());
        }

        $content = data_get($response->json(), 'choices.0.message.content');

        if (! is_string($content) || trim($content) === '') {
            throw new \RuntimeException('Analysis returned empty content.');
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        return CallSessionAnalysisProjection::fromDecoded($decoded);
    }
}
