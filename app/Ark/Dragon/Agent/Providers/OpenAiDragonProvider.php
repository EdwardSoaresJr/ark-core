<?php

namespace App\Ark\Dragon\Agent\Providers;

use App\Ark\Dragon\Agent\Contracts\DragonModelProvider;
use App\Ark\Dragon\Agent\DragonModelTurn;
use App\Ark\Dragon\Agent\DragonProviderUnavailable;
use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use Illuminate\Support\Facades\Http;
use Throwable;

final class OpenAiDragonProvider implements DragonModelProvider
{
    public function __construct(
        private readonly ShopIntegrationCredentials $credentials,
    ) {}

    public function complete(array $messages, array $tools = []): DragonModelTurn
    {
        $payload = [
            'model' => $this->modelName(),
            'messages' => $messages,
            'temperature' => 0.2,
        ];

        if ($tools !== []) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }

        try {
            $json = $this->post('chat/completions', $payload);
        } catch (DragonProviderUnavailable $e) {
            throw $e;
        } catch (Throwable) {
            throw new DragonProviderUnavailable('OpenAI is unreachable.');
        }
        $message = $json['choices'][0]['message'] ?? [];
        $usage = $json['usage'] ?? [];

        $toolCalls = [];
        foreach ($message['tool_calls'] ?? [] as $call) {
            $raw = $call['function']['arguments'] ?? '{}';
            $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
            $toolCalls[] = [
                'id' => (string) ($call['id'] ?? uniqid('call_', true)),
                'name' => (string) ($call['function']['name'] ?? ''),
                'arguments' => is_array($decoded) ? $decoded : [],
            ];
        }

        $content = $message['content'] ?? null;

        return new DragonModelTurn(
            content: is_string($content) ? $content : null,
            toolCalls: $toolCalls,
            promptTokens: (int) ($usage['prompt_tokens'] ?? 0),
            completionTokens: (int) ($usage['completion_tokens'] ?? 0),
            finishReason: isset($json['choices'][0]['finish_reason']) ? (string) $json['choices'][0]['finish_reason'] : null,
        );
    }

    public function structured(array $messages, array $schema): array
    {
        $json = $this->post('chat/completions', [
            'model' => $this->modelName(),
            'temperature' => 0.1,
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'dragon_structured',
                    'strict' => true,
                    'schema' => $schema,
                ],
            ],
            'messages' => $messages,
        ]);

        $content = $json['choices'][0]['message']['content'] ?? '{}';
        $decoded = json_decode((string) $content, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function providerName(): string
    {
        return 'openai';
    }

    public function modelName(): string
    {
        $configured = trim((string) config('dragon.openai_model'));

        return $configured !== '' ? $configured : $this->credentials->openaiAnalysisModel();
    }

    public function health(): array
    {
        try {
            $this->post('models', [], 'GET');

            return [
                'ok' => true,
                'provider' => $this->providerName(),
                'model' => $this->modelName(),
                'detail' => 'reachable',
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'provider' => $this->providerName(),
                'model' => $this->modelName(),
                'detail' => 'unreachable',
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function post(string $path, array $payload, string $method = 'POST'): array
    {
        $key = $this->apiKey();
        if ($key === '') {
            throw new DragonProviderUnavailable('OpenAI is not configured.');
        }

        $request = Http::timeout((int) config('dragon.timeout_seconds', 60))
            ->withToken($key)
            ->acceptJson();

        $url = config('dragon.openai_base_url').'/'.$path;
        $response = $method === 'GET' ? $request->get($url) : $request->post($url, $payload);

        if ($response->failed()) {
            $detail = $response->json('error.message');
            $detail = is_string($detail) ? trim($detail) : '';
            if (strlen($detail) > 240) {
                $detail = substr($detail, 0, 237).'...';
            }

            throw new DragonProviderUnavailable(
                'OpenAI request failed (HTTP '.$response->status().')'
                .($detail !== '' ? ': '.$detail : '.')
            );
        }

        /** @var array<string, mixed> $json */
        $json = $response->json() ?? [];

        return $json;
    }

    private function apiKey(): string
    {
        $fromShop = trim((string) ($this->credentials->openaiApiKey() ?? ''));
        if ($fromShop !== '') {
            return $fromShop;
        }

        return trim((string) config('dragon.openai_api_key'));
    }
}
