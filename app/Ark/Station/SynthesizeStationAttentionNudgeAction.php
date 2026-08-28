<?php

namespace App\Ark\Station;

use App\Ark\Dragon\Agent\Contracts\DragonModelProvider;
use App\Ark\Dragon\Agent\DragonProviderUnavailable;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Hosted-Dragon synthesis of an already-built attention snapshot.
 * Does not search the shop. Does not invent ROs.
 */
final class SynthesizeStationAttentionNudgeAction
{
    public function __construct(
        private readonly StationAttentionProjection $attention,
        private readonly DragonModelProvider $provider,
    ) {}

    /**
     * @return array{available: bool, text: ?string, model: ?string, fingerprint: string, reason: ?string}
     */
    public function handle(): array
    {
        $payload = $this->attention->payload();
        $fingerprint = (string) $payload['snapshot_fingerprint'];

        if (! (bool) config('dragon.hosted_chat_enabled', true)) {
            return $this->miss($fingerprint, 'hosted_chat_disabled');
        }

        return Cache::remember('station-attention-nudge:'.$fingerprint, 300, function () use ($payload, $fingerprint): array {
            try {
                $turn = $this->provider->complete([
                    [
                        'role' => 'system',
                        'content' => implode("\n", [
                            'You write a Shop Glass nudge of at most two short lines for two advisors.',
                            'Use only the JSON facts. Do not invent ROs, dollars, names, or statuses.',
                            'Name at most two priorities. Prefer oldest wait and largest waiting-approval dollars.',
                            'Money strings are already US dollars. Never treat integers as dollars.',
                            'No essays. No questions. No consulting advice.',
                        ]),
                    ],
                    [
                        'role' => 'user',
                        'content' => json_encode($payload['snapshot'], JSON_UNESCAPED_SLASHES),
                    ],
                ], []);

                $text = trim((string) $turn->content);
                if ($text === '') {
                    return $this->miss($fingerprint, 'empty_model');
                }

                return [
                    'available' => true,
                    'text' => $text,
                    'model' => $this->provider->modelName(),
                    'fingerprint' => $fingerprint,
                    'reason' => null,
                ];
            } catch (DragonProviderUnavailable) {
                return $this->miss($fingerprint, 'provider_unavailable');
            } catch (Throwable) {
                return $this->miss($fingerprint, 'provider_error');
            }
        });
    }

    /**
     * @return array{available: bool, text: ?string, model: ?string, fingerprint: string, reason: ?string}
     */
    private function miss(string $fingerprint, string $reason): array
    {
        return [
            'available' => false,
            'text' => null,
            'model' => null,
            'fingerprint' => $fingerprint,
            'reason' => $reason,
        ];
    }
}
