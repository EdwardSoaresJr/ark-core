<?php

namespace App\Ark\Operations\RepairOrders;

/**
 * Normalize advisor-entered estimate labels for storage and display.
 *
 * Some legacy/import paths stored "%" where advisors meant "&" between words
 * (e.g. "Front Brakes % Rotors"). Percent values like "50% worn" are untouched.
 */
final class RepairOrderFreeText
{
    public static function normalize(?string $value): string
    {
        $value = trim(html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if ($value === '') {
            return '';
        }

        $value = preg_replace('/(?<=\p{L})\s+%\s+(?=\p{L})/u', ' & ', $value) ?? $value;

        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    public static function normalizeSnapshot(array $snapshot): array
    {
        $concerns = $snapshot['concerns'] ?? null;

        if (! is_array($concerns)) {
            return $snapshot;
        }

        foreach ($concerns as $concernIndex => $concern) {
            if (! is_array($concern)) {
                continue;
            }

            if (isset($concern['summary'])) {
                $concern['summary'] = self::normalize((string) $concern['summary']);
            }

            $lines = $concern['lines'] ?? null;

            if (is_array($lines)) {
                foreach ($lines as $lineIndex => $line) {
                    if (! is_array($line)) {
                        continue;
                    }

                    if (isset($line['description'])) {
                        $line['description'] = self::normalize((string) $line['description']);
                    }

                    if (isset($line['customer_description'])) {
                        $line['customer_description'] = self::normalize((string) $line['customer_description']);
                    }

                    $lines[$lineIndex] = $line;
                }

                $concern['lines'] = $lines;
            }

            $workGroups = $concern['work_groups'] ?? null;

            if (is_array($workGroups)) {
                foreach ($workGroups as $groupIndex => $workGroup) {
                    if (! is_array($workGroup)) {
                        continue;
                    }

                    if (isset($workGroup['title'])) {
                        $workGroup['title'] = self::normalize((string) $workGroup['title']);
                    }

                    $groupLines = $workGroup['lines'] ?? null;

                    if (is_array($groupLines)) {
                        foreach ($groupLines as $lineIndex => $line) {
                            if (! is_array($line)) {
                                continue;
                            }

                            if (isset($line['description'])) {
                                $line['description'] = self::normalize((string) $line['description']);
                            }

                            if (isset($line['customer_description'])) {
                                $line['customer_description'] = self::normalize((string) $line['customer_description']);
                            }

                            $groupLines[$lineIndex] = $line;
                        }

                        $workGroup['lines'] = $groupLines;
                    }

                    $workGroups[$groupIndex] = $workGroup;
                }

                $concern['work_groups'] = $workGroups;
            }

            $concerns[$concernIndex] = $concern;
        }

        $snapshot['concerns'] = $concerns;

        return $snapshot;
    }
}
