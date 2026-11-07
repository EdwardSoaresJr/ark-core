{{--
    A page's context in the shell's top bar: breadcrumbs, title, a meta slot beside the title (a status badge) and actions,
    declared through <x-tabler.page>.
    Breadcrumbs are [['label' => ..., 'url' => ...], ...]; a crumb that repeats the title is dropped. Secondary
    actions (x-tabler.page-action without primary) move into the overflow menu on narrow screens (tabler.js).
    The id lets a worksheet refresh (ark-worksheet-continuity.js) replace the whole bar with the page's fresh copy.
--}}
@php
    $crumbs = collect($breadcrumbs ?? [])
        ->map(fn ($crumb): array => is_array($crumb) ? $crumb : ['label' => (string) $crumb])
        ->filter(fn (array $crumb): bool => filled($crumb['label'] ?? null) && strcasecmp(trim((string) $crumb['label']), trim(strip_tags((string) $title))) !== 0)
        ->values();
    $hasActions = $actions instanceof \Illuminate\View\ComponentSlot ? $actions->isNotEmpty() : filled($actions ?? null);
@endphp
<div class="ark-page-context" id="ark-page-context" x-data data-ark-page-context>
    <div class="ark-page-context__heading">
        @if ($crumbs->isNotEmpty())
            <ol class="breadcrumb ark-page-context__crumbs" aria-label="Breadcrumbs">
                @foreach ($crumbs as $crumb)
                    <li class="breadcrumb-item">
                        @if (filled($crumb['url'] ?? null))
                            <a href="{{ $crumb['url'] }}">{{ $crumb['label'] }}</a>
                        @else
                            {{ $crumb['label'] }}
                        @endif
                    </li>
                @endforeach
            </ol>
        @endif
        <h2 class="page-title"@if (filled($titleId)) id="{{ $titleId }}"@endif>{{ $title }}@if ((int) $titleCount > 0) <span class="badge bg-red-lt ms-1" data-page-title-count>{{ (int) $titleCount }}</span>@endif</h2>
        @if ($meta instanceof \Illuminate\View\ComponentSlot && $meta->isNotEmpty())
            <div class="ark-page-context__meta">{{ $meta }}</div>
        @endif
        @if (filled($subtitle))
            <span class="ark-page-context__subtitle">{{ $subtitle }}</span>
        @endif
    </div>
    @if ($hasActions)
        <div class="ark-page-context__actions btn-list flex-nowrap" role="group" aria-label="{{ $actionsLabel ?: __('Page actions') }}" data-ark-page-actions>
            {{ $actions }}
            <div class="dropdown" data-ark-page-overflow hidden>
                <button type="button" class="btn btn-icon" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" aria-label="{{ __('More page actions') }}" title="{{ __('More page actions') }}">
                    <x-tabler.icon name="dots" />
                </button>
                <div class="dropdown-menu dropdown-menu-end" data-ark-page-overflow-menu></div>
            </div>
        </div>
    @endif
</div>
