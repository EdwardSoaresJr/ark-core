@props(['layout', 'title' => null, 'titleId' => null, 'titleCount' => null, 'pretitle' => null, 'subtitle' => null, 'actionsLabel' => null, 'stackActions' => false, 'actionsBelow' => false])

@php
    // Workspace layouts are defined in docs/engineering/TABLER.md#workspace-layouts.
    $container = match ($layout) {
        'fluid', 'split', 'canvas' => 'container-fluid',
        'focused' => 'container-narrow',
        default => throw new InvalidArgumentException("Unknown Tabler page layout [{$layout}]."),
    };
    $fillsHeight = in_array($layout, ['split', 'canvas'], true);
@endphp

@if (filled($title))
    <div class="page-header d-print-none">
        <div class="{{ $container }}">
            <div class="row g-2 align-items-center">
                <div @class(['col', 'col-12 col-md' => $stackActions && ! $actionsBelow, 'col-12' => $actionsBelow])>
                    @if (filled($pretitle))
                        <div class="page-pretitle">{{ $pretitle }}</div>
                    @endif
                    <h2 class="page-title"@if (filled($titleId)) id="{{ $titleId }}"@endif>{{ $title }}@if ((int) $titleCount > 0) <span class="badge bg-red-lt ms-1" data-page-title-count>{{ (int) $titleCount }}</span>@endif</h2>
                    @if (filled($subtitle))
                        <div class="text-secondary mt-1 d-none d-md-block">{{ $subtitle }}</div>
                    @endif
                </div>
                @isset($actions)
                    <div @class([
                        'col-auto ms-auto d-print-none' => ! $stackActions && ! $actionsBelow,
                        'col-12 col-md-auto ms-md-auto d-print-none' => $stackActions && ! $actionsBelow,
                        'col-12 d-print-none' => $actionsBelow,
                    ])>
                        <div @class(['btn-list', 'flex-nowrap' => $actionsBelow]) @if (filled($actionsLabel)) role="group" aria-label="{{ $actionsLabel }}" @endif>
                            {{ $actions }}
                        </div>
                    </div>
                @endisset
            </div>
        </div>
    </div>
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
