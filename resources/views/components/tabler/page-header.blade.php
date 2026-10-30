@props(['title', 'pretitle' => null, 'subtitle' => null])

<div class="page-header d-print-none">
    <div class="container-xl">
        <div class="row g-2 align-items-center">
            <div class="col">
                @if (filled($pretitle))
                    <div class="page-pretitle">{{ $pretitle }}</div>
                @endif
                <h2 class="page-title">{{ $title }}</h2>
                @if (filled($subtitle))
                    <div class="text-secondary mt-1">{{ $subtitle }}</div>
                @endif
            </div>
            @isset($actions)
                <div class="col-auto ms-auto d-print-none">
                    <div class="btn-list">
                        {{ $actions }}
                    </div>
                </div>
            @endisset
        </div>
    </div>
</div>
