<?php

namespace App\Ark\Platform\Website;

final class WebsiteDocumentHash
{
    /**
     * @param  array<string, mixed>  $document
     */
    public static function hash(array $document): string
    {
        return hash('xxh3', (string) json_encode(self::canonicalize($document), JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    public static function canonicalize(array $document): array
    {
        ksort($document);

        foreach ($document as $key => $value) {
            if (is_array($value)) {
                $document[$key] = self::canonicalizeListOrMap($value);
            }
        }

        return $document;
    }

    /**
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    private static function canonicalizeListOrMap(array $value): array
    {
        if ($value === []) {
            return [];
        }

        $isList = array_keys($value) === range(0, count($value) - 1);

        if ($isList) {
            return array_map(
                fn (mixed $item): mixed => is_array($item) ? self::canonicalizeListOrMap($item) : $item,
                $value,
            );
        }

        /** @var array<string, mixed> $map */
        $map = $value;
        ksort($map);

        foreach ($map as $key => $item) {
            if (is_array($item)) {
                $map[$key] = self::canonicalizeListOrMap($item);
            }
        }

        return $map;
    }
}
