@props([
    'text' => '',
])

@if (filled($text))
    <div {{ $attributes->class('ops-note-body') }}>{{ $text }}</div>
@endif
