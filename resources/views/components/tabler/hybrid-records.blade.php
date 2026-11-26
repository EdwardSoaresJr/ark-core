<div {{ $attributes->class(['ark-collection-hybrid']) }} data-collection-hybrid>
    {{ $sort ?? '' }}
    <div class="ark-collection-hybrid__records" data-collection-hybrid-records>
        {{ $slot }}
    </div>
</div>
