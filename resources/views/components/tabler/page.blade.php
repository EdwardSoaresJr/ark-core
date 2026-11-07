@props(['layout', 'title' => null, 'titleId' => null, 'titleCount' => null, 'pretitle' => null, 'breadcrumbs' => null, 'subtitle' => null, 'actionsLabel' => null])

@php
    // Workspace layouts are defined in docs/engineering/TABLER.md#workspace-layouts.
    $container = match ($layout) {
        'fluid', 'split', 'canvas' => 'container-fluid',
        'focused' => 'container-narrow',
        default => throw new InvalidArgumentException("Unknown Tabler page layout [{$layout}]."),
    };
    $fillsHeight = in_array($layout, ['split', 'canvas'], true);
@endphp

{{-- The page's title, breadcrumbs and actions render in the shell's top bar (components.tabler.page-context), not in a row of their own. --}}
@if (filled($title))
    @push('ark-page-context')
        @include('components.tabler.page-context', [
            'title' => $title,
            'titleId' => $titleId,
            'titleCount' => $titleCount,
            'breadcrumbs' => $breadcrumbs ?? (filled($pretitle) ? [['label' => $pretitle]] : []),
            'subtitle' => $subtitle,
            'meta' => $meta ?? null,
            'actions' => $actions ?? null,
            'actionsLabel' => $actionsLabel,
        ])
    @endpush
@endif

<div @class(['page-body', 'mb-0' => $layout === 'canvas'])>
    <div @class([
        $container,
        'd-flex flex-column flex-fill' => $fillsHeight,
        'px-0' => $layout === 'canvas',
    ])>
        {{ $slot }}
    </div>
</div>
