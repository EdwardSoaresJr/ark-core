<?php

namespace App\Ark\Operations\LaborGuides\Rte;

/**
 * RTE engine pattern matching — includes known family groupings not expressible as simple wildcards.
 */
final class RteEnginePatternMatcher
{
    /** @var list<string> */
    private const GEN3_HEMI_ENGINE_PREFIXES = [
        'B803', // 5.7L
        'B863', // 6.4L
        'B860', // 6.4L variants
    ];

    public function matches(string $engineCode, string $pattern): bool
    {
        $engineCode = strtoupper(trim($engineCode));
        $pattern = strtoupper(trim($pattern));

        if ($engineCode === '' || $pattern === '') {
            return false;
        }

        $regex = '/^'.str_replace(['x', 'X'], '.*', preg_quote($pattern, '/')).'$/i';

        if (preg_match($regex, $engineCode) === 1) {
            return true;
        }

        if ($this->isGen3HemiLaborPattern($pattern)) {
            foreach (self::GEN3_HEMI_ENGINE_PREFIXES as $prefix) {
                if (str_starts_with($engineCode, $prefix)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isGen3HemiLaborPattern(string $pattern): bool
    {
        return preg_match('/^B80/i', $pattern) === 1;
    }
}
