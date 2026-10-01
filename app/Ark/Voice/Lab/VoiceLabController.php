<?php

namespace App\Ark\Voice\Lab;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class VoiceLabController
{
    public function __invoke(Request $request, VoiceLabTranscriber $transcriber, VoiceLabRecordScore $score): JsonResponse
    {
        if (! config('voice.lab_enabled') || ! filled(config('voice.lab_secret'))) {
            return response()->json(['message' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }

        $provided = (string) $request->header('X-Voice-Lab-Secret', '');
        if (! hash_equals((string) config('voice.lab_secret'), $provided)) {
            return response()->json(['message' => 'Unauthenticated.'], Response::HTTP_UNAUTHORIZED);
        }

        $wav = $this->wavBytes($request);
        if ($wav === null) {
            return response()->json(['message' => 'WAV body or audio file is required.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (strlen($wav) > 2_000_000) {
            return response()->json(['message' => 'Audio too large.'], Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        try {
            $transcript = $transcriber->transcribeWav($wav);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], Response::HTTP_BAD_GATEWAY);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Transcription failed.'], Response::HTTP_BAD_GATEWAY);
        }

        $expect = (string) $request->header('X-Voice-Expect', $request->input('expect', ''));
        $scoring = null;
        if ($expect === 'rr2-lr3') {
            $scoring = $score->score(VoiceLabRecordScore::goldRearPadFacts(), $transcript);
        }

        Log::info('voice.lab.transcribed', [
            'mic' => $request->header('X-Voice-Mic'),
            'bytes' => strlen($wav),
            'transcript' => $transcript,
            'record_accurate' => $scoring['record_accurate'] ?? null,
        ]);

        return response()->json([
            'transcript' => $transcript,
            'mic' => $request->header('X-Voice-Mic'),
            'score' => $scoring,
            'dragon' => false,
            'stored' => false,
        ]);
    }

    private function wavBytes(Request $request): ?string
    {
        if ($request->hasFile('audio')) {
            $file = $request->file('audio');
            if ($file === null || ! $file->isValid()) {
                return null;
            }

            return (string) file_get_contents($file->getRealPath());
        }

        $raw = $request->getContent();

        return is_string($raw) && strlen($raw) > 44 ? $raw : null;
    }
}
