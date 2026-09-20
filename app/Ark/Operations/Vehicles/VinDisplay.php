<?php

namespace App\Ark\Operations\Vehicles;

class VinDisplay
{
    public const WMI_LENGTH = 3;

    public const VDS_LENGTH = 6;

    /** VIS — vehicle identifier (model year, plant, serial). */
    public const SUFFIX_LENGTH = 8;

    public const WMI_LABEL = 'WMI';

    public const VDS_LABEL = 'VDS';

    public const SERIAL_LABEL = 'Serial';

    public const WMI_META = '1–3 · manufacturer';

    public const VDS_META = '4–9 · descriptor';

    public const SERIAL_META = '10–17 · VIS';

    /** @var array<string, string> */
    private const PHONETIC = [
        '0' => 'Zero',
        '1' => 'One',
        '2' => 'Two',
        '3' => 'Three',
        '4' => 'Four',
        '5' => 'Five',
        '6' => 'Six',
        '7' => 'Seven',
        '8' => 'Eight',
        '9' => 'Nine',
        'A' => 'Alpha',
        'B' => 'Bravo',
        'C' => 'Charlie',
        'D' => 'Delta',
        'E' => 'Echo',
        'F' => 'Foxtrot',
        'G' => 'Golf',
        'H' => 'Hotel',
        'I' => 'India',
        'J' => 'Juliet',
        'K' => 'Kilo',
        'L' => 'Lima',
        'M' => 'Mike',
        'N' => 'November',
        'O' => 'Oscar',
        'P' => 'Papa',
        'Q' => 'Quebec',
        'R' => 'Romeo',
        'S' => 'Sierra',
        'T' => 'Tango',
        'U' => 'Uniform',
        'V' => 'Victor',
        'W' => 'Whiskey',
        'X' => 'X-ray',
        'Y' => 'Yankee',
        'Z' => 'Zulu',
    ];

    public static function normalize(?string $vin): ?string
    {
        if (! filled($vin)) {
            return null;
        }

        return strtoupper(trim($vin));
    }

    public static function phonetic(string $char): string
    {
        $normalized = strtoupper($char);

        return self::PHONETIC[$normalized] ?? $normalized;
    }

    /**
     * @return array{vin: string, prefix: string, suffix: string, suffix_start: int, chars: list<string>, phonetic_chars: list<string>}|null
     */
    public static function parts(?string $vin): ?array
    {
        $normalized = self::normalize($vin);

        if ($normalized === null) {
            return null;
        }

        $length = strlen($normalized);
        $suffixLength = min(self::SUFFIX_LENGTH, $length);
        $prefix = $length > $suffixLength ? substr($normalized, 0, $length - $suffixLength) : '';
        $suffix = substr($normalized, -$suffixLength);
        $chars = str_split($normalized);

        return [
            'vin' => $normalized,
            'prefix' => $prefix,
            'suffix' => $suffix,
            'suffix_start' => $length - $suffixLength,
            'chars' => $chars,
            'phonetic_chars' => array_map(fn (string $char): string => self::phonetic($char), $chars),
        ];
    }

    /**
     * @return list<array{key: string, label: string, meta: string, chars: list<string>, phonetic_chars: list<string>, is_serial: bool}>|null
     */
    public static function sections(?string $vin): ?array
    {
        $parts = self::parts($vin);

        if ($parts === null) {
            return null;
        }

        $chars = $parts['chars'];
        $phoneticChars = $parts['phonetic_chars'];
        $length = count($chars);
        $sections = [];

        $wmiLength = min(self::WMI_LENGTH, $length);
        if ($wmiLength > 0) {
            $sections[] = [
                'key' => 'wmi',
                'label' => self::WMI_LABEL,
                'meta' => self::WMI_META,
                'chars' => array_slice($chars, 0, $wmiLength),
                'phonetic_chars' => array_slice($phoneticChars, 0, $wmiLength),
                'is_serial' => false,
            ];
        }

        if ($length > self::WMI_LENGTH) {
            $vdsLength = min(self::VDS_LENGTH, $length - self::WMI_LENGTH);
            $sections[] = [
                'key' => 'vds',
                'label' => self::VDS_LABEL,
                'meta' => self::VDS_META,
                'chars' => array_slice($chars, self::WMI_LENGTH, $vdsLength),
                'phonetic_chars' => array_slice($phoneticChars, self::WMI_LENGTH, $vdsLength),
                'is_serial' => false,
            ];
        }

        if ($length > self::WMI_LENGTH + self::VDS_LENGTH) {
            $sections[] = [
                'key' => 'vis',
                'label' => self::SERIAL_LABEL,
                'meta' => self::SERIAL_META,
                'chars' => array_slice($chars, self::WMI_LENGTH + self::VDS_LENGTH),
                'phonetic_chars' => array_slice($phoneticChars, self::WMI_LENGTH + self::VDS_LENGTH),
                'is_serial' => true,
            ];
        }

        return $sections;
    }
}
