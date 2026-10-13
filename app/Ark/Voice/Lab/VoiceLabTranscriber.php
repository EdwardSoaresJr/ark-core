<?php

namespace App\Ark\Voice\Lab;

use RuntimeException;

/**
 * Stock shops do not transcribe lab audio.
 * Hosted transcription is bound over this class when Dragon is entitled.
 */
class VoiceLabTranscriber
{
    public function transcribeWav(string $wavBytes, string $filename = 'utterance.wav'): string
    {
        throw new RuntimeException('Voice lab transcription requires a model provider (not configured).');
    }
}
