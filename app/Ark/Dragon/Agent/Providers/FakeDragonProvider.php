<?php

namespace App\Ark\Dragon\Agent\Providers;

use App\Ark\Dragon\Agent\Contracts\DragonModelProvider;
use App\Ark\Dragon\Agent\DragonModelTurn;
use App\Ark\Dragon\Agent\DragonProviderUnavailable;

final class FakeDragonProvider implements DragonModelProvider
{
    /** @var list<DragonModelTurn> */
    public array $script = [];

    /** @var list<array<string, mixed>> */
    public array $receivedMessages = [];

    public bool $unavailable = false;

    /** @var list<array<string, mixed>> */
    public array $structuredQueue = [];

    public function complete(array $messages, array $tools = []): DragonModelTurn
    {
        if ($this->unavailable) {
            throw new DragonProviderUnavailable('Fake provider marked unavailable.');
        }

        $this->receivedMessages[] = ['messages' => $messages, 'tools' => $tools];

        if ($this->script !== []) {
            return array_shift($this->script);
        }

        return new DragonModelTurn('I looked at the shop floor and can answer from the tools I have.', []);
    }

    public function structured(array $messages, array $schema): array
    {
        if ($this->unavailable) {
            throw new DragonProviderUnavailable('Fake provider marked unavailable.');
        }

        if ($this->structuredQueue !== []) {
            return array_shift($this->structuredQueue);
        }

        $properties = $schema['properties'] ?? [];
        $last = $messages[array_key_last($messages)] ?? [];
        $content = is_array($last) ? (string) ($last['content'] ?? '') : '';
        $decoded = json_decode($content, true);
        $source = '';
        if (is_array($decoded)) {
            $source = trim((string) ($decoded['source_note'] ?? $decoded['selected_text'] ?? ''));
        }

        if (isset($properties['proposal'])) {
            return [
                'proposal' => $source !== '' ? $source : 'Rewritten note.',
                'facts_preserved' => [],
                'material_changes' => [],
                'warnings' => [],
                'confidence' => 0.9,
            ];
        }

        if (isset($properties['proposals'])) {
            return [
                'summary' => 'Notes are complete enough to present.',
                'strengths' => [],
                'gaps' => [],
                'inconsistencies' => [],
                'customer_readiness' => 'Ready if documented facts stay intact.',
                'suggested_actions' => [],
                'warnings' => [],
                'confidence' => 0.8,
                'proposals' => [],
            ];
        }

        return [
            'summary' => 'Historical recall looks consistent with the saved work.',
            'confidence_comment' => 'Advisory only. Tier and hours stay with ARK.',
            'cautions' => [],
            'recommendation' => 'Use the deterministic hours unless the vehicle differs.',
            'review_book_time' => false,
            'sources' => [],
        ];
    }

    public function providerName(): string
    {
        return 'fake';
    }

    public function modelName(): string
    {
        return 'fake-dragon';
    }

    public function health(): array
    {
        return [
            'ok' => ! $this->unavailable,
            'provider' => $this->providerName(),
            'model' => $this->modelName(),
            'detail' => $this->unavailable ? 'unavailable' : 'ok',
        ];
    }
}
