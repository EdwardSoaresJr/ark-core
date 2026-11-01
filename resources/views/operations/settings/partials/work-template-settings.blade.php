@php
    /** @var \Illuminate\Support\Collection<int, \App\Ark\Operations\WorkTemplates\WorkTemplate> $workTemplates */
    $workTemplates = $workTemplates ?? collect();
@endphp

<div data-settings-work-templates>
    <form
        method="POST"
        action="{{ route('operations.settings.shop.work-templates.store') }}"
        class="card card-sm mb-4"
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
        <div class="card-header">
            <h4 class="card-title">Create Common Job</h4>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12">
                    <label for="work-template-create-title" class="form-label required">Title</label>
                    <input id="work-template-create-title" type="text" name="title" required maxlength="191" class="form-control" placeholder="Front Brake Service">
                </div>
                <div class="col-12">
                    <label for="work-template-create-description" class="form-label">Description <span class="text-secondary fw-normal">(optional)</span></label>
                    <input id="work-template-create-description" type="text" name="description" maxlength="1000" class="form-control">
                </div>
                <div class="col-12">
                    <label for="work-template-create-note" class="form-label">Internal note <span class="text-secondary fw-normal">(optional - becomes a private note line)</span></label>
                    <textarea id="work-template-create-note" name="internal_note" rows="2" maxlength="2000" class="form-control"></textarea>
                </div>
                <div class="col-12 col-md-6">
                    <label for="work-template-create-priority" class="form-label">Priority</label>
                    @include('operations.repair-orders.partials.recommendation-priority-select', [
                        'inputId' => 'work-template-create-priority',
                        'selectClass' => 'form-select',
                    ])
                </div>
                <div class="col-12 col-md-6">
                    <label for="work-template-create-intent" class="form-label">Work type</label>
                    @include('operations.repair-orders.partials.recommendation-intent-select', [
                        'selected' => \App\Ark\Operations\RepairOrders\RecommendationIntent::Maintenance->value,
                        'inputId' => 'work-template-create-intent',
                        'selectClass' => 'form-select',
                    ])
                    <div class="form-hint">Used when this saved job creates a new concern. Attaching to an existing concern keeps that concern’s priority and work type.</div>
                </div>
            </div>

            <div class="d-flex align-items-center justify-content-between mt-4 mb-2">
                <div class="subheader">Lines</div>
                <button type="button" class="btn btn-sm" @click="addLine()">+ Line</button>
            </div>
            <template x-for="(line, index) in lines" :key="index">
                <div class="row g-2 align-items-end border rounded p-2 mx-0 mb-2" data-work-template-line>
                    <div class="col-6 col-md-2">
                        <label class="form-label" :for="`work-template-line-${index}-type`">Type</label>
                        <select :id="`work-template-line-${index}-type`" :name="`lines[${index}][type]`" x-model="line.type" class="form-select">
                            <option value="labor">Labor</option>
                            <option value="part">Part</option>
                            <option value="fee">Fee</option>
                            <option value="note">Note</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-4 order-first order-md-0">
                        <label class="form-label" :for="`work-template-line-${index}-description`">Description</label>
                        <input type="text" :id="`work-template-line-${index}-description`" :name="`lines[${index}][description]`" x-model="line.description" required maxlength="2000" class="form-control">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label" :for="`work-template-line-${index}-quantity`" x-text="line.type === 'labor' ? 'Hours' : 'Qty'"></label>
                        <input type="number" step="0.01" min="0" :id="`work-template-line-${index}-quantity`" :name="`lines[${index}][quantity]`" x-model="line.quantity" class="form-control">
                    </div>
                    <div class="col-5 col-md-2">
                        <label class="form-label" :for="`work-template-line-${index}-price`" x-text="line.type === 'labor' ? 'Rate $' : (line.type === 'fee' ? 'Fee $' : 'Sell $')"></label>
                        <input type="text" inputmode="decimal" :id="`work-template-line-${index}-price`" :name="`lines[${index}][unit_price]`" x-model="line.unit_price" class="form-control" placeholder="0.00">
                    </div>
                    <div class="col-5 col-md-1">
                        <label class="form-label" :for="`work-template-line-${index}-cost`">Cost $</label>
                        <input type="text" inputmode="decimal" :id="`work-template-line-${index}-cost`" :name="`lines[${index}][part_cost]`" x-model="line.part_cost" class="form-control" placeholder="parts">
                    </div>
                    <div class="col-2 col-md-1 text-end">
                        <button type="button" class="btn btn-icon btn-ghost-secondary" aria-label="Remove line" @click="removeLine(index)" :disabled="lines.length <= 1">×</button>
                    </div>
                </div>
            </template>
        </div>
        <div class="card-footer text-end">
            <button type="submit" class="btn btn-primary">Create Common Job</button>
        </div>
    </form>

    <div class="subheader mb-2">Shop library</div>
    @forelse ($workTemplates as $template)
        <article @class(['card card-sm mb-2', 'opacity-75' => $template->isRetired()]) data-work-template="{{ $template->id }}">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
                    <div>
                        <h5 class="mb-1">
                            {{ $template->title }}
                            @if ($template->isRetired())
                                <span class="badge bg-yellow-lt ms-1">Retired</span>
                            @endif
                        </h5>
                        <div class="small fw-medium text-secondary">
                            {{ $template->presentation()->staffLabel() ?? 'No priority' }}
                            @if ($template->recommendationIntent())
                                · {{ $template->recommendationIntent()->staffLabel() }}
                            @endif
                        </div>
                        @if ($template->description)
                            <div class="small text-secondary">{{ $template->description }}</div>
                        @endif
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('operations.settings.shop.work-templates.duplicate', $template) }}">
                            @csrf
                            <button type="submit" class="btn btn-sm">Duplicate</button>
                        </form>
                        @if ($template->isRetired())
                            <form method="POST" action="{{ route('operations.settings.shop.work-templates.restore', $template) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn btn-sm">Restore</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('operations.settings.shop.work-templates.retire', $template) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn btn-sm btn-outline-warning">Retire</button>
                            </form>
                        @endif
                    </div>
                </div>
                <ul class="list-unstyled small text-secondary mt-2 mb-0">
                    @foreach ($template->lines as $line)
                        <li>
                            <span class="fw-medium text-body">{{ $line->type->label() }}</span>
                            - {{ $line->description }}
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
                        <summary class="small fw-medium" style="cursor: pointer">Edit</summary>
                        <form method="POST" action="{{ route('operations.settings.shop.work-templates.update', $template) }}" class="mt-3">
                            @csrf
                            @method('PUT')
                            <div class="row g-3">
                                <div class="col-12">
                                    <label for="work-template-edit-title-{{ $template->id }}" class="form-label required">Title</label>
                                    <input id="work-template-edit-title-{{ $template->id }}" type="text" name="title" value="{{ $template->title }}" required maxlength="191" class="form-control">
                                </div>
                                <div class="col-12">
                                    <label for="work-template-edit-description-{{ $template->id }}" class="form-label">Description</label>
                                    <input id="work-template-edit-description-{{ $template->id }}" type="text" name="description" value="{{ $template->description }}" maxlength="1000" class="form-control">
                                </div>
                                <div class="col-12">
                                    <label for="work-template-edit-note-{{ $template->id }}" class="form-label">Internal note</label>
                                    <textarea id="work-template-edit-note-{{ $template->id }}" name="internal_note" rows="2" maxlength="2000" class="form-control">{{ $template->internal_note }}</textarea>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="work-template-edit-priority-{{ $template->id }}" class="form-label">Priority</label>
                                    @include('operations.repair-orders.partials.recommendation-priority-select', [
                                        'selectedPriority' => $template->recommendationPriority()?->value,
                                        'informationOnly' => $template->isInformationOnly(),
                                        'inputId' => 'work-template-edit-priority-'.$template->id,
                                        'selectClass' => 'form-select',
                                    ])
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="work-template-edit-intent-{{ $template->id }}" class="form-label">Work type</label>
                                    @include('operations.repair-orders.partials.recommendation-intent-select', [
                                        'selected' => $template->recommendationIntent()?->value,
                                        'inputId' => 'work-template-edit-intent-'.$template->id,
                                        'selectClass' => 'form-select',
                                    ])
                                </div>
                            </div>
                            @foreach ($template->lines as $index => $line)
                                @php($lineId = 'work-template-'.$template->id.'-line-'.$index)
                                <div class="row g-2 align-items-end border rounded p-2 mx-0 mt-2">
                                    <div class="col-6 col-md-2">
                                        <label for="{{ $lineId }}-type" class="form-label">Type</label>
                                        <select id="{{ $lineId }}-type" name="lines[{{ $index }}][type]" class="form-select">
                                            @foreach (['labor', 'part', 'fee', 'note'] as $type)
                                                <option value="{{ $type }}" @selected($line->type->value === $type)>{{ ucfirst($type) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-4 order-first order-md-0">
                                        <label for="{{ $lineId }}-description" class="form-label">Description</label>
                                        <input id="{{ $lineId }}-description" type="text" name="lines[{{ $index }}][description]" value="{{ $line->description }}" required class="form-control">
                                    </div>
                                    <div class="col-6 col-md-2">
                                        <label for="{{ $lineId }}-quantity" class="form-label">Qty / hours</label>
                                        <input id="{{ $lineId }}-quantity" type="number" step="0.01" min="0" name="lines[{{ $index }}][quantity]" value="{{ $line->quantity }}" class="form-control">
                                    </div>
                                    <div class="col-6 col-md-2">
                                        <label for="{{ $lineId }}-price" class="form-label">Rate / sell / fee $</label>
                                        <input id="{{ $lineId }}-price" type="text" inputmode="decimal" name="lines[{{ $index }}][unit_price]" value="{{ $line->unit_price_cents !== null ? number_format($line->unit_price_cents / 100, 2, '.', '') : '' }}" class="form-control" placeholder="sell">
                                    </div>
                                    <div class="col-6 col-md-2">
                                        <label for="{{ $lineId }}-cost" class="form-label">Cost $</label>
                                        <input id="{{ $lineId }}-cost" type="text" inputmode="decimal" name="lines[{{ $index }}][part_cost]" value="{{ $line->part_cost_cents !== null ? number_format($line->part_cost_cents / 100, 2, '.', '') : '' }}" class="form-control" placeholder="part cost">
                                    </div>
                                </div>
                            @endforeach
                            <div class="text-end mt-3">
                                <button type="submit" class="btn btn-primary">Save changes</button>
                            </div>
                        </form>
                    </details>
                @endunless
            </div>
        </article>
    @empty
        <p class="text-secondary small mb-0">No Common Jobs yet. Create Front Brake Service or similar above.</p>
    @endforelse
</div>
