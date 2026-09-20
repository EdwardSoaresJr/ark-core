<?php

namespace App\Ark\Operations\Realtime;

use App\Ark\Operations\Realtime\Contracts\SessionProvider;
use App\Ark\Operations\Realtime\Providers\FakeSessionProvider;
use App\Ark\Operations\Telephony\TelephonyProviderType;
use InvalidArgumentException;

final class SessionProviderManager
{
    public function __construct(
        private readonly FakeSessionProvider $fake,
    ) {}

    public function current(): SessionProvider
    {
        return $this->resolve(config('communications.session_provider', TelephonyProviderType::Fake->value));
    }

    public function resolve(string $key): SessionProvider
    {
        return match ($key) {
            TelephonyProviderType::Fake->value, 'fake' => $this->fake,
            default => throw new InvalidArgumentException("Unknown session provider [{$key}]."),
        };
    }

    /**
     * @return list<SessionProvider>
     */
    public function registered(): array
    {
        return [$this->fake];
    }
}
