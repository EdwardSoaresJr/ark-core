<?php

use App\Ark\Dragon\Assist\CompleteHostedDragonAssistAction;

function assertOpenAiStrictObject(array $schema): void
{
    expect($schema['type'] ?? null)->toBe('object')
        ->and($schema['additionalProperties'] ?? true)->toBeFalse();

    $properties = array_keys($schema['properties'] ?? []);
    $required = $schema['required'] ?? [];
    sort($properties);
    sort($required);
    expect($required)->toBe($properties);

    foreach ($schema['properties'] ?? [] as $definition) {
        if (($definition['type'] ?? null) === 'array' && isset($definition['items']) && is_array($definition['items'])) {
            $items = $definition['items'];
            if (($items['type'] ?? null) === 'object') {
                assertOpenAiStrictObject($items);
            }
        }
    }
}

test('review estimate notes openai schema is strict-mode legal', function (): void {
    assertOpenAiStrictObject(CompleteHostedDragonAssistAction::reviewOpenAiSchema());
});

test('historical recall openai schema is strict-mode legal', function (): void {
    assertOpenAiStrictObject(CompleteHostedDragonAssistAction::recallOpenAiSchema());
});
