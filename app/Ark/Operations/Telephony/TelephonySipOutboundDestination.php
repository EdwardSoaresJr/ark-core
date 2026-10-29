<?php

namespace App\Ark\Operations\Telephony;

use App\Ark\Operations\PhoneNumber;

/**
 * Decides what a desk phone may dial. Emergency numbers always go through. Everything else must be
 * a US or Canadian number outside premium and non-geographic exchanges, which keeps toll fraud out.
 * Other NANP areas are opened only through telephony.sip_outbound.allowed_area_codes.
 */
final class TelephonySipOutboundDestination
{
    public const EMERGENCY = ['911', '933'];

    private const PREMIUM_AREA_CODES = ['900', '976'];

    private const NON_GEOGRAPHIC_AREA_CODES = ['456', '500', '521', '522', '523', '524', '525', '526', '527', '528', '529', '533', '544', '566', '577', '588', '700'];

    /** NANP areas outside the US and Canada: Caribbean, Atlantic, and US Pacific and Caribbean territories. */
    public const OUTSIDE_US_CANADA_AREA_CODES = [
        '242', '246', '264', '268', '284', '340', '345', '441', '473', '649', '658', '664', '670', '671',
        '684', '721', '758', '767', '784', '787', '809', '829', '849', '868', '869', '876', '939',
    ];

    /**
     * @return array{kind: 'pstn'|'emergency'|'rejected', number: ?string, reason: ?string}
     */
    public static function resolve(string $sipTo, ?string $shopNumber): array
    {
        $raw = self::userPart($sipTo);
        $digits = PhoneNumber::digits($raw);

        if (in_array($digits, self::EMERGENCY, true)) {
            return ['kind' => 'emergency', 'number' => $digits, 'reason' => null];
        }

        if ($digits === '' || str_starts_with($digits, '011') || str_starts_with(trim($raw), '+') && ! str_starts_with(trim($raw), '+1')) {
            return self::rejected('International calls are not allowed from this phone.');
        }

        if (strlen($digits) === 7) {
            $areaCode = substr((string) PhoneNumber::normalize($shopNumber), 0, 3);
            $digits = strlen($areaCode) === 3 ? $areaCode.$digits : $digits;
        }

        $national = PhoneNumber::normalize($digits);

        if ($national === null || strlen($national) !== 10 || ! preg_match('/^[2-9]\d{2}[2-9]\d{6}$/', $national)) {
            return self::rejected('That number cannot be dialed.');
        }

        $areaCode = substr($national, 0, 3);

        if (in_array($areaCode, self::PREMIUM_AREA_CODES, true) || in_array($areaCode, self::NON_GEOGRAPHIC_AREA_CODES, true)) {
            return self::rejected('Premium-rate numbers are not allowed from this phone.');
        }

        if (in_array($areaCode, self::OUTSIDE_US_CANADA_AREA_CODES, true)
            && ! in_array($areaCode, (array) config('telephony.sip_outbound.allowed_area_codes', []), true)) {
            return self::rejected('Calls to that area code are not enabled for this phone.');
        }

        return ['kind' => 'pstn', 'number' => '+1'.$national, 'reason' => null];
    }

    private static function userPart(string $sipTo): string
    {
        $value = trim($sipTo);

        if (str_starts_with(strtolower($value), 'sip:')) {
            $value = substr($value, 4);
        }

        return urldecode(explode('@', $value, 2)[0]);
    }

    /**
     * @return array{kind: 'rejected', number: null, reason: string}
     */
    private static function rejected(string $reason): array
    {
        return ['kind' => 'rejected', 'number' => null, 'reason' => $reason];
    }
}
