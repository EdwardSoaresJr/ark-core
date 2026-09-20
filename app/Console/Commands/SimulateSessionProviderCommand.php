<?php

namespace App\Console\Commands;

use App\Ark\Operations\Realtime\CanonicalSessionStream;
use App\Ark\Operations\Realtime\Providers\FakeSessionProvider;
use App\Ark\Operations\Realtime\Scenarios\StandardSessionLifecycleScenario;
use App\Ark\Operations\Realtime\SessionEvent;
use App\Ark\Operations\Realtime\SessionEventIngress;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\TelephonyProviderType;
use App\Models\User;
use Illuminate\Console\Command;

class SimulateSessionProviderCommand extends Command
{
    protected $signature = 'communications:simulate-provider
                            {provider : fake or twilio}
                            {--compare-golden : Compare normalized stream to golden fixture}';

    protected $description = 'Simulate a standard session lifecycle through a transport provider normalizer';

    public function handle(
        SessionEventIngress $ingress,
        FakeSessionProvider $fakeProvider,
    ): int {
        $providerKey = strtolower(trim($this->argument('provider')));

        $provider = match ($providerKey) {
            'fake' => TelephonyProviderType::Fake,
            'none' => TelephonyProviderType::None,
            default => null,
        };

        if ($provider === null) {
            $this->error('Provider must be fake or twilio.');

            return self::FAILURE;
        }

        $edward = User::query()->first() ?? User::factory()->create(['name' => StandardSessionLifecycleScenario::FROM_USER_NAME]);
        $molly = User::query()->whereKeyNot($edward->id)->first()
            ?? User::factory()->create(['name' => StandardSessionLifecycleScenario::TO_USER_NAME]);

        if ($provider === TelephonyProviderType::Fake) {
            $result = $fakeProvider->runStandardLifecycle(
                identity: array_merge(StandardSessionLifecycleScenario::sessionIdentity(), [
                    'normalized_from' => StandardSessionLifecycleScenario::FROM_NUMBER,
                    'direction' => CallSessionDirection::Inbound,
                ]),
                fromUser: $edward,
                toUser: $molly,
            );

            $session = $result['session'];
            $stream = $this->streamFromPersistedEvents($session->id);
        } else {
            $rawEvents = StandardSessionLifecycleScenario::twilioRawEvents($edward->id, $molly->id);

            $stream = $ingress->normalizeRawStream($provider, $rawEvents);
            $session = $ingress->ingestRawStream($provider, $rawEvents);
        }

        $this->line("Provider: {$providerKey}");
        $this->line("Session: {$session->id} ({$session->provider_call_sid})");
        $this->line('Status: '.$session->status->value);
        $this->line('Events: '.SessionEvent::query()->where('call_session_id', $session->id)->count());

        foreach ($stream->signatures() as $signature) {
            $payload = $signature['payload'] !== [] ? ' '.json_encode($signature['payload']) : '';
            $this->line("  - {$signature['type']}{$payload}");
        }

        if ($this->option('compare-golden')) {
            $golden = StandardSessionLifecycleScenario::goldenStream();

            if ($stream->equals($golden)) {
                $this->info('Golden stream match: yes');
            } else {
                $this->error('Golden stream match: no');
                $this->line('Expected: '.json_encode($golden->signatures()));
                $this->line('Actual:   '.json_encode($stream->signatures()));

                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }

    private function streamFromPersistedEvents(int $sessionId): CanonicalSessionStream
    {
        $events = SessionEvent::query()
            ->where('call_session_id', $sessionId)
            ->orderBy('occurred_at')
            ->get()
            ->map(fn (SessionEvent $event) => new \App\Ark\Operations\Realtime\CanonicalSessionEvent(
                type: $event->event_type,
                payload: (array) ($event->payload ?? []),
            ))
            ->all();

        return new CanonicalSessionStream($events);
    }
}
