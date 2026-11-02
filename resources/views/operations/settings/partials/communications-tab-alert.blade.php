@if ($operationalNotes !== [] && in_array($communicationsTab, ['hours', 'recording', 'ring'], true))
    <div class="alert alert-warning small">
        <ul class="mb-0 ps-3">
            @foreach ($operationalNotes as $note)
                <li>{{ $note }}</li>
            @endforeach
        </ul>
        <p class="small mt-2 mb-0">
            Full telephony status and webhook URLs are on the
            <a href="{{ route('operations.settings.shop.edit', ['section' => 'communications', 'communications-tab' => 'general']) }}" class="fw-semibold">General</a>
            tab.
        </p>
    </div>
@endif
