@php
    use App\Ark\Operations\Leads\LeadContactPreference;

    $selected = old('contact_preference', $selected ?? null);
    $inputId = $inputId ?? 'contact_preference';
    $inputName = $inputName ?? 'contact_preference';
    $labelClass = $labelClass ?? 'block text-xs font-medium text-slate-500';
    $selectClass = $selectClass ?? 'mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm';
    $xModel = $xModel ?? null;
@endphp

<label for="{{ $inputId }}" class="{{ $labelClass }}">
    Preferred contact
    <select
        id="{{ $inputId }}"
        name="{{ $inputName }}"
        class="{{ $selectClass }}"
        @if ($xModel) x-model="{{ $xModel }}" @endif
    >
        <option value="" @unless($xModel) @selected($selected === null || $selected === '') @endunless>Not set</option>
        @foreach (LeadContactPreference::cases() as $option)
            <option value="{{ $option->value }}" @unless($xModel) @selected($selected === $option->value) @endunless>
                {{ $option->formLabel() }}
            </option>
        @endforeach
    </select>
</label>
