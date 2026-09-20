<?php

use App\Ark\ShopMemory\Projections\ConcernSuggestionProjection;
use App\Ark\ShopMemory\Projections\LaborSuggestionProjection;
use App\Ark\ShopMemory\Projections\SuggestionPresentation;
use App\Ark\ShopMemory\Suggestion\DuplicateSuggestionProviderException;
use App\Ark\ShopMemory\Suggestion\Suggestion;
use App\Ark\ShopMemory\Suggestion\SuggestionCorpus;
use App\Ark\ShopMemory\Suggestion\SuggestionContext;
use App\Ark\ShopMemory\Suggestion\SuggestionDeduper;
use App\Ark\ShopMemory\Suggestion\SuggestionEngine;
use App\Ark\ShopMemory\Suggestion\SuggestionIdentity;
use App\Ark\ShopMemory\Suggestion\SuggestionPipeline;
use App\Ark\ShopMemory\Suggestion\SuggestionProvider;
use App\Ark\ShopMemory\Suggestion\SuggestionProviderRegistry;
use App\Ark\ShopMemory\Suggestion\SuggestionRanker;
use App\Ark\ShopMemory\Suggestion\SuggestionTextNormalizer;

final class FakeWorkLanguageProvider implements SuggestionProvider
{
    public function key(): string
    {
        return 'fake_work';
    }

    public function name(): string
    {
        return 'Fake Work';
    }

    public function description(): string
    {
        return 'Test work-language provider.';
    }

    public function version(): string
    {
        return '1';
    }

    public function corpora(): array
    {
        return [SuggestionCorpus::WorkLanguage];
    }

    public function suggest(SuggestionContext $context): array
    {
        return [
            new Suggestion(
                id: SuggestionIdentity::make($this->key(), 'Replace front brake pads and rotors'),
                text: 'Replace front brake pads and rotors',
                providerKey: $this->key(),
                corpus: SuggestionCorpus::WorkLanguage,
                relevance: 0.9,
                meta: ['usage_count' => 9],
            ),
            new Suggestion(
                id: SuggestionIdentity::make($this->key(), 'Replace front brake pads and rotors'),
                text: 'Replace front brake pads and rotors',
                providerKey: $this->key(),
                corpus: SuggestionCorpus::WorkLanguage,
                relevance: 0.4,
                meta: ['usage_count' => 1],
            ),
            new Suggestion(
                id: SuggestionIdentity::make($this->key(), 'Replace serpentine belt'),
                text: 'Replace serpentine belt',
                providerKey: $this->key(),
                corpus: SuggestionCorpus::WorkLanguage,
                relevance: 0.5,
                meta: ['usage_count' => 2],
            ),
        ];
    }
}

final class FakeProblemLanguageProvider implements SuggestionProvider
{
    public function key(): string
    {
        return 'fake_problem';
    }

    public function name(): string
    {
        return 'Fake Problem';
    }

    public function description(): string
    {
        return 'Test problem-language provider.';
    }

    public function version(): string
    {
        return '1';
    }

    public function corpora(): array
    {
        return [SuggestionCorpus::ProblemLanguage];
    }

    public function suggest(SuggestionContext $context): array
    {
        return [
            new Suggestion(
                id: SuggestionIdentity::make($this->key(), 'Brake vibration'),
                text: 'Brake vibration',
                providerKey: $this->key(),
                corpus: SuggestionCorpus::ProblemLanguage,
                relevance: 0.8,
            ),
        ];
    }
}

final class CrossTalkProvider implements SuggestionProvider
{
    public function key(): string
    {
        return 'crosstalk';
    }

    public function name(): string
    {
        return 'Cross Talk';
    }

    public function description(): string
    {
        return 'Mis-tagged suggestions for isolation tests.';
    }

    public function version(): string
    {
        return '1';
    }

    public function corpora(): array
    {
        return [SuggestionCorpus::WorkLanguage];
    }

    public function suggest(SuggestionContext $context): array
    {
        return [
            new Suggestion(
                id: 'crosstalk:bad',
                text: 'Should not appear',
                providerKey: 'other_provider',
                corpus: SuggestionCorpus::ProblemLanguage,
                relevance: 1.0,
            ),
        ];
    }
}

final class ExplodingProvider implements SuggestionProvider
{
    public function key(): string
    {
        return 'exploding';
    }

    public function name(): string
    {
        return 'Exploding';
    }

    public function description(): string
    {
        return 'Always throws.';
    }

    public function version(): string
    {
        return '1';
    }

    public function corpora(): array
    {
        return [SuggestionCorpus::WorkLanguage];
    }

    public function suggest(SuggestionContext $context): array
    {
        throw new \RuntimeException('provider boom');
    }
}

test('suggestion engine merges providers and dedupes by text', function (): void {
    $registry = new SuggestionProviderRegistry;
    $registry->register(new FakeWorkLanguageProvider);

    $engine = new SuggestionEngine($registry, new SuggestionPipeline);
    $result = $engine->suggest(new SuggestionContext(
        query: 'brake',
        corpus: SuggestionCorpus::WorkLanguage,
        limit: 8,
    ));

    expect($result->texts())->toBe([
        'Replace front brake pads and rotors',
        'Replace serpentine belt',
    ])->and($result->items[0]->relevance)->toBe(0.9)
        ->and($result->items[0]->meta['usage_count'])->toBe(9);
});

test('suggestion engine isolates corpora — work provider never answers problem projection', function (): void {
    $registry = new SuggestionProviderRegistry;
    $registry->register(new FakeWorkLanguageProvider);
    $registry->register(new FakeProblemLanguageProvider);

    $engine = new SuggestionEngine($registry);
    $labor = new LaborSuggestionProjection($engine);
    $concern = new ConcernSuggestionProjection($engine);

    expect($labor->suggest('brake')->texts())->toBe([
        'Replace front brake pads and rotors',
        'Replace serpentine belt',
    ])->and($concern->suggest('shake')->texts())->toBe([
        'Brake vibration',
    ]);
});

test('suggestion engine drops suggestions with wrong provider key or corpus', function (): void {
    $registry = new SuggestionProviderRegistry;
    $registry->register(new CrossTalkProvider);

    $engine = new SuggestionEngine($registry);
    $result = $engine->suggest(new SuggestionContext(
        query: 'x',
        corpus: SuggestionCorpus::WorkLanguage,
    ));

    expect($result->isEmpty())->toBeTrue();
});

test('suggestion engine can restrict to named providers', function (): void {
    $registry = new SuggestionProviderRegistry;
    $registry->register(new FakeWorkLanguageProvider);
    $registry->register(new FakeProblemLanguageProvider);

    $engine = new SuggestionEngine($registry);
    $result = $engine->suggest(new SuggestionContext(
        query: 'brake',
        corpus: SuggestionCorpus::WorkLanguage,
        providerKeys: ['fake_problem'],
    ));

    expect($result->isEmpty())->toBeTrue();
});

test('duplicate provider registration fails loud', function (): void {
    $registry = new SuggestionProviderRegistry;
    $registry->register(new FakeWorkLanguageProvider);

    expect(fn () => $registry->register(new FakeWorkLanguageProvider))
        ->toThrow(DuplicateSuggestionProviderException::class);
});

test('provider exceptions are isolated and other providers still run', function (): void {
    $registry = new SuggestionProviderRegistry;
    $registry->register(new ExplodingProvider);
    $registry->register(new FakeWorkLanguageProvider);

    $engine = new SuggestionEngine($registry);
    $result = $engine->suggest(new SuggestionContext(
        query: 'brake',
        corpus: SuggestionCorpus::WorkLanguage,
    ));

    expect($result->texts())->toContain('Replace front brake pads and rotors');
});

test('ranking is deterministic for equal relevance', function (): void {
    $ranker = new SuggestionRanker;
    $context = new SuggestionContext(query: 'replace', corpus: SuggestionCorpus::WorkLanguage, limit: 8);

    $a = new Suggestion('a', 'Replace belt', 'p', SuggestionCorpus::WorkLanguage, 1.0);
    $b = new Suggestion('b', 'Replace pads', 'p', SuggestionCorpus::WorkLanguage, 1.0);

    expect($ranker->rank([$b, $a], $context))->toEqual($ranker->rank([$a, $b], $context));
});

test('suggestion identity is stable for the same provider and text', function (): void {
    expect(SuggestionIdentity::make('historical_labor', 'Replace Front Pads'))
        ->toBe(SuggestionIdentity::make('historical_labor', 'replace front pads'))
        ->and(SuggestionIdentity::make('historical_labor', 'A'))
        ->not->toBe(SuggestionIdentity::make('other', 'A'));
});

test('projection presentation truncates and preserves metadata', function (): void {
    $long = str_repeat('Replace front brake pads and rotors with ceramic compound ', 5);
    $suggestion = new Suggestion(
        id: 'x',
        text: "  {$long}  ",
        providerKey: 'fake_work',
        corpus: SuggestionCorpus::WorkLanguage,
        relevance: 3.0,
        meta: ['usage_count' => 3, 'source' => 'test'],
    );

    $presented = (new SuggestionPresentation)->present($suggestion, 'replace');

    expect(mb_strlen($presented->display))->toBeLessThanOrEqual(SuggestionPresentation::MAX_DISPLAY_LENGTH)
        ->and($presented->display)->toEndWith('…')
        ->and($presented->meta['usage_count'])->toBe(3)
        ->and($presented->score)->toBe(3.0)
        ->and($presented->id)->toBe('x');
});

test('empty registry yields empty result and catalog diagnostics', function (): void {
    $engine = new SuggestionEngine(new SuggestionProviderRegistry);
    $result = $engine->suggest(new SuggestionContext(
        query: 'brake',
        corpus: SuggestionCorpus::WorkLanguage,
    ));
    $diagnostics = $engine->diagnostics();

    expect($result->isEmpty())->toBeTrue()
        ->and($diagnostics->registeredCount)->toBe(0)
        ->and($diagnostics->enabledCount)->toBeGreaterThanOrEqual(1)
        ->and(implode("\n", $diagnostics->lines()))->toContain('historical_labor')
        ->and(implode("\n", $diagnostics->lines()))->toContain('disabled');
});

test('diagnostics lists catalog enablement vs registration', function (): void {
    $registry = new SuggestionProviderRegistry;
    $registry->register(new FakeWorkLanguageProvider);

    $diagnostics = (new SuggestionEngine($registry))->diagnostics();
    $lines = implode("\n", $diagnostics->lines());

    expect($diagnostics->registeredCount)->toBe(1)
        ->and($lines)->toContain('historical_labor')
        ->and($lines)->toContain('historical_concern')
        ->and($lines)->toContain('Add Concern popup');
});

test('provider timing traces collect when enabled', function (): void {
    $registry = new SuggestionProviderRegistry;
    $registry->register(new FakeWorkLanguageProvider);

    $engine = new SuggestionEngine($registry, new SuggestionPipeline, collectTraces: true);
    $result = $engine->suggest(new SuggestionContext(
        query: 'brake',
        corpus: SuggestionCorpus::WorkLanguage,
    ));

    expect($result->traces)->toHaveCount(1)
        ->and($result->traces[0]->providerKey)->toBe('fake_work')
        ->and($result->traces[0]->resultCount)->toBe(3)
        ->and($result->traces[0]->failed)->toBeFalse()
        ->and($result->texts())->toHaveCount(2);
});

test('pipeline order is normalize-dedupe-rank via composition', function (): void {
    $pipeline = new SuggestionPipeline(
        new SuggestionDeduper(new SuggestionTextNormalizer),
        new SuggestionRanker,
    );

    $items = $pipeline->process([
        new Suggestion('1', 'Replace pads', 'p', SuggestionCorpus::WorkLanguage, 1.0),
        new Suggestion('2', '  replace   pads ', 'p', SuggestionCorpus::WorkLanguage, 5.0),
        new Suggestion('3', 'Replace belt', 'p', SuggestionCorpus::WorkLanguage, 2.0),
    ], new SuggestionContext(query: 'replace', corpus: SuggestionCorpus::WorkLanguage, limit: 8));

    expect($items)->toHaveCount(2)
        ->and($items[0]->text)->toBe('  replace   pads ')
        ->and($items[0]->relevance)->toBe(5.0);
});
