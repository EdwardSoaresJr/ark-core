@props([
    'attentionTone' => 'none',
])

<article {{ $attributes->class(['ark-collection-hybrid-record', 'ark-collection-hybrid-record--'.$attentionTone]) }}>
    <div class="ark-collection-hybrid-record__primary">
        <div class="ark-collection-hybrid-record__topline">
            {{ $identity }}
        </div>
        @isset($middle)
            <div class="ark-collection-hybrid-record__middle">
                {{ $middle }}
            </div>
        @endisset
    </div>

    @isset($workflow)
        <div class="ark-collection-hybrid-record__workflow">
            {{ $workflow }}
        </div>
    @endisset

    <div class="ark-collection-hybrid-record__finance">
        @isset($finance)
            <div class="ark-collection-hybrid-record__finance-stack">
                {{ $finance }}
            </div>
        @endisset
        @isset($open)
            {{ $open }}
        @endisset
    </div>
</article>
