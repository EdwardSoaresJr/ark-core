<?php

namespace App\Ark\ShopMemory\Suggestion;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Shop Memory Suggestion Engine.
 *
 * Orchestrates providers and the ranking pipeline.
 * Providers never call each other; the engine owns composition and failure isolation.
 */
final class SuggestionEngine
{
    private readonly bool $collectTraces;

    public function __construct(
        private readonly SuggestionProviderRegistry $registry,
        private readonly SuggestionPipeline $pipeline = new SuggestionPipeline,
        ?bool $collectTraces = null,
    ) {
        $this->collectTraces = $collectTraces ?? $this->defaultCollectTraces();
    }

    public function suggest(SuggestionContext $context): SuggestionResult
    {
        $merged = [];
        $traces = [];

        foreach ($this->registry->forContext($context) as $provider) {
            $started = hrtime(true);

            try {
                $batch = $provider->suggest($context);
            } catch (Throwable $e) {
                if ($this->collectTraces) {
                    $traces[] = new ProviderExecutionTrace(
                        providerKey: $provider->key(),
                        providerName: $provider->name(),
                        durationMs: $this->elapsedMs($started),
                        resultCount: 0,
                        failed: true,
                        error: $e->getMessage(),
                    );
                }

                $this->logProviderFailure($provider->key(), $e->getMessage());

                continue;
            }

            $accepted = 0;

            foreach ($batch as $suggestion) {
                if ($suggestion->corpus !== $context->corpus) {
                    continue;
                }

                if ($suggestion->providerKey !== $provider->key()) {
                    continue;
                }

                $merged[] = $suggestion;
                $accepted++;
            }

            if ($this->collectTraces) {
                $traces[] = new ProviderExecutionTrace(
                    providerKey: $provider->key(),
                    providerName: $provider->name(),
                    durationMs: $this->elapsedMs($started),
                    resultCount: $accepted,
                );
            }
        }

        return new SuggestionResult(
            items: $this->pipeline->process($merged, $context),
            query: $context->query,
            corpus: $context->corpus,
            traces: $traces,
        );
    }

    public function diagnostics(): ShopMemoryDiagnostics
    {
        return ShopMemoryDiagnostics::fromRegistryKeys($this->registry->keys());
    }

    private function defaultCollectTraces(): bool
    {
        // Opt in via constructor (service provider passes config('app.debug')).
        // Avoid calling config() here so pure unit tests stay framework-free.
        return false;
    }

    private function logProviderFailure(string $providerKey, string $message): void
    {
        try {
            Log::warning('Shop Memory provider failed', [
                'provider' => $providerKey,
                'message' => $message,
            ]);
        } catch (Throwable) {
            // Unit tests may run without the log container binding.
        }
    }

    private function elapsedMs(int $startedHrtime): float
    {
        return round((hrtime(true) - $startedHrtime) / 1_000_000, 3);
    }
}
