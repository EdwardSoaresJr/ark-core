@php
    use App\Ark\Operations\Documents\DocumentType;
    $selected = old('type', $selected ?? null);
@endphp
@foreach (DocumentType::options() as $type)
    <option value="{{ $type->value }}" @selected($selected === $type->value)>{{ $type->label() }}</option>
@endforeach
