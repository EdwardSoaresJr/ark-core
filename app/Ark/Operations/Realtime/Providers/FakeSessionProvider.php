<?php

namespace App\Ark\Operations\Realtime\Providers;

use App\Ark\Operations\Realtime\Contracts\SessionProvider;
use App\Ark\Operations\Realtime\RecordSessionEventAction;
use App\Ark\Operations\Realtime\SessionEvent;
use App\Ark\Operations\Realtime\SessionEventType;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\TelephonyProviderType;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Permanent first-class provider — simulates normalized session lifecycle without transport.
 */
final class FakeSessionProvider implements SessionProvider
{
    public function __construct(
        private readonly RecordSessionEventAction $recordSessionEvent,
    ) {}

    public function providerType(): TelephonyProviderType
    {
        return TelephonyProviderType::Fake;
    }

    public function key(): string
    {
        return 'fake';
    }

    /**
     * @param  array<string, mixed>  $identity
     * @return array{session: CallSession, events: Collection<int, SessionEvent>}
     */
    public function runStandardLifecycle(
        array $identity,
        User $fromUser,
        User $toUser,
        ?Carbon $startedAt = null,
    ): array {
        $startedAt ??= now();

        ['session' => $session, 'event' => $started] = $this->recordSessionEvent->begin($identity, $fromUser, $startedAt);

        $events = collect([$started]);

        $sequence = [
            [SessionEventType::SessionAnswered, [], null],
            [SessionEventType::SessionTransferred, [
                'from_user_id' => $fromUser->id,
                'from_user_name' => $fromUser->name,
                'to_user_id' => $toUser->id,
                'to_user_name' => $toUser->name,
            ], $fromUser],
            [SessionEventType::SessionHeld, [], null],
            [SessionEventType::SessionEnded, [], null],
        ];

        foreach ($sequence as $index => [$type, $payload, $actor]) {
            $events->push(
                $this->recordSessionEvent->record(
                    $session,
                    $type,
                    $payload,
                    $actor,
                    $startedAt->copy()->addSeconds($index + 2),
                ),
            );
        }

        return [
            'session' => $session->fresh(['sessionEvents']),
            'events' => $events,
        ];
    }

    /**
     * @param  array<string, mixed>  $identity
     */
    public function emit(
        CallSession $session,
        SessionEventType $type,
        array $payload = [],
        ?User $actor = null,
        ?Carbon $occurredAt = null,
    ): SessionEvent {
        return $this->recordSessionEvent->record($session, $type, $payload, $actor, $occurredAt);
    }
}
