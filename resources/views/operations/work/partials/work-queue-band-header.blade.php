@props([
    'id',
    'title',
    'count' => null,
    'queue' => null,
    'compact' => false,
    'subtitle' => null,
])

@php
    $href = null;
    $linkTitle = null;

    if ($compact && filled($queue)) {
        $href = $queue === 'comms'
            ? \App\Ark\Operations\Communications\CommunicationsNeedsYou::url()
            : route('operations.work.queue', $queue);
        $linkTitle = 'Open full '.$title.' queue';
    }
@endphp

<x-operations.queue-band-header
    variant="home"
    :id="$id"
    :label="$title"
    :description="$subtitle"
    :count="$count"
    :href="$href"
    :link-title="$linkTitle"
>
    <x-slot:actions>
        @unless ($compact)
            <a href="{{ route('operations.index') }}" class="ops-page-link">Back to Work</a>
        @endunless

        @if (filled($addLabel ?? null) && filled($addStoreRoute ?? null))
            @include('operations.work.partials.advisor-work-item-add', [
                'label' => $addLabel,
                'storeRoute' => $addStoreRoute,
            ])
        @endif

        {{ $actions ?? '' }}
    </x-slot:actions>
</x-operations.queue-band-header>
