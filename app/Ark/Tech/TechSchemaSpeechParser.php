<?php

namespace App\Ark\Tech;

/**
 * Maps an utterance onto the current item's number slots. Slot keys come from the template.
 */
final class TechSchemaSpeechParser
{
    /**
     * @param  list<array{key: string, name?: string, unit?: ?string, aliases?: list<string>}>|null  $slots  null = legacy paired names
     * @return array{measurements: list<array{name: string, value: string, unit: string}>, rotor_condition: ?string, finding: ?string, condition: ?string}
     */
    public function parse(string $transcript, ?array $slots = null): array
    {
        $normalized = strtolower($transcript);
        $normalized = str_replace(['-', ',', '.', ';'], ' ', $normalized);
        $words = preg_split('/\s+/', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $mapped = [];
        foreach ($words as $word) {
            $mapped[] = match ($word) {
                'one' => '1',
                'two' => '2',
                'three' => '3',
                'four' => '4',
                'five' => '5',
                'six' => '6',
                'seven' => '7',
                'eight' => '8',
                'nine' => '9',
                'ten' => '10',
                'millimeters', 'millimeter', 'mm' => 'mm',
                default => $word,
            };
        }
        $text = implode(' ', $mapped);

        $effectiveSlots = $slots === null ? $this->legacyPairedSlots() : $slots;
        $measurements = [];

        foreach ($effectiveSlots as $slot) {
            $key = (string) ($slot['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $aliases = $slot['aliases'] ?? self::aliasesForSlot($key, (string) ($slot['name'] ?? ''));
            $value = $this->firstNumberForAliases($text, $aliases);
            if ($value === null) {
                continue;
            }
            $unit = (string) ($slot['unit'] ?? 'mm');
            if ($unit === '') {
                $unit = 'mm';
            }
            $measurements[] = [
                'name' => $key,
                'value' => $value,
                'unit' => $unit,
            ];
        }

        $rotor = str_contains($text, 'groov') ? 'grooved' : null;
        $finding = trim($transcript);

        return [
            'measurements' => $measurements,
            'rotor_condition' => $rotor,
            'finding' => $finding !== '' ? $finding : null,
            'condition' => $this->guessCondition($text),
        ];
    }

    /**
     * @return list<string>
     */
    public static function aliasesForSlot(string $key, string $name = ''): array
    {
        $aliases = [];
        $keyLower = strtolower(trim($key));
        $nameLower = strtolower(trim($name));
        if ($keyLower !== '') {
            $aliases[] = $keyLower;
            $aliases[] = str_replace('_', ' ', $keyLower);
        }
        if ($nameLower !== '') {
            $aliases[] = $nameLower;
        }

        $compact = str_replace(['_', '-', ' '], '', $keyLower.$nameLower);
        if (in_array($keyLower, ['lf', 'lf_psi'], true) || str_contains($compact, 'leftfront')) {
            array_push($aliases, 'left front', 'lf');
        }
        if (in_array($keyLower, ['rf', 'rf_psi'], true) || str_contains($compact, 'rightfront')) {
            array_push($aliases, 'right front', 'rf');
        }
        if (in_array($keyLower, ['lr', 'lr_psi'], true) || str_contains($compact, 'leftrear')) {
            array_push($aliases, 'left rear', 'lr');
        }
        if (in_array($keyLower, ['rr', 'rr_psi'], true) || str_contains($compact, 'rightrear')) {
            array_push($aliases, 'right rear', 'rr');
        }
        if ($keyLower === 'l' || $keyLower === 'left') {
            $aliases[] = 'left';
        }
        if ($keyLower === 'r' || $keyLower === 'right') {
            $aliases[] = 'right';
        }
        if (str_contains($compact, 'inner')) {
            $aliases[] = 'inner';
        }
        if (str_contains($compact, 'outer')) {
            $aliases[] = 'outer';
        }

        return array_values(array_unique(array_filter($aliases)));
    }

    /**
     * @param  list<string>  $aliases
     */
    private function firstNumberForAliases(string $text, array $aliases): ?string
    {
        usort($aliases, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        foreach ($aliases as $alias) {
            $quoted = preg_quote($alias, '/');
            if (preg_match('/\b'.$quoted.'\s+(?:is\s+)?(\d+)\b/', $text, $match)) {
                return $match[1];
            }
            if (str_contains($alias, ' ') && preg_match('/\b(\d+)\s*(?:mm|\/32|psi)?\s+(?:on\s+(?:the\s+)?)?'.$quoted.'\b/', $text, $match)) {
                return $match[1];
            }
        }

        return null;
    }

    private function guessCondition(string $text): ?string
    {
        if (str_contains($text, 'needs attention') || str_contains($text, 'need attention')) {
            return 'needs_attention';
        }
        if (preg_match('/\bmonitor\b/', $text)) {
            return 'monitor';
        }
        if (preg_match('/\bgood\b/', $text)) {
            return 'good';
        }

        return null;
    }

    /**
     * @return list<array{key: string, name: string, unit: string}>
     */
    private function legacyPairedSlots(): array
    {
        return [
            ['key' => 'LF', 'name' => 'Left front', 'unit' => 'mm'],
            ['key' => 'RF', 'name' => 'Right front', 'unit' => 'mm'],
            ['key' => 'LR', 'name' => 'Left rear', 'unit' => 'mm'],
            ['key' => 'RR', 'name' => 'Right rear', 'unit' => 'mm'],
            ['key' => 'L', 'name' => 'Left', 'unit' => 'mm'],
            ['key' => 'R', 'name' => 'Right', 'unit' => 'mm'],
        ];
    }
}
