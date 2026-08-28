<section class="section">
    <div class="checkin-grid staff-capture-grid">
        @foreach ([
            'advisor' => 'Advisor name',
            'technician' => 'Technician name',
        ] as $key => $label)
            @php($field = $sheet['staff'][$key])
            <div class="worksheet-capture worksheet-capture--staff">
                <span class="staff-capture-label">{{ $label }}</span>
                @if ($field['prefilled'])
                    <p class="staff-capture-value">{{ $field['value'] }}</p>
                @endif
            </div>
        @endforeach
    </div>
</section>
