<?php

namespace App\Ark\Operations\Parts;

use RuntimeException;

/**
 * Parses dealer quote text into supplier/header metadata and part lines.
 * Tuned for VW / Penkhus-style quotes; also handles common tabular paste formats.
 */
final class DealerQuoteParser
{
    /**
     * @return array{
     *     supplier_name: ?string,
     *     quote_number: ?string,
     *     vehicle_description: ?string,
     *     vin: ?string,
     *     dealer_total_cents: ?int,
     *     lines: list<array{
     *         source_key: string,
     *         quantity: string,
     *         part_number: ?string,
     *         description: string,
     *         part_cost: string,
     *         unit_cost_cents: int,
     *         extended_cost_cents: ?int
     *     }>
     * }
     */
    public function parse(string $rawText): array
    {
        $text = $this->normalize($rawText);

        if ($text === '') {
            throw new RuntimeException('Nothing to analyze.');
        }

        $lines = $this->parseLines($text);

        if ($lines === []) {
            throw new RuntimeException('No part lines detected. Paste rows like: 1  06J-103-603-BD  Upper Oil Sump  406.08');
        }

        return [
            'supplier_name' => $this->detectSupplier($text),
            'quote_number' => $this->detectQuoteNumber($text),
            'vehicle_description' => $this->detectVehicle($text),
            'vin' => $this->detectVin($text),
            'dealer_total_cents' => $this->detectDealerTotal($text, $lines),
            'lines' => $lines,
        ];
    }

    private function normalize(string $rawText): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $rawText);
        $text = str_replace(['—', '–', '−', '‐', '‑'], '-', $text);
        $text = preg_replace('/-{2,}/', '-', $text) ?? $text;
        $text = preg_replace("/[ \t]+/u", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/u", "\n\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * @return list<array{
     *     source_key: string,
     *     quantity: string,
     *     part_number: ?string,
     *     description: string,
     *     part_cost: string,
     *     unit_cost_cents: int,
     *     extended_cost_cents: ?int
     * }>
     */
    private function parseLines(string $text): array
    {
        $rows = [];
        $index = 0;

        foreach (explode("\n", $text) as $line) {
            $line = trim($line);

            if ($line === '' || $this->isNoiseLine($line)) {
                continue;
            }

            $parsed = $this->parseLine($line);

            if ($parsed === null) {
                continue;
            }

            $index++;
            $rows[] = [
                'source_key' => 'preview:'.$index,
                'quantity' => $parsed['quantity'],
                'part_number' => $parsed['part_number'],
                'description' => $parsed['description'],
                'part_cost' => $this->centsToDecimal($parsed['unit_cost_cents']),
                'unit_cost_cents' => $parsed['unit_cost_cents'],
                'extended_cost_cents' => $parsed['extended_cost_cents'],
            ];
        }

        return $rows;
    }

    /**
     * @return array{
     *     quantity: string,
     *     part_number: ?string,
     *     description: string,
     *     unit_cost_cents: int,
     *     extended_cost_cents: ?int
     * }|null
     */
    private function parseLine(string $line): ?array
    {
        $cdk = $this->parseCdkOcrLine($line);

        if ($cdk !== null) {
            return $cdk;
        }

        // Qty · Part# · Description · Unit · [Ext]
        if (preg_match(
            '/^(\d+(?:\.\d+)?)\s+([A-Z0-9][A-Z0-9.\-\/]{2,})\s+(.+?)\s+(\d{1,3}(?:,\d{3})*(?:\.\d{2})|\d+\.\d{2})(?:\s+(\d{1,3}(?:,\d{3})*(?:\.\d{2})|\d+\.\d{2}))?\s*$/i',
            $line,
            $matches,
        )) {
            $unit = $this->moneyToCents($matches[4]);
            $extended = isset($matches[5]) && $matches[5] !== ''
                ? $this->moneyToCents($matches[5])
                : null;

            return [
                'quantity' => $this->normalizeQuantity($matches[1]),
                'part_number' => $this->normalizePartNumber(strtoupper(trim($matches[2]))),
                'description' => $this->cleanDescription($matches[3]),
                'unit_cost_cents' => $unit,
                'extended_cost_cents' => $extended,
            ];
        }

        // Qty · Description · Unit (no part number)
        if (preg_match(
            '/^(\d+(?:\.\d+)?)\s+([A-Za-z].+?)\s+(\d{1,3}(?:,\d{3})*(?:\.\d{2})|\d+\.\d{2})\s*$/u',
            $line,
            $matches,
        )) {
            $description = $this->cleanDescription($matches[2]);

            if (strlen($description) < 3 || $this->looksLikeHeader($description)) {
                return null;
            }

            return [
                'quantity' => $this->normalizeQuantity($matches[1]),
                'part_number' => null,
                'description' => $description,
                'unit_cost_cents' => $this->moneyToCents($matches[3]),
                'extended_cost_cents' => null,
            ];
        }

        return null;
    }

    /**
     * CDK / Penkhus scanned invoice rows (OCR):
     * qty … PART-NUMBER … DESC … list net ext
     *
     * @return array{
     *     quantity: string,
     *     part_number: ?string,
     *     description: string,
     *     unit_cost_cents: int,
     *     extended_cost_cents: ?int
     * }|null
     */
    private function parseCdkOcrLine(string $line): ?array
    {
        if (! preg_match('/\d+\.\d{2}/', $line)) {
            return null;
        }

        // Skip supersession footnotes and policy copy.
        if (preg_match('/\breplaces\b|\breturn(?:s|able)?\b|\bcopyright\b|\bdo not\)?\s*pay\b/i', $line)) {
            return null;
        }

        $cleaned = str_replace(['|', ')', '(', '«', '»', '°', '©', '\\', ','], ' ', $line);
        $cleaned = preg_replace('/\s+/', ' ', $cleaned) ?? $cleaned;
        $cleaned = trim($cleaned);

        if (! preg_match(
            '/\b((?:[OJIl]?[0-9O][A-Z0-9]{1,3}|[IN]?N|[DW]HT|WHT|D|[OW]?[0-9]{2,3})-[A-Z0-9][A-Z0-9.\-]{2,})\b/i',
            $cleaned,
            $partMatch,
            PREG_OFFSET_CAPTURE,
        )) {
            // WHT OCR often breaks the hyphen: "WHT s005-2.27" or "WHT-005-2227"
            if (preg_match('/\b(WHT)\s*[Ss]?(\d{3})[-.]?(\d+)\b/i', $cleaned, $wht, PREG_OFFSET_CAPTURE)) {
                $rawPart = 'WHT-'.$wht[2][0].'-'.$wht[3][0];
                $partOffset = (int) $wht[0][1];
                $rawMatchLen = strlen($wht[0][0]);
            } else {
                return null;
            }
        } else {
            $rawPart = $partMatch[1][0];
            $partOffset = (int) $partMatch[1][1];
            $rawMatchLen = strlen($rawPart);
        }

        $partNumber = $this->normalizePartNumber(strtoupper($rawPart));

        if ($partNumber === null || strlen($partNumber) < 5) {
            return null;
        }

        $before = trim(substr($cleaned, 0, $partOffset));
        $after = trim(substr($cleaned, $partOffset + $rawMatchLen));

        if (! preg_match_all('/\d+\.\d{2}/', $after, $moneyMatches)) {
            return null;
        }

        $amounts = $moneyMatches[0];

        if ($amounts === []) {
            return null;
        }

        $extendedDecimal = (float) $amounts[count($amounts) - 1];
        $netDecimal = count($amounts) >= 2
            ? (float) $amounts[count($amounts) - 2]
            : $extendedDecimal;

        if ($netDecimal <= 0) {
            return null;
        }

        $extendedCents = (int) round($extendedDecimal * 100);
        $unitCents = (int) round($netDecimal * 100);

        $quantity = $this->inferQuantity($before, $netDecimal, $extendedDecimal);

        if ($quantity <= 0) {
            return null;
        }

        $description = $this->descriptionFromCdkTail($after, $amounts);

        if ($description === '') {
            $description = $partNumber;
        }

        return [
            'quantity' => $this->normalizeQuantity((string) $quantity),
            'part_number' => $partNumber,
            'description' => $description,
            'unit_cost_cents' => $unitCents,
            'extended_cost_cents' => $extendedCents,
        ];
    }

    private function inferQuantity(string $before, float $net, float $extended): float
    {
        $leading = null;

        if (preg_match('/^(\d{1,3})\b/', trim($before), $matches) && (int) $matches[1] > 0) {
            $leading = (float) $matches[1];
        }

        if ($net > 0) {
            $fromMoney = $extended / $net;

            if ($fromMoney >= 0.95 && abs($fromMoney - round($fromMoney)) < 0.08) {
                $rounded = (float) max(1, (int) round($fromMoney));

                // Single money amount (net == ext) means unit cost only — trust leading qty.
                if ($leading !== null && abs($extended - $net) < 0.02) {
                    return $leading;
                }

                return $rounded;
            }
        }

        if ($leading !== null) {
            return $leading;
        }

        return abs($extended - $net) < 0.02 ? 1.0 : 0.0;
    }

    /**
     * @param  list<string>  $amounts
     */
    private function descriptionFromCdkTail(string $after, array $amounts): string
    {
        $desc = $after;

        foreach (array_reverse($amounts) as $amount) {
            $pos = strrpos($desc, $amount);

            if ($pos !== false) {
                $desc = trim(substr($desc, 0, $pos));
            }
        }

        // Drop bin / location codes like 1031D, 2061, 1083B before the description word.
        $desc = preg_replace('/\b\d{3,4}[A-Z]?\b/i', ' ', $desc) ?? $desc;
        $desc = preg_replace('/\b[Oo]\b/', ' ', $desc) ?? $desc;
        $desc = preg_replace('/^[^A-Za-z]+/', '', $desc) ?? $desc;
        $desc = preg_replace('/\b[a-z]\b/', ' ', $desc) ?? $desc;
        $desc = preg_replace('/[^A-Za-z0-9 \/\-]+/', ' ', $desc) ?? $desc;
        $desc = trim(preg_replace('/\s+/', ' ', $desc) ?? $desc);

        return $this->cleanDescription($desc);
    }

    private function normalizePartNumber(string $partNumber): ?string
    {
        $part = strtoupper(trim($partNumber));
        $part = str_replace([' ', '_'], '', $part);
        $part = preg_replace('/[^A-Z0-9.\-]/', '', $part) ?? $part;

        // OCR prefixes: JO6J / O6J / Il06J → 06J ; IN-910 → N-910
        $part = preg_replace('/^[JIL]*O(?=\d)/', '0', $part) ?? $part;
        $part = preg_replace('/^[JIL]+(?=\d)/', '', $part) ?? $part;
        $part = preg_replace('/^O(?=\d)/', '0', $part) ?? $part;
        $part = preg_replace('/^IN-/', 'N-', $part) ?? $part;
        $part = preg_replace('/^I(?=N-)/', '', $part) ?? $part;

        // Trailing letter O/l confusion: -Al / -AL → -A1
        $part = preg_replace_callback(
            '/-([A-Z])[LI]$/',
            fn (array $m): string => '-'.$m[1].'1',
            $part,
        ) ?? $part;

        if (! preg_match('/[A-Z0-9]-/', $part) && ! preg_match('/^\d{2,}[A-Z]/', $part)) {
            return null;
        }

        return $part !== '' ? $part : null;
    }

    private function isNoiseLine(string $line): bool
    {
        $lower = strtolower($line);

        if ($this->looksLikeHeader($line)) {
            return true;
        }

        foreach ([
            'subtotal',
            'sales tax',
            'page ',
            'continued',
            'thank you',
            'terms and',
            'return policy',
            'not returnable',
            'copyright',
            'imaging customer',
            'do not pay',
            'replaces ',
            'ship via',
            'account no',
        ] as $needle) {
            if (str_contains($lower, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function looksLikeHeader(string $line): bool
    {
        $compact = strtolower(preg_replace('/\s+/', ' ', $line) ?? $line);

        return (bool) preg_match(
            '/^(qty|quantity|part\s*(#|no|number)|description|unit\s*price|ext(\.|ended)?|amount|item)\b/',
            $compact,
        );
    }

    private function detectSupplier(string $text): ?string
    {
        if (preg_match('/\b((?:Bob\s+)?Penkhus(?:\s+Volkswagen|\s+VW)?)\b/i', $text, $matches)) {
            return $this->titleCase(trim($matches[1]));
        }

        if (preg_match('/^(?:from|supplier|dealer|vendor)\s*[:\-]\s*(.+)$/im', $text, $matches)) {
            return $this->truncate(trim($matches[1]), 120);
        }

        $firstLines = array_values(array_filter(array_map('trim', explode("\n", $text))));

        foreach (array_slice($firstLines, 0, 8) as $line) {
            if (
                strlen($line) >= 8
                && strlen($line) <= 80
                && ! preg_match('/\d{2,}/', $line)
                && preg_match('/\b(volkswagen|vw|honda|toyota|ford|bmw|audi|dealer|motors|automotive)\b/i', $line)
            ) {
                return $this->truncate($line, 120);
            }
        }

        return null;
    }

    private function detectQuoteNumber(string $text): ?string
    {
        // Penkhus / CDK header: "NUMBER Q19696"
        if (preg_match('/\bNUMBER\s+([Q0]\d{4,})\b/i', $text, $matches)) {
            return $this->normalizeQuoteNumber(strtoupper(trim($matches[1])));
        }

        if (preg_match('/\b(?:invoice|quote|quotation|order)\s*(?:#|no\.?|number)?\s*[:\-]?\s*([Q0]\d{4,})\b/i', $text, $matches)) {
            return $this->normalizeQuoteNumber(strtoupper(trim($matches[1])));
        }

        // OCR often confuses Q with 0 in quote numbers like Q19696.
        if (preg_match('/\b([Q0]\d{4,})\b/i', $text, $matches)) {
            return $this->normalizeQuoteNumber(strtoupper(trim($matches[1])));
        }

        return null;
    }

    private function normalizeQuoteNumber(string $value): string
    {
        if (preg_match('/^0(\d{4,})$/', $value, $matches)) {
            return 'Q'.$matches[1];
        }

        return $value;
    }

    private function detectVin(string $text): ?string
    {
        if (preg_match('/\b([A-HJ-NPR-Z0-9]{17})\b/i', $text, $matches)) {
            return strtoupper($matches[1]);
        }

        return null;
    }

    private function detectVehicle(string $text): ?string
    {
        if (preg_match('/\b((?:19|20)\d{2}\s+(?:Volkswagen|VW|Honda|Toyota|Ford|BMW|Audi|Chevrolet|GMC|Nissan|Mazda|Subaru|Hyundai|Kia)\s+[A-Za-z0-9 \-]{2,40})\b/i', $text, $matches)) {
            $candidate = trim(preg_replace('/\s+/', ' ', $matches[1]) ?? $matches[1]);

            return $this->truncate($candidate, 120);
        }

        if (preg_match('/^(?:vehicle|car)\s*[:\-]\s*(.+)$/im', $text, $matches)) {
            return $this->truncate(trim($matches[1]), 120);
        }

        return null;
    }

    /**
     * @param  list<array{unit_cost_cents: int, quantity: string, extended_cost_cents: ?int}>  $lines
     */
    private function detectDealerTotal(string $text, array $lines): ?int
    {
        if (preg_match('/\b(?:dealer\s+)?(?:total|grand\s+total|amount\s+due)\s*[:\-]?\s*\$?\s*(\d{1,3}(?:,\d{3})*(?:\.\d{2})|\d+\.\d{2})\b/i', $text, $matches)) {
            return $this->moneyToCents($matches[1]);
        }

        $sum = 0;

        foreach ($lines as $line) {
            if ($line['extended_cost_cents'] !== null) {
                $sum += $line['extended_cost_cents'];
            } else {
                $qty = (float) $line['quantity'];
                $sum += (int) round($line['unit_cost_cents'] * $qty);
            }
        }

        return $sum > 0 ? $sum : null;
    }

    private function moneyToCents(string $value): int
    {
        $normalized = str_replace([',', '$', ' '], '', trim($value));

        if (! is_numeric($normalized)) {
            throw new RuntimeException('Invalid money amount: '.$value);
        }

        return (int) round(((float) $normalized) * 100);
    }

    private function centsToDecimal(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    private function normalizeQuantity(string $quantity): string
    {
        $value = (float) $quantity;

        if (abs($value - round($value)) < 0.00001) {
            return (string) (int) round($value);
        }

        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    private function cleanDescription(string $description): string
    {
        $description = trim(preg_replace('/\s+/', ' ', $description) ?? $description);
        $description = preg_replace('/\s+(EA|EACH|PC|PCS)\s*$/i', '', $description) ?? $description;

        return $this->truncate($description, 255);
    }

    private function truncate(string $value, int $max): string
    {
        if (strlen($value) <= $max) {
            return $value;
        }

        return rtrim(substr($value, 0, $max - 1)).'…';
    }

    private function titleCase(string $value): string
    {
        return collect(preg_split('/\s+/', $value) ?: [])
            ->filter()
            ->map(function (string $word): string {
                $upper = strtoupper($word);

                if (in_array($upper, ['VW', 'BMW', 'GMC'], true)) {
                    return $upper;
                }

                return ucfirst(strtolower($word));
            })
            ->implode(' ');
    }
}
