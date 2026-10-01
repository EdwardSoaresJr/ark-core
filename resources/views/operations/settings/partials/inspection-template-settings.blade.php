@php
    /** @var list<array<string, mixed>> $inspectionTemplates */
    $inspectionTemplates = $inspectionTemplates ?? [];
@endphp

<form method="POST" action="{{ route('operations.settings.shop.inspection-templates.update') }}" class="mt-4 space-y-6">
    @csrf
    @method('PATCH')

    @forelse ($inspectionTemplates as $template)
        <div class="border border-slate-200">
            <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-black text-slate-950">{{ $template['name'] }}</p>
                        <p class="text-xs text-slate-500">Technicians see this as the vehicle inspection checklist on mobile.</p>
                    </div>
                    <label class="flex items-center gap-2 text-xs font-semibold text-slate-600">
                        <input
                            type="checkbox"
                            name="templates[{{ $template['id'] }}][enabled]"
                            value="1"
                            @checked($template['enabled'])
                            class="rounded border-slate-300"
                        >
                        Enabled
                    </label>
                </div>
            </div>

            @foreach ($template['categories'] as $category)
                <div class="border-t border-slate-100 px-3 py-3">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ $category['name'] }}</p>
                    <div class="mt-2 space-y-2">
                        @foreach ($category['items'] as $item)
                            <div class="grid gap-2 border border-slate-100 bg-white p-2 md:grid-cols-[1fr_auto_auto] md:items-center">
                                <div>
                                    <input
                                        type="text"
                                        name="templates[{{ $template['id'] }}][categories][{{ $category['id'] }}][items][{{ $item['id'] }}][label]"
                                        value="{{ $item['label'] }}"
                                        class="w-full rounded border-slate-300 text-sm font-medium text-slate-950"
                                    >
                                    <div class="mt-1 flex flex-wrap gap-3 text-[11px] text-slate-500">
                                        <label class="inline-flex items-center gap-1">
                                            <input
                                                type="checkbox"
                                                name="templates[{{ $template['id'] }}][categories][{{ $category['id'] }}][items][{{ $item['id'] }}][enabled]"
                                                value="1"
                                                @checked($item['enabled'])
                                                class="rounded border-slate-300"
                                            >
                                            Enabled
                                        </label>
                                        <label class="inline-flex items-center gap-1">
                                            <input
                                                type="checkbox"
                                                name="templates[{{ $template['id'] }}][categories][{{ $category['id'] }}][items][{{ $item['id'] }}][requires_photo]"
                                                value="1"
                                                @checked($item['requires_photo'])
                                                class="rounded border-slate-300"
                                            >
                                            Photo required
                                        </label>
                                    </div>
                                </div>
                                <input
                                    type="text"
                                    name="templates[{{ $template['id'] }}][categories][{{ $category['id'] }}][items][{{ $item['id'] }}][measurement_name]"
                                    value="{{ $item['measurement_name'] }}"
                                    placeholder="Measurement name"
                                    class="rounded border-slate-300 text-xs"
                                >
                                <input
                                    type="text"
                                    name="templates[{{ $template['id'] }}][categories][{{ $category['id'] }}][items][{{ $item['id'] }}][measurement_unit]"
                                    value="{{ $item['measurement_unit'] }}"
                                    placeholder="Unit"
                                    class="rounded border-slate-300 text-xs"
                                >
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @empty
        <p class="text-sm text-slate-500">No inspection templates yet. They are created automatically on first mobile checklist load.</p>
    @endforelse

    @if ($inspectionTemplates !== [])
        <div>
            <button type="submit" class="min-h-10 rounded-md bg-slate-950 px-5 text-sm font-semibold text-white hover:bg-slate-800">
                Save inspection templates
            </button>
        </div>
    @endif
</form>
