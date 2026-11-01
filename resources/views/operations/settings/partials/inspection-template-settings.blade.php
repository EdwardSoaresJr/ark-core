@php
    /** @var list<array<string, mixed>> $inspectionTemplates */
    $inspectionTemplates = $inspectionTemplates ?? [];
@endphp

<form method="POST" action="{{ route('operations.settings.shop.inspection-templates.update') }}" data-settings-inspection-templates>
    @csrf
    @method('PATCH')

    @forelse ($inspectionTemplates as $template)
        <div class="card card-sm mb-3">
            <div class="card-header">
                <div>
                    <h4 class="card-title">{{ $template['name'] }}</h4>
                    <div class="card-subtitle">Technicians see this as the vehicle inspection checklist on mobile.</div>
                </div>
                <div class="card-actions">
                    <label class="form-check form-switch mb-0">
                        <input
                            type="checkbox"
                            name="templates[{{ $template['id'] }}][enabled]"
                            value="1"
                            @checked($template['enabled'])
                            class="form-check-input"
                        >
                        <span class="form-check-label">Enabled</span>
                    </label>
                </div>
            </div>

            @foreach ($template['categories'] as $category)
                <div class="card-body border-top">
                    <div class="subheader mb-2">{{ $category['name'] }}</div>
                    <div class="list-group">
                        @foreach ($category['items'] as $item)
                            @php
                                $itemField = 'templates['.$template['id'].'][categories]['.$category['id'].'][items]['.$item['id'].']';
                                $itemId = 'inspection-item-'.$template['id'].'-'.$category['id'].'-'.$item['id'];
                            @endphp
                            <div class="list-group-item">
                                <label for="{{ $itemId }}" class="visually-hidden">Checklist item</label>
                                <input
                                    id="{{ $itemId }}"
                                    type="text"
                                    name="{{ $itemField }}[label]"
                                    value="{{ $item['label'] }}"
                                    class="form-control fw-medium"
                                >
                                <div class="d-flex flex-wrap gap-3 mt-2">
                                    <label class="form-check mb-0">
                                        <input type="checkbox" name="{{ $itemField }}[enabled]" value="1" @checked($item['enabled']) class="form-check-input">
                                        <span class="form-check-label">Enabled</span>
                                    </label>
                                    <label class="form-check mb-0">
                                        <input type="checkbox" name="{{ $itemField }}[requires_photo]" value="1" @checked($item['requires_photo']) class="form-check-input">
                                        <span class="form-check-label">Photo required</span>
                                    </label>
                                </div>
                                @if (($item['measurement_slots'] ?? []) !== [])
                                    <div class="mt-2">
                                        @foreach ($item['measurement_slots'] as $slotIndex => $slot)
                                            <div class="row g-2 mb-2">
                                                <div class="col-12 col-sm">
                                                    <input
                                                        type="text"
                                                        name="{{ $itemField }}[slots][{{ $slotIndex }}][name]"
                                                        value="{{ $slot['name'] }}"
                                                        aria-label="Measurement name"
                                                        class="form-control form-control-sm"
                                                    >
                                                </div>
                                                <div class="col-7 col-sm-4 col-md-3">
                                                    <select
                                                        name="{{ $itemField }}[slots][{{ $slotIndex }}][type]"
                                                        aria-label="Measurement type"
                                                        class="form-select form-select-sm"
                                                    >
                                                        <option value="number" @selected(($slot['type'] ?? 'number') === 'number')>Number</option>
                                                        <option value="text" @selected(($slot['type'] ?? '') === 'text')>Text</option>
                                                        <option value="condition" @selected(($slot['type'] ?? '') === 'condition')>Condition</option>
                                                    </select>
                                                </div>
                                                <div class="col-5 col-sm-3 col-md-2">
                                                    <input
                                                        type="text"
                                                        name="{{ $itemField }}[slots][{{ $slotIndex }}][unit]"
                                                        value="{{ $slot['unit'] }}"
                                                        placeholder="Unit"
                                                        aria-label="Measurement unit"
                                                        class="form-control form-control-sm"
                                                    >
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @empty
        <p class="text-secondary mb-0">No inspection templates yet. They are created automatically on first mobile checklist load.</p>
    @endforelse

    @if ($inspectionTemplates !== [])
        <div class="d-flex justify-content-end border-top pt-3">
            <button type="submit" class="btn btn-primary">Save inspection checklists</button>
        </div>
    @endif
</form>
