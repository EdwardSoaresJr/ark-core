<?php

namespace App\Ark\Dragon\Agent;

use InvalidArgumentException;

/**
 * OpenAI function names may only be [a-zA-Z0-9_-]. ARK keeps dotted canonical ids.
 */
final class DragonOpenAiFunctionNames
{
    public static function toProvider(string $canonical): string
    {
        $canonical = trim($canonical);
        if ($canonical === '' || ! preg_match('/^[a-zA-Z0-9_.-]+$/', $canonical)) {
            throw new InvalidArgumentException("Invalid canonical Dragon tool [{$canonical}].");
        }

        $provider = str_replace('.', '_', $canonical);
        if (! preg_match('/^[a-zA-Z0-9_-]+$/', $provider)) {
            throw new InvalidArgumentException("Canonical tool [{$canonical}] has no OpenAI-safe name.");
        }

        return $provider;
    }

    /**
     * @param  list<string>  $canonicalTools
     */
    public static function toCanonical(string $providerName, array $canonicalTools): string
    {
        $index = self::index($canonicalTools);
        if (! isset($index[$providerName])) {
            throw new InvalidArgumentException("Unknown provider tool [{$providerName}].");
        }

        return $index[$providerName];
    }

    /**
     * @param  list<string>  $canonicalTools
     * @return array<string, string>
     */
    public static function index(array $canonicalTools): array
    {
        $index = [];
        foreach ($canonicalTools as $canonical) {
            $provider = self::toProvider($canonical);
            if (isset($index[$provider]) && $index[$provider] !== $canonical) {
                throw new InvalidArgumentException("Provider tool name collision [{$provider}].");
            }
            $index[$provider] = $canonical;
        }

        return $index;
    }
}
