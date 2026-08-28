<?php

namespace App\Ark\ShopMemory\Suggestion;

/**
 * Provider registration only. Providers never see each other.
 * Duplicate keys fail loud — do not silently overwrite.
 */
final class SuggestionProviderRegistry
{
    /** @var array<string, SuggestionProvider> */
    private array $providers = [];

    public function register(SuggestionProvider $provider): void
    {
        $key = $provider->key();

        if (isset($this->providers[$key])) {
            throw DuplicateSuggestionProviderException::forKey($key);
        }

        $this->providers[$key] = $provider;
    }

    /**
     * @return list<SuggestionProvider>
     */
    public function all(): array
    {
        return array_values($this->providers);
    }

    /**
     * @return list<SuggestionProvider>
     */
    public function forContext(SuggestionContext $context): array
    {
        $keys = $context->providerKeys;
        $out = [];

        foreach ($this->providers as $provider) {
            if ($keys !== [] && ! in_array($provider->key(), $keys, true)) {
                continue;
            }

            if (! in_array($context->corpus, $provider->corpora(), true)) {
                continue;
            }

            $out[] = $provider;
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->providers);
    }

    public function count(): int
    {
        return count($this->providers);
    }
}
