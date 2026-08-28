@props([
    'selected' => '',
    'required' => false,
    'name' => 'visit_mode',
])

<div class="ops-intake-open-field">
    <span class="ops-intake-open-field-label">
        Visit
        @if ($required)
            <span class="text-rose-600">*</span>
        @endif
    </span>
    <div
        class="ops-intake-flags-row"
        role="radiogroup"
        aria-label="Visit posture"
        @if ($required) aria-required="true" @endif
    >
        @foreach ([
            'waiting_here' => 'Waiting',
            'drop_off' => 'Drop Off',
            'needs_shuttle' => 'Shuttle',
            'tow_incoming' => 'Tow-In',
        ] as $value => $label)
            <label class="ops-intake-flag ops-intake-flag--compact ops-intake-flag--radio">
                <input
                    name="{{ $name }}"
                    value="{{ $value }}"
                    type="radio"
                    @checked($selected === $value)
                    @if ($required && $loop->first && $selected === '') required @endif
                >
                {{ $label }}
            </label>
        @endforeach
    </div>
    @error($name)
        <p class="text-xs font-semibold text-rose-700">{{ $message }}</p>
    @enderror
</div>
