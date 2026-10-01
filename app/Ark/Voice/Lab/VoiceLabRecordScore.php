<?php

namespace App\Ark\Voice\Lab;

/**
 * Record accuracy is not conversational BLEU. Swapped laterality is a fail.
 */
final class VoiceLabRecordScore
{
    /**
     * @param  list<array{side: string, axle: string, value: string, unit: string}>  $expectedFacts
     * @return array{record_accurate: bool, conversational_ok: bool, laterality_swap_suspected: bool, extracted: list<array{side: string, axle: string, value: string, unit: string}>}
     */
    public function score(array $expectedFacts, string $transcript): array
    {
        $normalized = $this->normalize($transcript);
        $extracted = $this->extractCornerMeasurements($normalized);

        return [
            'record_accurate' => $this->factsMatch($expectedFacts, $extracted),
            'conversational_ok' => $this->conversationalOverlap($expectedFacts, $normalized),
            'laterality_swap_suspected' => $this->lateralitySwapSuspected($expectedFacts, $extracted),
            'extracted' => $extracted,
        ];
    }

    /**
     * Gold phrase: "Right rear two millimeters, left rear three."
     *
     * @return list<array{side: string, axle: string, value: string, unit: string}>
     */
    public static function goldRearPadFacts(): array
    {
        return [
            ['side' => 'right', 'axle' => 'rear', 'value' => '2', 'unit' => 'mm'],
            ['side' => 'left', 'axle' => 'rear', 'value' => '3', 'unit' => 'mm'],
        ];
    }

    public function normalize(string $transcript): string
    {
        $text = strtolower($transcript);
        $text = str_replace(['-', ',', '.', ';', ':'], ' ', $text);
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $mapped = [];

        foreach ($words as $word) {
            $mapped[] = match ($word) {
                'two' => '2',
                'three' => '3',
                'four' => '4',
                'five' => '5',
                'six' => '6',
                'millimeters', 'millimeter', 'millimetres', 'mm' => 'mm',
                'psi' => 'psi',
                default => $word,
            };
        }

        return trim(implode(' ', $mapped));
    }

    /**
     * @return list<array{side: string, axle: string, value: string, unit: string}>
     */
    public function extractCornerMeasurements(string $normalized): array
    {
        $facts = [];

        if (preg_match_all(
            '/\b(left|right)\s+(front|rear)\s+(\d+(?:\s+point\s+\d+)?)\s*(mm|psi)?\b/',
            $normalized,
            $matches,
            PREG_SET_ORDER,
        ) === false) {
            return [];
        }

        foreach ($matches as $match) {
            $facts[] = [
                'side' => $match[1],
                'axle' => $match[2],
                'value' => str_replace(' ', '', str_replace('point', '.', $match[3])),
                'unit' => ($match[4] ?? '') === '' ? 'mm' : $match[4],
            ];
        }

        return $facts;
    }

    /**
     * @param  list<array{side: string, axle: string, value: string, unit: string}>  $expected
     * @param  list<array{side: string, axle: string, value: string, unit: string}>  $extracted
     */
    private function factsMatch(array $expected, array $extracted): bool
    {
        if (count($expected) === 0 || count($extracted) !== count($expected)) {
            return false;
        }

        $normalizeFact = static fn (array $fact): string => $fact['side'].'|'.$fact['axle'].'|'.$fact['value'].'|'.$fact['unit'];

        $expectedKeys = array_map($normalizeFact, $expected);
        $extractedKeys = array_map($normalizeFact, $extracted);
        sort($expectedKeys);
        sort($extractedKeys);

        return $expectedKeys === $extractedKeys;
    }

    /**
     * @param  list<array{side: string, axle: string, value: string, unit: string}>  $expected
     * @param  list<array{side: string, axle: string, value: string, unit: string}>  $extracted
     */
    private function lateralitySwapSuspected(array $expected, array $extracted): bool
    {
        if (count($expected) < 2 || count($extracted) < 2) {
            return false;
        }

        $expectedValues = array_column($expected, 'value');
        $extractedValues = array_column($extracted, 'value');
        sort($expectedValues);
        sort($extractedValues);

        if ($expectedValues !== $extractedValues) {
            return false;
        }

        return ! $this->factsMatch($expected, $extracted);
    }

    /**
     * @param  list<array{side: string, axle: string, value: string, unit: string}>  $expected
     */
    private function conversationalOverlap(array $expected, string $normalized): bool
    {
        foreach ($expected as $fact) {
            if (! str_contains($normalized, $fact['side']) || ! str_contains($normalized, $fact['axle'])) {
                return false;
            }
            if (! str_contains($normalized, $fact['value'])) {
                return false;
            }
        }

        return true;
    }
}
