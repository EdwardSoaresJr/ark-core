<?php

namespace App\Ark\Operations\Intake;

use Illuminate\Support\Str;

class IntakeConcernParser
{
    /**
     * @return array{summary: string, customer_states: string}|null
     */
    public function parseRow(string $customerStates): ?array
    {
        $text = trim($customerStates);

        if ($text === '') {
            return null;
        }

        $firstLine = trim(strtok($text, "\r\n") ?: $text);

        return [
            'summary' => Str::limit($this->summaryForLine($firstLine !== '' ? $firstLine : $text), 255, ''),
            'customer_states' => $text,
        ];
    }

    /**
     * @return list<array{summary: string, customer_states: string}>
     */
    public function parse(string $customerStates): array
    {
        $text = trim($customerStates);

        if ($text === '') {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
        $concerns = [];

        foreach ($lines as $line) {
            $line = trim((string) $line);
            $line = preg_replace('/^[\-\*•]\s*/u', '', $line) ?? $line;
            $line = preg_replace('/^\d+[\.\)]\s*/', '', $line) ?? $line;
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $summary = $this->summaryForLine($line);

            $concerns[] = [
                'summary' => Str::limit($summary, 255, ''),
                'customer_states' => $line,
            ];
        }

        if ($concerns !== []) {
            return $concerns;
        }

        return [[
            'summary' => Str::limit($text, 255, ''),
            'customer_states' => $text,
        ]];
    }

    private function summaryForLine(string $line): string
    {
        if (preg_match('/^(maintenance|customer states|complaint|concern)\s*:\s*(.+)$/i', $line, $matches) === 1) {
            return trim($matches[2]);
        }

        return $line;
    }
}
