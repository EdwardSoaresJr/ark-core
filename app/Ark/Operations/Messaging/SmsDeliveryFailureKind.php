<?php

namespace App\Ark\Operations\Messaging;

/**
 * Provider-neutral classification of outbound SMS delivery failures for staff recovery copy.
 * Codes are recognized from common carrier/provider identifiers without binding Core to one vendor.
 */
enum SmsDeliveryFailureKind: string
{
    case OptOut = 'opt_out';
    case LandlineUnsupported = 'landline_unsupported';
    case Invalid = 'invalid';
    case Temporary = 'temporary';
    case Unknown = 'unknown';

    /** @var list<string> */
    private const OPT_OUT = ['21610'];

    /** @var list<string> */
    private const LANDLINE = ['30006', '21614', '21612'];

    /** @var list<string> */
    private const INVALID = ['21211', '21401', '21408', '21601'];

    /** @var list<string> */
    private const TEMPORARY = ['30001', '30002', '30003', '30004', '30005', '30007', '30008', '30009'];

    public static function fromErrorCode(?string $errorCode): self
    {
        $code = trim((string) $errorCode);
        if ($code === '') {
            return self::Unknown;
        }
        if (in_array($code, self::OPT_OUT, true)) {
            return self::OptOut;
        }
        if (in_array($code, self::LANDLINE, true)) {
            return self::LandlineUnsupported;
        }
        if (in_array($code, self::INVALID, true)) {
            return self::Invalid;
        }
        if (in_array($code, self::TEMPORARY, true)) {
            return self::Temporary;
        }

        return self::Unknown;
    }

    public function allowsRetry(): bool
    {
        return $this === self::Temporary || $this === self::Unknown;
    }

    public function staffDetail(?string $errorCode = null): string
    {
        $code = trim((string) $errorCode);
        $carrier = $code !== '' ? 'Carrier error '.$code.'. ' : '';

        return match ($this) {
            self::OptOut => $carrier.'This number opted out of texts (STOP). Do not text it again until the customer replies START. Call or use email instead.',
            self::LandlineUnsupported => $carrier.'This number cannot receive texts (landline or unsupported line type). Call or use another contact.',
            self::Invalid => $carrier.'The destination number is invalid or unreachable for SMS. Correct the number, call, or use email.',
            self::Temporary => $carrier.'The carrier could not deliver this text yet. You may try again later, or call if urgent.',
            self::Unknown => $carrier.'The customer did not get this message. Verify the number before trying again, or call.',
        };
    }
}
