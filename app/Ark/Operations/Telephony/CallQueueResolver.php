<?php

namespace App\Ark\Operations\Telephony;

use App\Ark\Operations\Conversations\CustomerCallContextResolver;
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

        $calls = $sessions
            ->map(function (CallSession $session) use ($viewer): array {
                $lookupPhone = app(InboundCallerDisplayPhone::class)->normalizedForSession($session)
                    ?? $session->normalized_from;

                $context = $session->customer_id !== null
                    ? $this->callContextResolver->resolveForCustomer($session->customer)
                    : $this->callContextResolver->resolve($lookupPhone);

                return $this->presenter->present($session, $context, $viewer);
            })
            ->values()
            ->all();

        return [
            'count' => count($calls),
            'calls' => $calls,
        ];
    }
}
