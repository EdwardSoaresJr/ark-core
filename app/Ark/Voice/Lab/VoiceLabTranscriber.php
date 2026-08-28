<?php

namespace App\Ark\Voice\Lab;

use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class VoiceLabTranscriber
{
    public function transcribeWav(string $wavBytes, string $filename = 'utterance.wav'): string
    {
        $credentials = ShopIntegrationCredentials::forCurrentShop();
        $apiKey = $this->apiKey($credentials);
        if ($apiKey === null) {
            throw new RuntimeException('Voice lab has no OpenAI key (shop settings or OPENAI_API_KEY).');
        }

        $response = Http::timeout(90)
            ->withToken($apiKey)
            ->attach('file', $wavBytes, $filename)
            ->post('https://api.openai.com/v1/audio/transcriptions', [
                'model' => $credentials->openaiTranscriptionModel(),
                'language' => 'en',
                'response_format' => 'text',
                'prompt' => (string) config('voice.lab_prompt'),
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Transcription failed: '.$response->body());
        }

        return trim($response->body());
    }

    private function apiKey(ShopIntegrationCredentials $credentials): ?string
    {
        $shop = trim((string) ($credentials->openaiApiKey() ?? ''));
        if ($shop !== '') {
            return $shop;
        }

        $env = trim((string) config('dragon.openai_api_key'));

        return $env !== '' ? $env : null;
    }
}
