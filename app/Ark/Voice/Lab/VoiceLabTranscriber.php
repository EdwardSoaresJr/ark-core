<?php

namespace App\Ark\Voice\Lab;

use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use RuntimeException;

final class VoiceLabTranscriber
{
    public function transcribeWav(string $wavBytes, string $filename = 'utterance.wav'): string
    {
        if (! ShopIntegrationCredentials::forCurrentShop()->openaiConfigured()) {
            throw new RuntimeException('Voice lab transcription requires a model provider (not configured).');
        }

        throw new RuntimeException('Voice lab transcription requires a model provider (not configured).');
    }
}
