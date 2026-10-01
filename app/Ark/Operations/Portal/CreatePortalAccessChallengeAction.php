<?php

namespace App\Ark\Operations\Portal;

final class CreatePortalAccessChallengeAction
{
    private const CHALLENGE_TTL_MINUTES = 10;

    private const MAX_CHALLENGES_PER_DESTINATION = 3;

    private const CHALLENGE_WINDOW_MINUTES = 15;

    public function __construct(
        private readonly ResolveCustomerByContact $resolveCustomer,
        private readonly PortalAccessChallengeSender $sender,
    ) {}

    public function execute(string $contactInput): ?PortalAccessChallenge
    {
        $resolved = $this->resolveCustomer->resolve($contactInput);

        if ($resolved === null) {
            return null;
        }

        $customer = $resolved['customer'];
        $channel = $resolved['channel'];
        $destination = $resolved['destination'];

        $recentChallengeCount = PortalAccessChallenge::query()
            ->where('destination', $destination)
            ->where('created_at', '>=', now()->subMinutes(self::CHALLENGE_WINDOW_MINUTES))
            ->count();

        if ($recentChallengeCount >= self::MAX_CHALLENGES_PER_DESTINATION) {
            return null;
        }

        PortalAccessChallenge::query()
            ->where('customer_id', $customer->id)
            ->where('destination', $destination)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->update(['used_at' => now()]);

        $plainCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $challenge = PortalAccessChallenge::query()->create([
            'customer_id' => $customer->id,
            'channel' => $channel,
            'destination' => $destination,
            'code_hash' => PortalAccessChallenge::hashCode($plainCode),
            'expires_at' => now()->addMinutes(self::CHALLENGE_TTL_MINUTES),
        ]);

        $this->sender->send($challenge, $plainCode, $customer);

        return $challenge;
    }
}
