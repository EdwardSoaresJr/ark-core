<?php

namespace App\Ark\Tech;

use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class TechFindingRewriteService
{
    public function propose(string $source): string
    {
        $source = trim($source);
        if ($source === '') {
            throw new RuntimeException('Nothing to rewrite.');
        }

        $credentials = ShopIntegrationCredentials::forCurrentShop();
        $apiKey = trim((string) ($credentials->openaiApiKey() ?? config('dragon.openai_api_key') ?? ''));
        if ($apiKey === '') {
            throw new RuntimeException('Dragon rewrite is unavailable. Keep the original note.');
        }

        $response = Http::timeout(45)
            ->withToken($apiKey)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $credentials->openaiAnalysisModel(),
                'temperature' => 0,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Rewrite a technician inspection note in clear English. Preserve every number, unit, left/right, front/rear, component, and DTC. Do not add safety claims, causes, or certainty the tech did not state. Do not invent measurements. Return only the rewritten sentence.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $source,
                    ],
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Dragon rewrite is unavailable. Keep the original note.');
        }

        $text = trim((string) data_get($response->json(), 'choices.0.message.content', ''));
        if ($text === '') {
            throw new RuntimeException('Dragon rewrite is unavailable. Keep the original note.');
        }

        return $text;
    }
}
