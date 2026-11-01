<x-operations.app title="Reports" ui="tabler">
    <x-tabler.page
        layout="fluid"
        title="Pick a report"
        pretitle="Reports"
        subtitle="Same operational truth as before - one entry point instead of landing inside Executive Pulse. End of Day first; drill into tabs when you need depth."
    >
        @foreach ($sections as $section)
            <section class="mb-4" aria-labelledby="reports-section-{{ $loop->index }}" data-reports-section>
                <h3 class="mb-0" id="reports-section-{{ $loop->index }}">{{ $section['label'] }}</h3>
                <p class="text-secondary mb-2">{{ $section['description'] }}</p>
                <div class="ark-card-grid">
                    @foreach ($section['cards'] as $card)
                        <a href="{{ $card['url'] }}" class="card card-sm card-link text-reset" data-reports-card>
                            <div class="card-body d-flex align-items-start gap-3">
                                <span class="avatar bg-primary-lt" aria-hidden="true">
                                    <x-tabler.icon name="chart-bar" />
                                </span>
                                <span>
                                    <span class="d-block fw-bold">{{ $card['title'] }}</span>
                                    <span class="d-block text-secondary small mt-1">{{ $card['hint'] }}</span>
                                </span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
        @endforeach
    </x-tabler.page>
</x-operations.app>
