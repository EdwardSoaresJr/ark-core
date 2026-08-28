@php
    $icon = $icon ?? 'labor';
@endphp
{{-- ARKv1 muscle memory: solid glyphs in equal boxed hit targets (clock / wrench / sticky-note / external / receipt / camera). --}}
<span class="ops-repair-action__compose-icon" aria-hidden="true">
    @switch($icon)
        @case('part')
            {{-- Font Awesome solid wrench — open-end + bolt head --}}
            <svg viewBox="0 0 512 512" fill="currentColor">
                <path d="M352 320c88.4 0 160-71.6 160-160c0-15.3-2.2-30.1-6.2-44.2c-3.1-10.8-16.4-13.2-24.3-5.3l-76.8 76.8c-3 3-7.1 4.7-11.3 4.7L336 192c-8.8 0-16-7.2-16-16l0-57.4c0-4.2 1.7-8.3 4.7-11.3l76.8-76.8c7.9-7.9 5.4-21.2-5.3-24.3C382.1 2.2 367.3 0 352 0C263.6 0 192 71.6 192 160c0 19.1 3.4 37.5 9.5 54.5L19.9 396.1C7.2 408.8 0 426.1 0 444.1C0 481.6 30.4 512 67.9 512c18 0 35.3-7.2 48-19.9L297.5 310.5c17 6.2 35.4 9.5 54.5 9.5zM80 408a24 24 0 1 1 0 48 24 24 0 1 1 0-48z"/>
            </svg>
            @break
        @case('note')
            {{-- sticky-note --}}
            <svg viewBox="0 0 20 20" fill="currentColor">
                <path d="M3.2 2.5h10.1c.7 0 1.3.6 1.3 1.3v8.2l-4.1 4.1H3.2c-.7 0-1.3-.6-1.3-1.3V3.8c0-.7.6-1.3 1.3-1.3Zm8.6 12.4 2.7-2.7h-2.1c-.3 0-.6.3-.6.6v2.1Z"/>
            </svg>
            @break
        @case('sublet')
            {{-- external-link — work sent outside / vendor service --}}
            <svg viewBox="0 0 512 512" fill="currentColor">
                <path d="M320 0c-17.7 0-32 14.3-32 32s14.3 32 32 32l82.7 0L201.4 265.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0L448 109.3l0 82.7c0 17.7 14.3 32 32 32s32-14.3 32-32l0-160c0-17.7-14.3-32-32-32L320 0zM80 32C35.8 32 0 67.8 0 112L0 432c0 44.2 35.8 80 80 80l320 0c44.2 0 80-35.8 80-80l0-112c0-17.7-14.3-32-32-32s-32 14.3-32 32l0 112c0 8.8-7.2 16-16 16L80 448c-8.8 0-16-7.2-16-16l0-320c0-8.8 7.2-16 16-16l112 0c17.7 0 32-14.3 32-32s-14.3-32-32-32L80 32z"/>
            </svg>
            @break
        @case('fee')
            {{-- receipt --}}
            <svg viewBox="0 0 20 20" fill="currentColor">
                <path d="M4.1 1.8h11.8c.3 0 .5.2.5.5v15.4c0 .4-.5.6-.8.4l-1.5-1.1-1.5 1.1c-.3.2-.7.2-1 0l-1.5-1.1-1.5 1.1c-.3.2-.7.2-1 0L5.6 17l-1.5 1.1c-.3.2-.8 0-.8-.4V2.3c0-.3.2-.5.5-.5Zm2.2 3.4v1.2h7.4V5.2H6.3Zm0 3v1.2h7.4V8.2H6.3Zm0 3v1.2h4.8v-1.2H6.3Z"/>
            </svg>
            @break
        @case('saved-work')
            {{-- bookmark / canned job --}}
            <svg viewBox="0 0 20 20" fill="currentColor">
                <path d="M5.2 2.2h9.6c.7 0 1.2.5 1.2 1.2v14.1c0 .4-.4.6-.7.5L10 14.6 4.7 18c-.3.1-.7-.1-.7-.5V3.4c0-.7.5-1.2 1.2-1.2Z"/>
            </svg>
            @break
        @case('evidence')
            {{-- camera --}}
            <svg viewBox="0 0 20 20" fill="currentColor">
                <path d="M7.1 3.2 6.3 4.5H3.8c-.9 0-1.6.7-1.6 1.6v8.6c0 .9.7 1.6 1.6 1.6h12.4c.9 0 1.6-.7 1.6-1.6V6.1c0-.9-.7-1.6-1.6-1.6h-2.5l-.8-1.3c-.2-.3-.5-.5-.9-.5H8c-.4 0-.7.2-.9.5ZM10 7.4a3.4 3.4 0 1 1 0 6.8 3.4 3.4 0 0 1 0-6.8Zm0 1.5a1.9 1.9 0 1 0 0 3.8 1.9 1.9 0 0 0 0-3.8Z"/>
            </svg>
            @break
        @default
            {{-- clock — Labor --}}
            <svg viewBox="0 0 20 20" fill="currentColor">
                <path d="M10 1.6a8.4 8.4 0 1 1 0 16.8A8.4 8.4 0 0 1 10 1.6Zm0 1.6a6.8 6.8 0 1 0 0 13.6A6.8 6.8 0 0 0 10 3.2Zm.1 2.2c.4 0 .7.3.7.7v4.1l2.4 1.4c.3.2.4.6.2.9-.2.3-.6.4-.9.2l-2.8-1.6a.7.7 0 0 1-.4-.7V6.1c0-.4.3-.7.8-.7Z"/>
            </svg>
    @endswitch
</span>
