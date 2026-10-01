<?php

namespace App\Ark\Operations\RepairOrders;

/**
 * Preserve operational specificity on scope summaries when shop vocabulary
 * returns a broader canonical label (e.g. "Rear brakes" must not become "Brakes").
 */
final class ScopeEntrySummaryResolver
{
    public static function resolve(string $selectedSummary, string $observedSummary): string
    {
        $selected = RepairOrderFreeText::normalize($selectedSummary);
        $observed = RepairOrderFreeText::normalize($observedSummary);

        if ($selected === '') {
            return $observed;
        }

        if ($observed === '' || mb_strtolower($observed) === mb_strtolower($selected)) {
            return $selected;
        }

        if (self::shouldPreferObservedOverAccidentalSubstring($observed, $selected)) {
            return $observed;
        }

        if (self::collapsedSpecificity($observed, $selected)) {
            return $observed;
        }

        return $selected;
    }

    public static function collapsedSpecificity(string $observed, string $selected): bool
    {
        $observedLower = mb_strtolower(RepairOrderFreeText::normalize($observed));
        $selectedLower = mb_strtolower(RepairOrderFreeText::normalize($selected));

        if ($observedLower === '' || $selectedLower === '') {
            return false;
        }

        if ($observedLower === $selectedLower) {
            return false;
        }

        if (! self::containsWholePhrase($observedLower, $selectedLower)) {
            return false;
        }

        if (mb_strlen($observedLower) <= mb_strlen($selectedLower)) {
            return false;
        }

        return self::hasPositionQualifier($observedLower) && ! self::hasPositionQualifier($selectedLower);
    }

    /**
     * Reject vocabulary matches that only align by accidental substring (e.g. "bra" inside "brakes").
     */
    public static function shouldPreferObservedOverAccidentalSubstring(string $observed, string $selected): bool
    {
        $observedLower = mb_strtolower(RepairOrderFreeText::normalize($observed));
        $selectedLower = mb_strtolower(RepairOrderFreeText::normalize($selected));

        if ($observedLower === '' || $selectedLower === '') {
            return false;
        }

        if (mb_strlen($observedLower) <= mb_strlen($selectedLower)) {
            return false;
        }

        if (! str_contains($observedLower, $selectedLower)) {
            return false;
        }

        return ! self::containsWholePhrase($observedLower, $selectedLower);
    }

    public static function containsWholePhrase(string $haystack, string $needle): bool
    {
        $needle = mb_strtolower(RepairOrderFreeText::normalize($needle));
        $haystack = mb_strtolower(RepairOrderFreeText::normalize($haystack));

        if ($needle === '' || $haystack === '') {
            return false;
        }

        $pattern = '/\b'.preg_quote($needle, '/').'\b/u';

        return preg_match($pattern, $haystack) === 1;
    }

    public static function hasPositionQualifier(string $text): bool
    {
        return preg_match('/\b(front|rear|left|right|lf|rf|lr|rr|driver|passenger)\b/u', mb_strtolower($text)) === 1;
    }
}
