<?php

namespace App\Ark\Operations\Telephony\Media;

final class CallSessionMediaUri
{
    public function __construct(
        public readonly string $scheme,
        public readonly string $reference,
        public readonly ?string $callSid = null,
        public readonly ?string $kind = null,
        public readonly ?string $twilioRecordingSid = null,
    ) {}

    public static function parse(?string $value): ?self
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, 'ark-voice://')) {
            $path = substr($value, strlen('ark-voice://'));

            if (preg_match('#^call/([^/]+)/(recording|voicemail)$#', $path, $matches) === 1) {
                return new self(
                    scheme: 'ark-voice',
                    reference: $value,
                    callSid: $matches[1],
                    kind: $matches[2],
                );
            }

            return new self(scheme: 'ark-voice', reference: $value);
        }

        if (str_starts_with($value, 'twilio://')) {
            return new self(scheme: 'twilio', reference: $value, twilioRecordingSid: self::twilioSidFromReference($value));
        }

        if (preg_match('#^https://api\.twilio\.com/.+/Recordings/(RE[a-f0-9]+)#i', $value, $matches) === 1) {
            return new self(
                scheme: 'twilio',
                reference: $value,
                twilioRecordingSid: $matches[1],
            );
        }

        if (str_starts_with($value, 'local://')) {
            return new self(scheme: 'local', reference: $value);
        }

        if (str_starts_with($value, 's3://')) {
            return new self(scheme: 's3', reference: $value);
        }

        return null;
    }

    public function isTwilio(): bool
    {
        return $this->scheme === 'twilio';
    }

    public function isArkVoice(): bool
    {
        return $this->scheme === 'ark-voice';
    }

    private static function twilioSidFromReference(string $reference): ?string
    {
        if (preg_match('/(RE[a-f0-9]+)/i', $reference, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }
}
