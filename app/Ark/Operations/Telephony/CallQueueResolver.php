<?php

namespace App\Ark\Operations\Telephony;

use App\Ark\Operations\Conversations\CustomerCallContextResolver;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Orientation\Orientation;
use App\Models\User;

class CallQueueResolver
{
    public function __construct(
        private readonly CallSessionQueue $queue,
        private readonly CustomerCallContextResolver $callContextResolver,
        private readonly CallQueuePresenter $presenter,
    ) {}

    /**
     * @return array{count: int, calls: array<int, array<string, mixed>>}
     */
    public function resolve(?User $viewer = null): array
    {
        $sessions = $this->queue->waitingSessions();
        $display = app(InboundCallerDisplayPhone::class);
        $phones = $sessions
            ->map(fn (CallSession $session): ?string => $this->attentionPhone($session, $display))
            ->all();
        $contexts = $this->callContextResolver->mapForAttentionList($phones);
        Orientation::preloadForContexts($contexts);

        $calls = $sessions
            ->map(function (CallSession $session) use ($viewer, $display, $contexts): array {
                $phone = $this->attentionPhone($session, $display);
                $key = PhoneNumber::normalize($phone);
                $context = $key !== null ? ($contexts[$key] ?? null) : null;

                return $this->presenter->present($session, $context, $viewer);
            })
            ->values()
            ->all();

        return [
            'count' => count($calls),
            'calls' => $calls,
        ];
    }

    private function attentionPhone(CallSession $session, InboundCallerDisplayPhone $display): ?string
    {
        $customerPhone = $session->customer?->phone;

        if (is_string($customerPhone) && $customerPhone !== '') {
            return $customerPhone;
        }

        return $display->normalizedForSession($session) ?? $session->normalized_from;
    }
}
