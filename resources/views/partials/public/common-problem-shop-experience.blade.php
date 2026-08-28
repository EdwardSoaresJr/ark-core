@php
    /** @var array $shopExperience */
@endphp

@if ($shopExperience['has_signals'])
    <section class="public-shop-experience" aria-label="Shop repair experience">
        <div class="public-shop-experience__header">
            <h2>What we see at our shop</h2>
            @if ($shopExperience['verified_repair_count'] > 0)
                <p class="public-shop-experience__meta">
                    Based on {{ number_format($shopExperience['verified_repair_count']) }} verified repair{{ $shopExperience['verified_repair_count'] === 1 ? '' : 's' }} here.
                </p>
            @endif
        </div>

        <dl class="public-shop-experience__signals">
            @if (filled($shopExperience['most_common_fix']))
                <div>
                    <dt>Most common fix here</dt>
                    <dd>{{ $shopExperience['most_common_fix'] }}</dd>
                </div>
            @endif
            @if (filled($shopExperience['average_diagnostic_time_label']))
                <div>
                    <dt>Typical time to diagnose</dt>
                    <dd>{{ $shopExperience['average_diagnostic_time_label'] }}</dd>
                </div>
            @endif
            @if (filled($shopExperience['last_updated_label']))
                <div>
                    <dt>Last updated</dt>
                    <dd>{{ $shopExperience['last_updated_label'] }}</dd>
                </div>
            @endif
        </dl>

        @if ($shopExperience['repairs'] !== [])
            <div class="public-shop-experience__repairs">
                <h3>Recent repairs we&apos;ve done</h3>
                <ul class="public-shop-experience__repair-list">
                    @foreach ($shopExperience['repairs'] as $repair)
                        <li>
                            <p class="public-shop-experience__repair-vehicle">{{ $repair['vehicle'] }}</p>
                            <p class="public-shop-experience__repair-summary">{{ $repair['summary'] }}</p>
                            @if (filled($repair['outcome']))
                                <p class="public-shop-experience__repair-outcome">{{ $repair['outcome'] }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </section>
@endif
