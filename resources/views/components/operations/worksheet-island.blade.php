@props(['stack' => false])

{{-- Repair order worksheet markup that is not on Tabler yet. Its legacy rules select on both of these ancestors. --}}
<div {{ $attributes->class(['ark-legacy ark-worksheet-island ops-main']) }}>
    <div class="ops-estimate-workspace ops-estimate-workspace--builder" style="{{ $stack ? 'padding-bottom: 0' : 'display: block; padding-bottom: 0' }}">
        {{ $slot }}
    </div>
</div>
