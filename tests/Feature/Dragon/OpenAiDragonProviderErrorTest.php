<?php

use App\Ark\Dragon\Agent\DragonProviderUnavailable;
use App\Ark\Dragon\Agent\Providers\OpenAiDragonProvider;
use App\Ark\Dragon\Assist\CompleteHostedDragonAssistAction;
use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use Illuminate\Support\Facades\Http;

test('openai provider surfaces the api error body on http 400', function (): void {
    config([
        'dragon.openai_api_key' => 'sk-test',
        'dragon.openai_model' => 'gpt-4o',
        'dragon.openai_base_url' => 'https://api.openai.com/v1',
    ]);

    Http::fake([
        'https://api.openai.com/v1/chat/completions' => Http::response([
            'error' => [
                'message' => "Invalid schema for response_format 'dragon_structured': Missing 'concern_id'.",
                'type' => 'invalid_request_error',
            ],
        ], 400),
    ]);

    $provider = new OpenAiDragonProvider(app(ShopIntegrationCredentials::class));

    try {
        $provider->structured(
            [['role' => 'user', 'content' => '{}']],
            CompleteHostedDragonAssistAction::reviewOpenAiSchema(),
        );
        expect(false)->toBeTrue();
    } catch (DragonProviderUnavailable $e) {
        expect($e->getMessage())->toContain('HTTP 400')
            ->and($e->getMessage())->toContain("Missing 'concern_id'");
    }
});
