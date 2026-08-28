<x-public.lead-intake :seo="$seo" publicSurfacePage="financing" :editorial-sections="true">
    <x-customer.split-page variant="public">
        <x-slot:primary>
            <h1 class="public-page-title">Financing for unexpected repairs</h1>
            <p class="public-page-lede">
                Big repairs don’t wait for a good month. If the job qualifies, Wisetack and Synchrony Car Care™ can help you pay over time.
            </p>

            @include('partials.public.why-drivers-choose', [
                'trustSignals' => $trustSignals,
                'class' => 'mt-6',
            ])

            <section class="public-content-section">
                <h2>How it works</h2>
                <p class="text-sm leading-relaxed text-slate-600 sm:text-base">
                    We inspect the car and show you the estimate. If you want financing, we’ll walk you through the options that fit that repair. Not every job qualifies — approval and terms depend on the program.
                </p>
            </section>

            @if ($trustSignals['financing']['available'])
                <section class="public-content-section">
                    <h2>Programs we offer</h2>
                    <ul class="public-financing-programs">
                        <li>
                            <p class="public-financing-programs__name">Wisetack</p>
                            <p class="public-financing-programs__body">
                                Pay over time on repairs that qualify. During estimate review, we can text you a link to see if you’re approved.
                            </p>
                            @if (filled($trustSignals['financing']['wisetack_url']))
                                <div class="public-financing-programs__actions">
                                    @include('partials.public.wisetack-prequal-button', [
                                        'trustSignals' => $trustSignals,
                                    ])
                                    <a href="{{ $trustSignals['financing']['wisetack_url'] }}" target="_blank" rel="noopener noreferrer" class="public-link">
                                        Prequalify with Wisetack →
                                    </a>
                                </div>
                            @endif
                        </li>
                        <li>
                            <p class="public-financing-programs__name">Synchrony Car Care™</p>
                            <p class="public-financing-programs__body">
                                A credit card made for auto repair and maintenance at shops that take Synchrony.
                            </p>
                            @if (filled($trustSignals['financing']['synchrony_url']))
                                <div class="public-financing-programs__actions">
                                    @include('partials.public.synchrony-apply-button', [
                                        'trustSignals' => $trustSignals,
                                    ])
                                    <a href="{{ $trustSignals['financing']['synchrony_url'] }}" target="_blank" rel="noopener noreferrer" class="public-link">
                                        Apply or prequalify with Synchrony Car Care →
                                    </a>
                                </div>
                            @endif
                        </li>
                    </ul>
                </section>
            @endif
        </x-slot:primary>

        <x-slot:rail>
            @include('partials.public.financing-conversion')
        </x-slot:rail>
    </x-customer.split-page>
</x-public.lead-intake>
