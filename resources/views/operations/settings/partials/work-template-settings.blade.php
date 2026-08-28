@php
    /** @var \Illuminate\Support\Collection<int, \App\Ark\Operations\WorkTemplates\WorkTemplate> $workTemplates */
    $workTemplates = $workTemplates ?? collect();
@endphp

<div class="mt-4 space-y-6">
    <form
        method="POST"
        action="{{ route('operations.settings.shop.work-templates.store') }}"
        class="rounded border border-slate-200 bg-white p-3"
        x-data="{
            lines: [{ type: 'labor', description: '', quantity: '1.00', unit_price: '', part_cost: '' }],
            addLine() {
                this.lines.push({ type: 'labor', description: '', quantity: '1.00', unit_price: '', part_cost: '' });
            },
            removeLine(index) {
                if (this.lines.length <= 1) {
                    return;
                }
                this.lines.splice(index, 1);
            },
        }"
    >
        @csrf
        <h4 class="text-xs font-black uppercase tracking-[0.08em] text-slate-700">Create Common Job</h4>
        <div class="mt-3 grid gap-3 md:grid-cols-2">
            <label class="block text-xs font-medium text-slate-500 md:col-span-2">
                Title
                <input type="text" name="title" required maxlength="191" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950" placeholder="Front Brake Service">
            </label>
            <label class="block text-xs font-medium text-slate-500 md:col-span-2">
                Description <span class="font-normal text-slate-400">(optional)</span>
                <input type="text" name="description" maxlength="1000" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950">
            </label>
            <label class="block text-xs font-medium text-slate-500 md:col-span-2">
                Internal note <span class="font-normal text-slate-400">(optional — becomes a private note line)</span>
                <textarea name="internal_note" rows="2" maxlength="2000" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950"></textarea>
            </label>
            <label class="block text-xs font-medium text-slate-500 md:col-span-2">
                Recommendation status
                @include('operations.repair-orders.partials.recommendation-intent-select', [
                    'selected' => \App\Ark\Operations\RepairOrders\RecommendationIntent::Maintenance->value,
                    'inputId' => 'work-template-create-intent',
                    'selectClass' => 'mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950',
                ])
                <span class="mt-1 block font-normal text-slate-400">Used when this saved job creates a new concern. Attaching to an existing concern keeps that concern’s status.</span>
            </label>
        </div>

        <div class="mt-4 space-y-2">
            <div class="flex items-center justify-between">
                <p class="text-xs font-black uppercase tracking-[0.08em] text-slate-700">Lines</p>
                <button type="button" class="text-xs font-semibold text-slate-700 hover:text-slate-950" @click="addLine()">+ Line</button>
            </div>
            <template x-for="(line, index) in lines" :key="index">
                <div class="grid gap-2 rounded border border-slate-100 bg-slate-50 p-2 md:grid-cols-12">
                    <label class="block text-xs font-medium text-slate-500 md:col-span-2">
                        Type
                        <select :name="`lines[${index}][type]`" x-model="line.type" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm text-slate-950">
                            <option value="labor">Labor</option>
                            <option value="part">Part</option>
                            <option value="fee">Fee</option>
                            <option value="note">Note</option>
                        </select>
                    </label>
                    <label class="block text-xs font-medium text-slate-500 md:col-span-5">
                        Description
                        <input type="text" :name="`lines[${index}][description]`" x-model="line.description" required maxlength="2000" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm text-slate-950">
                    </label>
                    <label class="block text-xs font-medium text-slate-500 md:col-span-2">
                        <span x-text="line.type === 'labor' ? 'Hours' : 'Qty'"></span>
                        <input type="number" step="0.01" min="0" :name="`lines[${index}][quantity]`" x-model="line.quantity" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm text-slate-950">
                    </label>
                    <label class="block text-xs font-medium text-slate-500 md:col-span-2">
                        <span x-text="line.type === 'labor' ? 'Rate $' : (line.type === 'fee' ? 'Fee $' : 'Sell $')"></span>
                        <input type="text" inputmode="decimal" :name="`lines[${index}][unit_price]`" x-model="line.unit_price" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm text-slate-950" placeholder="0.00">
                    </label>
                    <label class="block text-xs font-medium text-slate-500 md:col-span-2">
                        Cost $
                        <input type="text" inputmode="decimal" :name="`lines[${index}][part_cost]`" x-model="line.part_cost" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm text-slate-950" placeholder="parts">
                    </label>
                    <div class="flex items-end md:col-span-1">
                        <button type="button" class="rounded border border-slate-200 bg-white px-2 py-1.5 text-xs text-slate-600" @click="removeLine(index)" :disabled="lines.length <= 1">×</button>
                    </div>
                </div>
            </template>
        </div>

        <div class="mt-4">
            <button type="submit" class="rounded bg-slate-950 px-3 py-2 text-xs font-bold text-white">Create Common Job</button>
        </div>
    </form>

    <div class="space-y-3">
        <h4 class="text-xs font-black uppercase tracking-[0.08em] text-slate-700">Shop library</h4>
        @forelse ($workTemplates as $template)
            <article @class([
                'rounded border p-3',
                'border-slate-200 bg-white' => ! $template->isRetired(),
                'border-slate-100 bg-slate-50 opacity-70' => $template->isRetired(),
            ])>
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <h5 class="text-sm font-bold text-slate-950">{{ $template->title }}</h5>
                        <p class="mt-0.5 text-[11px] font-semibold text-slate-600">{{ $template->recommendationIntent()->staffLabel() }}</p>
                        @if ($template->description)
                            <p class="mt-0.5 text-xs text-slate-500">{{ $template->description }}</p>
                        @endif
                        @if ($template->isRetired())
                            <p class="mt-1 text-[10px] font-bold uppercase tracking-wide text-amber-700">Retired</p>
                        @endif
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('operations.settings.shop.work-templates.duplicate', $template) }}">
                            @csrf
                            <button type="submit" class="text-xs font-semibold text-slate-600 hover:text-slate-950">Duplicate</button>
                        </form>
                        @if ($template->isRetired())
                            <form method="POST" action="{{ route('operations.settings.shop.work-templates.restore', $template) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="text-xs font-semibold text-slate-600 hover:text-slate-950">Restore</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('operations.settings.shop.work-templates.retire', $template) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="text-xs font-semibold text-amber-700 hover:text-amber-900">Retire</button>
                            </form>
                        @endif
                    </div>
                </div>
                <ul class="mt-2 space-y-1 text-xs text-slate-600">
                    @foreach ($template->lines as $line)
                        <li>
                            <span class="font-semibold text-slate-800">{{ $line->type->label() }}</span>
                            — {{ $line->description }}
                            @if ($line->type->isLabor())
                                · {{ rtrim(rtrim(number_format((float) $line->quantity, 2, '.', ''), '0'), '.') }} hr
                                @if ($line->unit_price_cents)
                                    · ${{ number_format($line->unit_price_cents / 100, 2) }}/hr
                                @endif
                            @elseif ($line->type->isPart())
                                · qty {{ rtrim(rtrim(number_format((float) $line->quantity, 2, '.', ''), '0'), '.') }}
                                @if ($line->part_cost_cents !== null)
                                    · cost ${{ number_format($line->part_cost_cents / 100, 2) }}
                                @endif
                                @if ($line->unit_price_cents !== null)
                                    · sell ${{ number_format($line->unit_price_cents / 100, 2) }}
                                @endif
                            @elseif ($line->type === \App\Ark\Operations\RepairOrders\RepairOrderLineType::Fee && $line->unit_price_cents)
                                · ${{ number_format($line->unit_price_cents / 100, 2) }}
                            @endif
                        </li>
                    @endforeach
                </ul>

                @unless ($template->isRetired())
                    <details class="mt-3">
                        <summary class="cursor-pointer text-xs font-semibold text-slate-700">Edit</summary>
                        <form method="POST" action="{{ route('operations.settings.shop.work-templates.update', $template) }}" class="mt-3 space-y-3">
                            @csrf
                            @method('PUT')
                            <label class="block text-xs font-medium text-slate-500">
                                Title
                                <input type="text" name="title" value="{{ $template->title }}" required maxlength="191" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950">
                            </label>
                            <label class="block text-xs font-medium text-slate-500">
                                Description
                                <input type="text" name="description" value="{{ $template->description }}" maxlength="1000" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950">
                            </label>
                            <label class="block text-xs font-medium text-slate-500">
                                Internal note
                                <textarea name="internal_note" rows="2" maxlength="2000" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950">{{ $template->internal_note }}</textarea>
                            </label>
                            <label class="block text-xs font-medium text-slate-500">
                                Recommendation status
                                @include('operations.repair-orders.partials.recommendation-intent-select', [
                                    'selected' => $template->recommendationIntent()->value,
                                    'inputId' => 'work-template-edit-intent-'.$template->id,
                                    'selectClass' => 'mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950',
                                ])
                            </label>
                            @foreach ($template->lines as $index => $line)
                                <div class="grid gap-2 rounded border border-slate-100 bg-slate-50 p-2 md:grid-cols-12">
                                    <label class="block text-xs font-medium text-slate-500 md:col-span-2">
                                        Type
                                        <select name="lines[{{ $index }}][type]" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm text-slate-950">
                                            @foreach (['labor', 'part', 'fee', 'note'] as $type)
                                                <option value="{{ $type }}" @selected($line->type->value === $type)>{{ ucfirst($type) }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="block text-xs font-medium text-slate-500 md:col-span-5">
                                        Description
                                        <input type="text" name="lines[{{ $index }}][description]" value="{{ $line->description }}" required class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm text-slate-950">
                                    </label>
                                    <label class="block text-xs font-medium text-slate-500 md:col-span-2">
                                        Qty / hours
                                        <input type="number" step="0.01" min="0" name="lines[{{ $index }}][quantity]" value="{{ $line->quantity }}" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm text-slate-950">
                                    </label>
                                    <label class="block text-xs font-medium text-slate-500 md:col-span-2">
                                        Rate / sell / fee $
                                        <input type="text" inputmode="decimal" name="lines[{{ $index }}][unit_price]" value="{{ $line->unit_price_cents !== null ? number_format($line->unit_price_cents / 100, 2, '.', '') : '' }}" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm text-slate-950" placeholder="sell">
                                    </label>
                                    <label class="block text-xs font-medium text-slate-500 md:col-span-2">
                                        Cost $
                                        <input type="text" inputmode="decimal" name="lines[{{ $index }}][part_cost]" value="{{ $line->part_cost_cents !== null ? number_format($line->part_cost_cents / 100, 2, '.', '') : '' }}" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm text-slate-950" placeholder="part cost">
                                    </label>
                                </div>
                            @endforeach
                            <button type="submit" class="rounded bg-slate-950 px-3 py-2 text-xs font-bold text-white">Save changes</button>
                        </form>
                    </details>
                @endunless
            </article>
        @empty
            <p class="text-xs text-slate-500">No Common Jobs yet. Create Front Brake Service or similar above.</p>
        @endforelse
    </div>
</div>
