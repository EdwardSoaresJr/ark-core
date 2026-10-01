<?php

namespace App\Ark\Operations\Telephony\Media;

final class CallSessionMediaMetadata
{
    /**
     * @return array{
     *     provider: string,
     *     codec: ?string,
     *     sample_rate: ?int,
     *     channels: ?int,
     *     bits_per_sample: ?int,
     *     file_size_bytes: ?int,
     *     duration_seconds: ?int,
     *     checksum_sha256: ?string,
     * }
     */
    public static function fromWavPath(string $path, string $provider = 'twilio'): array
    {
        $fileSize = is_readable($path) ? filesize($path) : false;
        $wav = self::readWavHeader($path);

        return [
            'provider' => $provider,
            'codec' => $wav['codec'] ?? 'pcm_s16le',
            'sample_rate' => $wav['sample_rate'] ?? null,
            'channels' => $wav['channels'] ?? null,
            'bits_per_sample' => $wav['bits_per_sample'] ?? null,
            'file_size_bytes' => $fileSize !== false ? (int) $fileSize : null,
            'duration_seconds' => $wav['duration_seconds'] ?? null,
            'checksum_sha256' => is_readable($path) ? hash_file('sha256', $path) : null,
        ];
    }

    /**
     * @return array{codec?: string, sample_rate?: int, channels?: int, bits_per_sample?: int, duration_seconds?: int}
     */
    private static function readWavHeader(string $path): array
    {
        if (! is_readable($path)) {
            return [];
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return [];
        }

        try {
            if (fread($handle, 4) !== 'RIFF') {
                return [];
            }

            fseek($handle, 12, SEEK_SET);

            while (! feof($handle)) {
                $chunkHeader = fread($handle, 8);

                if ($chunkHeader === false || strlen($chunkHeader) < 8) {
                    break;
                }

                $chunkId = substr($chunkHeader, 0, 4);
                $chunkSize = unpack('V', substr($chunkHeader, 4, 4))[1] ?? 0;

                if ($chunkId === 'fmt ') {
                    $fmt = fread($handle, min($chunkSize, 16));

                    if ($fmt === false || strlen($fmt) < 16) {
                        break;
                    }

                    $channels = unpack('v', substr($fmt, 2, 2))[1] ?? 1;
                    $sampleRate = unpack('V', substr($fmt, 4, 4))[1] ?? 8000;
                    $bitsPerSample = unpack('v', substr($fmt, 14, 2))[1] ?? 16;
                } elseif ($chunkId === 'data') {
                    if (! isset($sampleRate, $channels, $bitsPerSample)) {
                        break;
                    }

                    $bytesPerSecond = $sampleRate * $channels * ($bitsPerSample / 8);

                    return [
                        'codec' => 'pcm_s16le',
                        'sample_rate' => (int) $sampleRate,
                        'channels' => (int) $channels,
                        'bits_per_sample' => (int) $bitsPerSample,
                        'duration_seconds' => $bytesPerSecond > 0
                            ? max(1, (int) round($chunkSize / $bytesPerSecond))
                            : null,
                    ];
                } else {
                    fseek($handle, $chunkSize, SEEK_CUR);
                }

                if (($chunkSize % 2) === 1) {
                    fseek($handle, 1, SEEK_CUR);
                }
            }
        } finally {
            fclose($handle);
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public static function forTwilioWebhook(string $recordingUrl, int $duration, ?string $recordingSid): array
    {
        return [
            'provider' => 'twilio',
            'codec' => 'mp3',
            'sample_rate' => null,
            'channels' => null,
            'bits_per_sample' => null,
            'file_size_bytes' => null,
            'duration_seconds' => $duration > 0 ? $duration : null,
            'checksum_sha256' => null,
            'recording_sid' => $recordingSid,
            'source_url' => $recordingUrl,
        ];
    }
}
