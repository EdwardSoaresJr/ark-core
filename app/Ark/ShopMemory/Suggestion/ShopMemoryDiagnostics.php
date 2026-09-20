<?php

namespace App\Ark\ShopMemory\Suggestion;

use App\Ark\ShopMemory\ShopMemoryFeatures;
use App\Ark\ShopMemory\ShopMemoryProviderCatalog;

/**
 * Catalog vs enablement vs engine registration — disabled ≠ broken.
 */
final class ShopMemoryDiagnostics
{
    /**
     * @param  list<array{
     *     key: string,
     *     name: string,
     *     description: string,
     *     version: string,
     *     corpora: list<string>,
     *     enabled: bool,
     *     registered: bool,
     *     status: string,
     * }>  $providers
     */
    public function __construct(
        public readonly array $providers,
        public readonly int $registeredCount,
        public readonly int $enabledCount,
        public readonly bool $addConcernPopupEnabled,
    ) {}

    /**
     * @param  list<string>  $registeredKeys
     */
    public static function fromRegistryKeys(array $registeredKeys): self
    {
        $registeredSet = array_fill_keys($registeredKeys, true);
        $rows = [];
        $enabledCount = 0;

        foreach (ShopMemoryProviderCatalog::providers() as $catalog) {
            $key = $catalog['key'];
            $enabled = ShopMemoryFeatures::providerEnabled($key);
            $isEngineProvider = $key !== ShopMemoryProviderCatalog::AI_REWRITE;
            $registered = $isEngineProvider && isset($registeredSet[$key]);

            if ($enabled) {
                $enabledCount++;
            }

            // AI Rewrite is a sibling action — never engine-registered.
            $status = match (true) {
                ! $isEngineProvider && $enabled => 'healthy',
                ! $isEngineProvider && ! $enabled => 'disabled',
                $enabled && $registered => 'healthy',
                $enabled && ! $registered => 'missing',
                ! $enabled && $registered => 'unexpected_registered',
                default => 'disabled',
            };

            $rows[] = [
                'key' => $key,
                'name' => $catalog['name'],
                'description' => $catalog['description'],
                'version' => $catalog['version'],
                'corpora' => $catalog['corpora'],
                'enabled' => $enabled,
                'registered' => $registered,
                'status' => $status,
            ];
        }

        return new self(
            providers: $rows,
            registeredCount: count($registeredKeys),
            enabledCount: $enabledCount,
            addConcernPopupEnabled: ShopMemoryFeatures::addConcernPopupEnabled(),
        );
    }

    /**
     * @return list<string>
     */
    public function lines(): array
    {
        $lines = ['Shop Memory diagnostics'];
        $lines[] = sprintf(
            'Add Concern popup: %s',
            $this->addConcernPopupEnabled ? 'enabled' : 'disabled',
        );
        $lines[] = '';

        foreach ($this->providers as $provider) {
            $enabled = $provider['enabled'] ? 'enabled' : 'disabled';
            $registered = $provider['registered'] ? 'registered' : 'not registered';
            $health = $provider['status'] === 'healthy'
                ? 'healthy'
                : ($provider['status'] === 'disabled' ? '' : $provider['status']);

            $suffix = $health !== '' ? "   {$health}" : '';
            $lines[] = sprintf(
                '%-28s %-10s %-16s%s',
                $provider['key'],
                $enabled,
                $registered,
                $suffix,
            );
        }

        $lines[] = '';
        $lines[] = "{$this->enabledCount} enabled · {$this->registeredCount} registered in engine.";

        return $lines;
    }
}
