<?php

namespace App\Ark\Operations\Attention;

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\CustomerCallContextResolver;
use App\Ark\Operations\Customers\Customer;

/**
 * Suggested reply text for advisor nudges — advisor confirms before send.
 */
final class AdvisorNudgeDraftBuilder
{
    public function __construct(
        private readonly CustomerCallContextResolver $callContextResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $nudge
     */
    public function forNudge(array $nudge, ?Conversation $conversation = null, ?Customer $customer = null): ?string
    {
        $key = (string) ($nudge['key'] ?? '');
        $name = $this->firstName($customer, $conversation);

        $draft = match ($key) {
            'conversation.waiting_response' => "Hi {$name}, following up on your message — let me know if you still need anything from us.",
            'conversation.multiple_messages' => "Hi {$name}, sorry for the delay — I'm here now. What can I help with?",
            'conversation.estimate_views', 'conversation.estimate_viewed' => "Hi {$name}, I noticed you opened the estimate — happy to answer any questions or help with approval whenever you're ready.",
            'conversation.sms_analysis_follow_up' => $this->smsAnalysisDraft($nudge, $name),
            'call.analysis_follow_up' => $this->callAnalysisDraft($nudge, $name),
            default => null,
        };

        return filled($draft) ? $draft : null;
    }

    /**
     * @param  array<string, mixed>  $nudge
     */
    private function smsAnalysisDraft(array $nudge, string $name): ?string
    {
        $suggested = trim((string) ($nudge['suggested_reply'] ?? ''));

        if ($suggested === '' || $this->looksLikeAdvisorCoaching($suggested)) {
            return null;
        }

        if (str_starts_with(strtolower($suggested), 'hi ')) {
            return $suggested;
        }

        return "Hi {$name}, {$suggested}";
    }

    private function looksLikeAdvisorCoaching(string $text): bool
    {
        $lower = strtolower($text);

        foreach ([
            'advisor should',
            'the advisor',
            'shop left',
            'no advisor reply',
            'missed follow-up',
            'no reply was recorded',
            'operational failure',
        ] as $needle) {
            if (str_contains($lower, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $nudge
     */
    private function callAnalysisDraft(array $nudge, string $name): ?string
    {
        $suggested = trim((string) ($nudge['suggested_reply'] ?? ''));

        if ($suggested !== '') {
            return "{$name} — {$suggested}";
        }

        $notes = trim((string) ($nudge['message'] ?? ''));

        if ($notes === '') {
            return null;
        }

        return "{$name} — {$notes}";
    }

    private function firstName(?Customer $customer, ?Conversation $conversation): string
    {
        if ($customer instanceof Customer && filled($customer->first_name)) {
            return trim((string) $customer->first_name);
        }

        if ($conversation !== null) {
            $phone = trim((string) $conversation->contact_address);

            if ($phone !== '') {
                $context = $this->callContextResolver->resolve($phone);
                $resolved = $context?->customer;

                if ($resolved instanceof Customer && filled($resolved->first_name)) {
                    return trim((string) $resolved->first_name);
                }
            }
        }

        return 'there';
    }
}
