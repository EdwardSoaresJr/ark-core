<section class="identity-band">
    @foreach (['customer', 'vehicle', 'visit'] as $column)
        <div class="identity-col">
            <p class="eyebrow">{{ ucfirst($column) }}</p>
            <h3>{{ $identity[$column]['title'] }}</h3>
            @if ($column === 'vehicle' && filled($identity['vehicle']['subtitle'] ?? null))
                <p class="muted vehicle-descriptor">{{ $identity['vehicle']['subtitle'] }}</p>
            @endif
            @foreach ($identity[$column]['lines'] as $line)
                @include('operations.documents.partials._identity-line', ['line' => $line])
            @endforeach
        </div>
    @endforeach
</section>
