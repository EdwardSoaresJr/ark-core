<x-operations.app title="Business" ui="tabler">
    <x-tabler.page
        layout="fluid"
        :title="$business->greeting"
        pretitle="Business"
        subtitle="Market, growth, and yesterday. Operational work lives on Today. This cockpit is for shop health."
    >
        <div class="row row-cards">
            @foreach ($business->nonEmptySections() as $section)
                <div class="col-12">
                    <section class="card" data-business-section="{{ $section->key }}">
                        <div class="card-header">
                            <h3 class="card-title">{{ $section->title }}</h3>
                        </div>
                        @if ($section->key === 'market_pressure' && filled($section->panel))
                            @include('operations.today.partials.market-pressure-panel', ['panel' => $section->panel])
                        @endif
                        @if ($section->actions !== [])
                            <div class="list-group list-group-flush">
                                @foreach ($section->actions as $action)
                                    <a href="{{ $action->url }}" class="list-group-item list-group-item-action">
                                        <div class="fw-bold">{{ $action->title }}</div>
                                        @if (filled($action->reason))
                                            <div class="text-secondary small mt-1">{{ $action->reason }}</div>
                                        @endif
                                        @if (filled($action->expectedOutcome))
                                            <div class="text-secondary small mt-1">{{ $action->expectedOutcome }}</div>
                                        @endif
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </section>
                </div>
            @endforeach

            <div class="col-12">
                <section class="card" data-business-yesterday>
                    <div class="card-header">
                        <h3 class="card-title">Yesterday</h3>
                    </div>
                    <div class="card-body p-2 p-md-3">
                        @if ($business->yesterdaySummary === [])
                            <p class="text-secondary mb-0">No posted activity recorded for yesterday.</p>
                        @else
                            <div class="datagrid">
                                @foreach ($business->yesterdaySummary as $row)
                                    <div class="datagrid-item">
                                        <div class="datagrid-title">{{ $row['label'] }}</div>
                                        <div class="datagrid-content h3 mb-0">{{ $row['value'] }}</div>
                                        @if (! empty($row['hint']))
                                            <div class="text-secondary small mt-1">{{ $row['hint'] }}</div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </section>
            </div>
        </div>

        @if ($business->links !== [])
            <div class="btn-list mt-3" data-business-links>
                @foreach ($business->links as $link)
                    <a href="{{ $link['url'] }}" class="btn">{{ $link['label'] }}</a>
                @endforeach
            </div>
        @endif
    </x-tabler.page>
</x-operations.app>
