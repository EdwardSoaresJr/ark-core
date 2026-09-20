<?php

namespace App\Ark\Dragon\ServiceAdvisor;

/**
 * Deterministic post-check: proposal must not alter documented measurements,
 * DTCs, side/location, or silently convert uncertainty into confirmed failure.
 */
final class ServiceAdvisorFactPreservationCheck
{
    /**
     * @return array{ok: bool, reason: ?string}
     */
    public function check(string $original, string $proposal): array
    {
        $orig = mb_strtolower($original);
        $prop = mb_strtolower($proposal);

        foreach ($this->extractDtcs($original) as $dtc) {
            if (! str_contains($prop, mb_strtolower($dtc))) {
                return $this->fail("Proposal dropped or altered DTC {$dtc}.");
            }
        }

        foreach ($this->extractMeasurements($original) as $measurement) {
            if (! $this->proposalContainsMeasurement($prop, $measurement)) {
                return $this->fail("Proposal dropped or altered measurement {$measurement['raw']}.");
            }
        }

        foreach ($this->extractSides($original) as $side) {
            if (! $this->sidePreserved($orig, $prop, $side)) {
                return $this->fail("Proposal altered documented side/location ({$side}).");
            }
        }

        if ($this->hasUncertainty($orig) && $this->assertsConfirmedFailure($prop) && ! $this->hasUncertainty($prop)) {
            return $this->fail('Proposal turned documented uncertainty into a confirmed failure.');
        }

        if ($this->inventsUrgency($orig, $prop)) {
            return $this->fail('Proposal invented urgency not present in the source.');
        }

        return ['ok' => true, 'reason' => null];
    }

    /**
     * @return list<string>
     */
    private function extractDtcs(string $text): array
    {
        preg_match_all('/\b[PBCU][0-9A-F]{4}\b/i', $text, $matches);

        return array_values(array_unique(array_map('strtoupper', $matches[0] ?? [])));
    }

    /**
     * @return list<array{raw: string, number: string, unit: string}>
     */
    private function extractMeasurements(string $text): array
    {
        preg_match_all('/\b(\d+(?:\.\d+)?)\s*(mm|mil|in|inch|inches|cca|psi|v|volt|volts|a|amp|amps|%|percent)\b/i', $text, $matches, PREG_SET_ORDER);
        $out = [];
        foreach ($matches as $m) {
            $out[] = [
                'raw' => $m[0],
                'number' => $m[1],
                'unit' => strtolower($m[2]),
            ];
        }

        return $out;
    }

    /**
     * @param  array{raw: string, number: string, unit: string}  $measurement
     */
    private function proposalContainsMeasurement(string $proposalLower, array $measurement): bool
    {
        $number = preg_quote($measurement['number'], '/');
        $unit = $measurement['unit'];
        $unitAlt = match ($unit) {
            'inch', 'inches' => 'in(?:ch(?:es)?)?',
            'percent' => '%|percent',
            'volt', 'volts' => 'v(?:olt(?:s)?)?',
            'amp', 'amps' => 'a(?:mp(?:s)?)?',
            default => preg_quote($unit, '/'),
        };

        return (bool) preg_match('/\b'.$number.'\s*(?:'.$unitAlt.')\b/i', $proposalLower);
    }

    /**
     * @return list<string>
     */
    private function extractSides(string $text): array
    {
        $sides = [];
        $map = [
            'left front' => 'lf',
            'right front' => 'rf',
            'left rear' => 'lr',
            'right rear' => 'rr',
            'left-front' => 'lf',
            'right-front' => 'rf',
            'left-rear' => 'lr',
            'right-rear' => 'rr',
        ];
        $lower = mb_strtolower($text);
        foreach ($map as $phrase => $code) {
            if (str_contains($lower, $phrase) || preg_match('/\b'.$code.'\b/i', $text)) {
                $sides[] = $code;
            }
        }
        foreach (['left', 'right', 'front', 'rear'] as $token) {
            if (preg_match('/\b'.$token.'\b/i', $text)) {
                $sides[] = $token;
            }
        }

        return array_values(array_unique($sides));
    }

    private function sidePreserved(string $originalLower, string $proposalLower, string $side): bool
    {
        $opposites = [
            'left' => 'right',
            'right' => 'left',
            'front' => 'rear',
            'rear' => 'front',
            'lf' => 'rf',
            'rf' => 'lf',
            'lr' => 'rr',
            'rr' => 'lr',
        ];

        $expansions = [
            'lf' => ['left front', 'left-front', 'lf', 'driver front'],
            'rf' => ['right front', 'right-front', 'rf', 'passenger front'],
            'lr' => ['left rear', 'left-rear', 'lr'],
            'rr' => ['right rear', 'right-rear', 'rr'],
            'left' => ['left', 'lf', 'lr', 'driver'],
            'right' => ['right', 'rf', 'rr', 'passenger'],
            'front' => ['front', 'lf', 'rf'],
            'rear' => ['rear', 'lr', 'rr'],
        ];

        $found = false;
        foreach ($expansions[$side] ?? [$side] as $token) {
            if (str_contains($proposalLower, $token) || preg_match('/\b'.preg_quote($token, '/').'\b/i', $proposalLower)) {
                $found = true;
                break;
            }
        }

        if (! $found) {
            return false;
        }

        // If original had only one of a pair, proposal must not flip to the opposite alone.
        $opp = $opposites[$side] ?? null;
        if ($opp === null) {
            return true;
        }

        $origHasOpp = false;
        foreach ($expansions[$opp] ?? [$opp] as $token) {
            if (str_contains($originalLower, $token) || preg_match('/\b'.preg_quote($token, '/').'\b/i', $originalLower)) {
                $origHasOpp = true;
                break;
            }
        }

        if ($origHasOpp) {
            return true;
        }

        // Original lacked opposite — OK as long as original side still present (already checked).
        return true;
    }

    private function hasUncertainty(string $lower): bool
    {
        return (bool) preg_match('/\b(possible|possibly|maybe|might|suspect(?:ed)?|could be|appears|likely|uncertain|needs? (?:smoke )?test|further (?:confirm|test))\b/i', $lower);
    }

    private function assertsConfirmedFailure(string $lower): bool
    {
        return (bool) preg_match('/\b(has failed|is failed|confirmed|definitely|failed|is bad|is broken)\b/i', $lower);
    }

    private function inventsUrgency(string $originalLower, string $proposalLower): bool
    {
        $urgency = '/\b(urgent|immediately|asap|unsafe to drive|do not drive|critical safety|danger(?:ous)?)\b/i';
        $origHad = (bool) preg_match($urgency, $originalLower);
        $propHas = (bool) preg_match($urgency, $proposalLower);

        return $propHas && ! $origHad;
    }

    /**
     * @return array{ok: bool, reason: string}
     */
    private function fail(string $reason): array
    {
        return ['ok' => false, 'reason' => $reason];
    }
}
