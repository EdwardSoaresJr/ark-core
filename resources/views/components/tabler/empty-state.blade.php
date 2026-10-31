@props(['icon', 'clearUrl' => null])

<div class="card">
    <div class="empty">
        <div class="empty-icon">
            <x-tabler.icon :name="$icon" />
        </div>
        <p class="empty-title">{{ $slot }}</p>
        @if ($clearUrl !== null)
            <div class="empty-action">
                <a href="{{ $clearUrl }}" class="btn">Clear search</a>
            </div>
        @endif
    </div>
</div>
