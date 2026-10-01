<section class="section">
    <div class="checkin-grid mileage-capture-grid">
        @foreach ([
            'in' => 'Mileage in',
            'out' => 'Mileage out',
        ] as $key => $label)
            @php($field = $sheet['mileage'][$key])
            <div class="worksheet-capture worksheet-capture--mileage">
                <div class="mileage-capture-head">
                    <span class="mileage-capture-label">{{ $label }}</span>
                    @if ($field['prefilled'])
                        <span class="mileage-capture-verify">
                            <span class="pull-box" aria-hidden="true"></span>
                            Verified
                        </span>
                    @endif
                </div>
                @if ($field['prefilled'])
                    <p class="mileage-capture-value">{{ $field['value'] }}</p>
                @endif
            </div>
        @endforeach
    </div>
</section>
