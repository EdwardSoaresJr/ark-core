<?php

namespace App\Ark\Platform\Website;

/**
 * Apply Manage form fields onto a draft document.
 *
 * A blank input is not an edit. Absent keys, nulls, empty strings, JSON types,
 * and every other property stay as stored until a non-blank value differs.
 */
final class WebsiteDraftDocumentMerger
{
    /**
     * @param  array<string, mixed>  $document
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function merge(array $document, array $input): array
    {
        foreach ([
            'headline',
            'positioning_lede',
            'local_tagline',
            'customer_quote',
            'customer_quote_attribution',
            'response_time_hint',
            'google_reviews_url',
        ] as $key) {
            if (array_key_exists($key, $input)) {
                $document = $this->applyScalar($document, $key, $input[$key]);
            }
        }

        if (array_key_exists('google_rating', $input)) {
            $document = $this->applyScalar($document, 'google_rating', $input['google_rating'], numeric: true);
        }

        if (array_key_exists('google_review_count', $input)) {
            $document = $this->applyScalar($document, 'google_review_count', $input['google_review_count'], numeric: true);
        }

        $document = $this->applyNested($document, 'trust_signals', $input, [
            'wisetack_url',
            'synchrony_url',
        ]);

        return $this->applyNested($document, 'social_profiles', $input, [
            'facebook_url',
            'instagram_url',
            'nextdoor_url',
            'youtube_url',
        ]);
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    private function applyScalar(array $document, string $key, mixed $incoming, bool $numeric = false): array
    {
        $present = array_key_exists($key, $document);
        $current = $present ? $document[$key] : null;

        if ($this->leaveStored($present, $current, $incoming, $numeric)) {
            return $document;
        }

        $document[$key] = $this->nextValue($present, $current, $incoming, $numeric);

        return $document;
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  array<string, mixed>  $input
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    private function applyNested(array $document, string $parent, array $input, array $keys): array
    {
        $parentPresent = array_key_exists($parent, $document) && is_array($document[$parent]);
        /** @var array<string, mixed> $bucket */
        $bucket = $parentPresent ? $document[$parent] : [];
        $changed = false;

        foreach ($keys as $key) {
            if (! array_key_exists($key, $input)) {
                continue;
            }

            $present = array_key_exists($key, $bucket);
            $current = $present ? $bucket[$key] : null;
            $incoming = $input[$key];

            if ($this->leaveStored($present, $current, $incoming, false)) {
                continue;
            }

            $bucket[$key] = $this->nextValue($present, $current, $incoming, false);
            $changed = true;
        }

        if (! $changed) {
            return $document;
        }

        $document[$parent] = $bucket;

        return $document;
    }

    private function leaveStored(bool $present, mixed $current, mixed $incoming, bool $numeric): bool
    {
        if ($this->isBlank($incoming)) {
            return true;
        }

        if (! $present) {
            return false;
        }

        if ($numeric && is_numeric($current) && is_numeric($incoming)) {
            return (float) $current === (float) $incoming;
        }

        if (is_string($current) && is_string($incoming)) {
            return trim($current) === trim($incoming);
        }

        return $current === $incoming;
    }

    private function nextValue(bool $present, mixed $current, mixed $incoming, bool $numeric): mixed
    {
        if ($numeric && $present) {
            if (is_int($current)) {
                return (int) $incoming;
            }

            if (is_float($current)) {
                return (float) $incoming;
            }

            if (is_string($current)) {
                return is_string($incoming) ? trim($incoming) : $this->decimalString($incoming);
            }
        }

        return is_string($incoming) ? trim($incoming) : $incoming;
    }

    private function isBlank(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }

    private function decimalString(mixed $value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            $formatted = rtrim(rtrim(sprintf('%.4F', $value), '0'), '.');

            return $formatted === '' ? '0' : $formatted;
        }

        return trim((string) $value);
    }
}
