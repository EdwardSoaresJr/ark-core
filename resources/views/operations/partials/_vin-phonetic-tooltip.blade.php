@php
    $sections = $sections ?? [];
@endphp

<span class="ops-vin-phonetic-head">
    <span class="ops-vin-phonetic-label">Phone readback</span>
</span>
<span class="ops-vin-phonetic-rows">
    @foreach ($sections as $section)
        <span @class([
            'ops-vin-phonetic-section',
            'ops-vin-phonetic-section--serial' => $section['is_serial'],
        ])>
            <span @class([
                'ops-vin-phonetic-row-label',
                'ops-vin-phonetic-row-label--serial' => $section['is_serial'],
            ])>
                {{ $section['label'] }}
                <span class="ops-vin-phonetic-row-meta">{{ $section['meta'] }}</span>
            </span>
            <span @class([
                'ops-vin-phonetic-row',
                'ops-vin-phonetic-row--suffix' => $section['is_serial'],
            ])>
                @foreach ($section['chars'] as $index => $char)
                    <span @class([
                        'ops-vin-phonetic-cell',
                        'ops-vin-phonetic-cell--suffix' => $section['is_serial'],
                    ])>
                        <span class="ops-vin-phonetic-char">{{ $char }}</span>
                        <span class="ops-vin-phonetic-word">{{ $section['phonetic_chars'][$index] ?? $char }}</span>
                    </span>
                @endforeach
            </span>
        </span>
    @endforeach
</span>
