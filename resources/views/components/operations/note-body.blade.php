@props([
    'text' => '',
    'ui' => null,
])

@if (filled($text))
    @if ($ui === 'tabler')
        <div {{ $attributes->class('text-break') }} style="white-space: pre-line">{{ $text }}</div>
    @else
        <div {{ $attributes->class('ops-note-body') }}>{{ $text }}</div>
    @endif
@endif
