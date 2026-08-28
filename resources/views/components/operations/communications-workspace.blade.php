@props([
    'section',
    'listItems' => [],
    'listCount' => 0,
    'selected' => null,
    'thread' => null,
    'context' => null,
    'listTitle' => 'Queue',
    'listDescription' => null,
    'filters' => null,
    'paginator' => null,
    'turnFilter' => null,
    'turnCounts' => null,
    'listFilter' => null,
    'filterCounts' => null,
])

@php
    /** @var list<array<string, mixed>> $listItems */
    /** @var array<string, mixed>|null $selected */
    /** @var array<string, mixed>|null $thread */
    /** @var array<string, mixed>|null $context */
@endphp

<section class="ops-comms-workspace">
    <x-operations.queue-page-header
        id="ops-comms-workspace"
        title="Communications"
        :description="$listDescription"
        :count="$listCount > 0 ? $listCount : null"
        :show-back="false"
    >
        <x-slot:actions>
            <button
                type="button"
                class="ops-page-link"
                data-ops-global-search-open
                title="Search anywhere (⌘K)"
            >Compose / Search</button>
            <a href="{{ route('operations.index') }}" class="ops-page-link">Work</a>
        </x-slot:actions>
    </x-operations.queue-page-header>

    @include('operations.communications.workspace.partials.section-nav', [
        'section' => $section,
        'listFilter' => $listFilter,
        'filterCounts' => $filterCounts,
        'turnFilter' => $turnFilter,
        'turnCounts' => $turnCounts,
    ])

    <div
        id="ops-comms-workspace-live"
        class="ops-comms-workspace__grid"
        data-fragment-url="{{ route('operations.communications.workspace.fragment', array_merge(request()->query(), ['section' => $section])) }}"
        data-section="{{ $section }}"
    >
        <aside id="ops-comms-workspace-list" class="ops-comms-workspace__list" aria-label="{{ $listTitle }}">
            @if (is_array($filters))
                @include('operations.communications.workspace.partials.history-filters', ['filters' => $filters])
            @endif

            @include('operations.communications.workspace.partials.list-panel', [
                'title' => $listTitle,
                'count' => $listCount,
                'items' => $listItems,
                'selected' => $selected,
                'listFilter' => $listFilter,
                'filterCounts' => $filterCounts,
                'turnFilter' => $turnFilter,
                'turnCounts' => $turnCounts,
                'section' => $section,
            ])

            @if ($paginator !== null)
                <div class="ops-comms-workspace__pagination">
                    {{ $paginator->withQueryString()->links() }}
                </div>
            @endif
        </aside>

        <main id="ops-comms-workspace-thread" class="ops-comms-workspace__thread" aria-label="Conversation story">
            @include('operations.communications.workspace.partials.thread-panel', [
                'thread' => $thread,
                'selected' => $selected,
                'section' => $section,
            ])
        </main>

        <aside id="ops-comms-workspace-context" class="ops-comms-workspace__context" aria-label="Shop context">
            @include('operations.communications.workspace.partials.context-panel', [
                'context' => $context,
                'selected' => $selected,
                'section' => $section,
            ])
        </aside>
    </div>
</section>
