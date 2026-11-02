@php
    $tabletMode = (bool) ($tabletMode ?? false);
    $nav = $living_record['navigation'] ?? [];
    $prior = $living_record['prior_visits'] ?? ['available' => false, 'items' => []];
    $measurementName = $living_record['measurement_name'] ?? 'Measurement';
    $measurementUnit = $living_record['measurement_unit'] ?? '';
    $currentMeasurement = $living_record['measurement']['value'] ?? '';
    $currentUnit = $living_record['measurement']['unit'] ?? $measurementUnit;
    $slots = $living_record['measurement_slots'] ?? [];
    $isAxleGate = (bool) ($living_record['is_axle_gate'] ?? false);
    $photoStoreUrl = route('operations.repair-orders.inspection.photos.store', [$repairOrder, $living_record['id']]);
    if ($tabletMode) {
        $photoStoreUrl .= (str_contains($photoStoreUrl, '?') ? '&' : '?').'surface=tablet';
    }
    $checked = (int) ($progress['checked'] ?? 0);
    $total = (int) ($progress['total'] ?? 0);
    $pct = $total > 0 ? (int) round(($checked / $total) * 100) : 0;
    $priorOpen = ($prior['available'] ?? false) && count($prior['items'] ?? []) > 0;
    $conditionOptions = $condition_options;
    if ($living_record['road_test_force_na'] ?? false) {
        $conditionOptions = array_values(array_filter(
            $condition_options,
            fn ($option) => ($option['value'] ?? '') === 'na',
        ));
    }
    $conditionColor = fn (string $value): string => match ($value) {
        'good' => 'success',
        'monitor' => 'warning',
        'needs_attention', 'failed' => 'danger',
        'na' => 'secondary',
        default => 'primary',
    };
@endphp

<div
    class="d-flex flex-column gap-3"
    id="inspection-walk"
    data-inspection-walk
    data-surface="{{ $tabletMode ? 'tablet' : 'standard' }}"
    x-data="arkInspectionWalk(@js([
        'updateUrl' => $living_record['update_url'],
        'csrf' => csrf_token(),
        'currentStatus' => $living_record['status'],
        'note' => $living_record['note'] ?? '',
        'measurementValue' => $currentMeasurement,
        'measurementUnit' => $currentUnit,
        'measurementSlots' => $slots,
        'rearAxleBrakeType' => $living_record['rear_axle_brake_type'] ?? null,
        'isAxleGate' => $isAxleGate,
        'roadTestFindingLocked' => (bool) ($living_record['road_test_finding_locked'] ?? false),
        'roadTestForceNa' => (bool) ($living_record['road_test_force_na'] ?? false),
        'brakePrompts' => $living_record['brake_prompts'] ?? [],
        'missingSlots' => $living_record['missing_measurement_slots'] ?? [],
    ]))"
>
    <div class="card">
        <div class="card-body p-3">
            <div class="d-flex align-items-start gap-2">
                <div class="flex-fill" style="min-width: 0">
                    <div class="small text-secondary">
                        @if (! empty($template_name ?? null))
                            {{ $template_name }} ·
                        @endif
                        Point {{ $nav['index'] ?? '-' }} of {{ $nav['total'] ?? '-' }}
                        · {{ $checked }}/{{ $total }} checked
                    </div>
                    <h2 @class(['mb-0 text-break', 'h1' => $tabletMode, 'h2' => ! $tabletMode]) data-inspection-walk-title>{{ $living_record['label'] }}</h2>
                    @if ($living_record['category_name'] ?? null)
                        <div class="text-secondary">{{ $living_record['category_name'] }}</div>
                    @endif
                </div>
                @if (! $tabletMode && $canEdit && ($nav['next_url'] ?? null))
                    <a href="{{ $nav['next_url'] }}" class="btn btn-primary flex-shrink-0">
                        Next
                        <x-tabler.icon name="arrow-right" />
                    </a>
                @endif
            </div>

            @if ($tabletMode)
                <div class="progress progress-sm mt-3" aria-hidden="true" data-inspection-walk-meter>
                    <div class="progress-bar" style="width: {{ $pct }}%"></div>
                </div>
            @else
                <nav class="d-flex flex-wrap gap-1 mt-3" aria-label="Inspection walk order">
                    @foreach ($walk_points as $point)
                        <a
                            href="{{ $point['url'] }}"
                            @class([
                                'btn btn-sm btn-icon',
                                'btn-primary' => $point['is_current'],
                                'btn-success' => ! $point['is_current'] && $point['checked'],
                            ])
                            title="{{ $point['label'] }}"
                            @if ($point['is_current']) aria-current="step" @endif
                        >
                            <span aria-hidden="true">{{ $loop->iteration }}</span>
                            <span class="visually-hidden">{{ $point['label'] }}</span>
                        </a>
                    @endforeach
                </nav>
            @endif
        </div>
    </div>

    @if ($tabletMode)
        <div class="row g-3">
            <div class="col-12 col-lg-7 d-flex flex-column gap-3">
    @endif

    @if ($isAxleGate && $canEdit)
        <section class="card" aria-labelledby="inspection-axle-heading">
            <div class="card-body p-3">
                <h3 id="inspection-axle-heading" class="card-title mb-1">Rear axle brake type</h3>
                <p class="text-secondary mb-2">One choice for the rear axle - Disc or Drum.</p>
                <div class="d-flex gap-2" role="group" aria-label="Rear axle type">
                    <button type="button" @class(['btn flex-fill', 'btn-xl' => $tabletMode, 'btn-lg' => ! $tabletMode]) :class="rearAxleBrakeType === 'disc' ? 'btn-primary' : 'btn-outline-primary'" :aria-pressed="rearAxleBrakeType === 'disc' ? 'true' : 'false'" x-on:click="setRearAxle('disc')">Disc</button>
                    <button type="button" @class(['btn flex-fill', 'btn-xl' => $tabletMode, 'btn-lg' => ! $tabletMode]) :class="rearAxleBrakeType === 'drum' ? 'btn-primary' : 'btn-outline-primary'" :aria-pressed="rearAxleBrakeType === 'drum' ? 'true' : 'false'" x-on:click="setRearAxle('drum')">Drum</button>
                </div>
                <p class="small text-secondary mt-2 mb-0" x-show="saving" x-cloak>Saving…</p>
            </div>
        </section>
    @else
        <section class="card" aria-labelledby="inspection-condition-heading">
            <div class="card-body p-3">
                <h3 id="inspection-condition-heading" class="card-title mb-2">
                    @if (($living_record['gate_group'] ?? null) === 'road_test_performed')
                        Road test performed
                    @else
                        Condition
                    @endif
                </h3>
                @if (($living_record['road_test_finding_locked'] ?? false))
                    <p class="text-secondary mb-0">Mark road test performed before recording findings.</p>
                @elseif ($canEdit)
                    @if ($tabletMode)
                        <div class="row g-2" role="group" aria-label="Set condition">
                            @foreach ($conditionOptions as $option)
                                <div @class(['col-12' => $loop->last && $loop->count % 2 === 1, 'col-6' => ! ($loop->last && $loop->count % 2 === 1)])>
                                    <button
                                        type="button"
                                        class="btn btn-xl w-100 h-100 px-2 text-wrap"
                                        :class="currentStatus === @js($option['value']) ? 'btn-{{ $conditionColor($option['value']) }}' : 'btn-outline-{{ $conditionColor($option['value']) }}'"
                                        :aria-pressed="currentStatus === @js($option['value']) ? 'true' : 'false'"
                                        data-condition="{{ $option['value'] }}"
                                        x-on:click="setCondition(@js($option['value']))"
                                    >{{ $option['display'] }}</button>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="d-flex flex-wrap gap-2" role="group" aria-label="Set condition">
                            @foreach ($conditionOptions as $option)
                                <button
                                    type="button"
                                    class="btn btn-lg flex-fill"
                                    :class="currentStatus === @js($option['value']) ? 'btn-{{ $conditionColor($option['value']) }}' : 'btn-outline-{{ $conditionColor($option['value']) }}'"
                                    :aria-pressed="currentStatus === @js($option['value']) ? 'true' : 'false'"
                                    data-condition="{{ $option['value'] }}"
                                    x-on:click="setCondition(@js($option['value']))"
                                >{{ $option['display'] }}</button>
                            @endforeach
                        </div>
                    @endif
                    <div class="small mt-2" aria-live="polite">
                        <span class="text-secondary" x-show="saving" x-cloak>Saving…</span>
                        <span class="text-success" x-show="saved" x-cloak>Saved</span>
                        <span class="text-danger" x-show="saveError" x-cloak>Save failed - tap condition again to retry</span>
                    </div>
                @else
                    <span class="badge bg-secondary-lt">{{ $living_record['status_display'] ?? 'Not checked' }}</span>
                @endif
            </div>
        </section>
    @endif

    @if ($tabletMode)
            </div>
            <div class="col-12 col-lg-5 d-flex flex-column gap-3">
    @endif

    @if (count($slots) > 0)
        <section class="card" aria-labelledby="inspection-measurement-heading">
            <div class="card-body p-3">
                <h3 id="inspection-measurement-heading" class="card-title mb-2">
                    Measurements
                    @if (count($living_record['missing_measurement_slots'] ?? []) > 0)
                        <span class="text-danger">· required</span>
                    @endif
                </h3>
                @if ($canEdit)
                    <div class="d-flex flex-column gap-2">
                        <template x-for="(slot, index) in measurementSlots" :key="slot.key">
                            <div class="row g-2 align-items-center">
                                <label class="col-5 col-sm-4 col-form-label" :for="'inspection-slot-' + slot.key" x-text="slot.name + (slot.required ? ' *' : '')"></label>
                                <div class="col">
                                    <template x-if="slot.type === 'condition'">
                                        <select
                                            @class(['form-select', 'form-select-lg' => $tabletMode])
                                            :id="'inspection-slot-' + slot.key"
                                            x-model="slot.value"
                                            x-on:change="saveSlots()"
                                        >
                                            <option value="">-</option>
                                            <option value="good">Good</option>
                                            <option value="monitor">Monitor</option>
                                            <option value="needs_attention">Needs Attention</option>
                                            <option value="failed">Failed</option>
                                            <option value="na">N/A</option>
                                        </select>
                                    </template>
                                    <template x-if="slot.type !== 'condition'">
                                        <div @class(['input-group', 'input-group-lg' => $tabletMode])>
                                            <input
                                                type="text"
                                                class="form-control"
                                                :id="'inspection-slot-' + slot.key"
                                                :placeholder="slot.name"
                                                x-model="slot.value"
                                                x-on:change.debounce.500ms="saveSlots()"
                                                inputmode="decimal"
                                            >
                                            <span class="input-group-text" x-show="slot.unit" x-text="slot.unit"></span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                    <p class="small text-secondary mt-2 mb-0" x-show="missingSlots.length" x-cloak>
                        Still needed: <span x-text="missingSlots.join(', ')"></span>
                    </p>
                @else
                    <div class="datagrid">
                        @foreach ($slots as $slot)
                            <div class="datagrid-item">
                                <div class="datagrid-title">{{ $slot['name'] }}</div>
                                <div class="datagrid-content">{{ filled($slot['value']) ? $slot['value'].($slot['unit'] ? ' '.$slot['unit'] : '') : '-' }}</div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>
    @elseif ($living_record['measurement_name'] ?? null)
        <section class="card" aria-labelledby="inspection-measurement-heading">
            <div class="card-body p-3">
                <h3 id="inspection-measurement-heading" class="card-title mb-2">Measurement</h3>
                @if ($canEdit)
                    <div @class(['input-group', 'input-group-lg' => $tabletMode])>
                        <input
                            type="text"
                            class="form-control"
                            placeholder="{{ $measurementName }}"
                            aria-label="{{ $measurementName }}"
                            value="{{ $currentMeasurement }}"
                            x-model="measurementValue"
                            x-on:change.debounce.500ms="saveMeasurement()"
                            inputmode="decimal"
                        >
                        @if ($measurementUnit !== '')
                            <span class="input-group-text">{{ $measurementUnit }}</span>
                        @else
                            <input
                                type="text"
                                class="form-control"
                                style="max-width: 6rem"
                                placeholder="Unit"
                                aria-label="Unit"
                                value="{{ $currentUnit }}"
                                x-model="measurementUnit"
                                x-on:change.debounce.500ms="saveMeasurement()"
                            >
                        @endif
                    </div>
                @elseif ($living_record['measurement']['formatted'] ?? null)
                    <div class="fw-medium">{{ $living_record['measurement']['formatted'] }}</div>
                @else
                    <div class="text-secondary">-</div>
                @endif
            </div>
        </section>
    @endif

    <template x-if="brakePrompts.length">
        <section class="alert alert-info mb-0" aria-label="Brake comparison">
            <template x-for="(prompt, index) in brakePrompts" :key="index">
                <div class="mb-2">
                    <div class="fw-bold" x-text="prompt.message"></div>
                    <div class="text-secondary" x-text="prompt.helper"></div>
                </div>
            </template>
        </section>
    </template>

    @if ($living_record['requires_scan_evidence'] ?? false)
        <div class="alert alert-info mb-0">
            Scan evidence preferred - attach a photo/screenshot of the tool. Manual code typing is not required when an attachment is present.
        </div>
    @endif

    @if ($tabletMode)
        @if ($canEdit)
            <section class="card" aria-labelledby="inspection-camera-heading" data-inspection-walk-camera>
                <div class="card-body p-3">
                    <h3 id="inspection-camera-heading" class="card-title mb-2">Photo</h3>
                    <form
                        method="post"
                        action="{{ $photoStoreUrl }}"
                        enctype="multipart/form-data"
                        @class(['mb-3' => count($living_record['photos'] ?? []) > 0])
                        data-inspection-walk-upload
                    >
                        @csrf
                        <input type="hidden" name="surface" value="tablet">
                        <input type="hidden" name="purpose" value="{{ $photo_purposes[0]->value ?? 'internal' }}">
                        <label class="btn btn-primary btn-xl w-100">
                            Take photo
                            <input
                                type="file"
                                name="photo"
                                accept="image/jpeg,image/png,image/webp"
                                capture="environment"
                                class="visually-hidden"
                                onchange="this.form.submit()"
                            >
                        </label>
                    </form>
                    @include('operations.repair-orders.inspection.partials.walk-evidence-tabler', [
                        'photos' => $living_record['photos'] ?? [],
                        'label' => $living_record['label'] ?? 'Inspection photo',
                        'canEdit' => $canEdit,
                        'surface' => 'tablet',
                    ])
                </div>
            </section>
        @endif

        <section class="card" data-inspection-walk-more>
            <div class="accordion accordion-flush">
                <div class="accordion-item">
                    <h3 class="accordion-header" id="inspection-walk-more-heading">
                        <button
                            type="button"
                            @class(['accordion-button py-3', 'collapsed' => ! $priorOpen])
                            data-bs-toggle="collapse"
                            data-bs-target="#inspection-walk-more"
                            aria-controls="inspection-walk-more"
                            aria-expanded="{{ $priorOpen ? 'true' : 'false' }}"
                        >Previous visit &amp; notes</button>
                    </h3>
                    <div
                        id="inspection-walk-more"
                        @class(['accordion-collapse collapse', 'show' => $priorOpen])
                        aria-labelledby="inspection-walk-more-heading"
                    >
                        <div class="accordion-body d-flex flex-column gap-3">
                            <section aria-labelledby="inspection-prior-heading">
                                <h4 id="inspection-prior-heading" class="subheader mb-2">Previous visit</h4>
                                @if ($prior['available'] && count($prior['items']) > 0)
                                    <div class="list-group">
                                        @foreach ($prior['items'] as $visit)
                                            <div class="list-group-item">
                                                <div class="row g-2">
                                                    <div class="col-auto text-secondary">{{ $visit['occurred_label'] }}</div>
                                                    <div class="col text-end">
                                                        {{ $visit['status_display'] }}
                                                        @if ($visit['measurement'])
                                                            · {{ $visit['measurement'] }}
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="text-secondary">{{ $prior['empty_label'] ?? 'No prior history for this inspection point yet.' }}</div>
                                @endif
                            </section>

                            <section aria-labelledby="inspection-note-heading">
                                <h4 id="inspection-note-heading" class="subheader mb-2">
                                    @if ($canEdit)
                                        <label for="inspection-walk-note" class="mb-0">Notes</label>
                                    @else
                                        Notes
                                    @endif
                                </h4>
                                @if ($canEdit)
                                    <textarea
                                        id="inspection-walk-note"
                                        rows="3"
                                        class="form-control form-control-lg"
                                        placeholder="Optional note for this inspection point"
                                        x-model="note"
                                        x-on:change.debounce.500ms="saveNote()"
                                    ></textarea>
                                @elseif (filled($living_record['note'] ?? null))
                                    <div style="white-space: pre-wrap">{{ $living_record['note'] }}</div>
                                @else
                                    <div class="text-secondary">-</div>
                                @endif
                            </section>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    @else
        <section class="card" aria-labelledby="inspection-prior-heading">
            <div class="card-header">
                <h3 id="inspection-prior-heading" class="card-title">Previous visit</h3>
            </div>
            @if ($prior['available'] && count($prior['items']) > 0)
                <div class="list-group list-group-flush">
                    @foreach ($prior['items'] as $visit)
                        <div class="list-group-item">
                            <div class="row g-2">
                                <div class="col-auto text-secondary">{{ $visit['occurred_label'] }}</div>
                                <div class="col text-end">
                                    {{ $visit['status_display'] }}
                                    @if ($visit['measurement'])
                                        · {{ $visit['measurement'] }}
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="card-body text-secondary">{{ $prior['empty_label'] ?? 'No prior history for this inspection point yet.' }}</div>
            @endif
        </section>

        <section class="card" aria-labelledby="inspection-evidence-heading">
            <div class="card-header">
                <h3 id="inspection-evidence-heading" class="card-title">Photos &amp; video</h3>
            </div>
            <div class="card-body p-3">
                @include('operations.repair-orders.inspection.partials.walk-evidence-tabler', [
                    'photos' => $living_record['photos'] ?? [],
                    'label' => $living_record['label'] ?? 'Inspection photo',
                    'canEdit' => $canEdit,
                    'surface' => null,
                ])
                @if ($canEdit)
                    <form
                        method="post"
                        action="{{ $photoStoreUrl }}"
                        enctype="multipart/form-data"
                        class="row g-2"
                        data-inspection-walk-upload
                    >
                        @csrf
                        <div class="col-12 col-sm-4">
                            <select name="purpose" class="form-select" aria-label="Photo purpose">
                                @foreach ($photo_purposes as $purpose)
                                    <option value="{{ $purpose->value }}">{{ $purpose->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-sm">
                            <input
                                type="file"
                                name="photo"
                                accept="image/jpeg,image/png,image/webp,video/mp4,video/webm,video/quicktime"
                                capture="environment"
                                class="form-control"
                                aria-label="Take or choose a photo or video"
                                onchange="this.form.submit()"
                            >
                        </div>
                    </form>
                @elseif (count($living_record['photos'] ?? []) === 0)
                    <div class="text-secondary">-</div>
                @endif
            </div>
        </section>

        <section class="card" aria-labelledby="inspection-note-heading">
            <div class="card-body p-3">
                <h3 id="inspection-note-heading" class="card-title mb-2">
                    @if ($canEdit)
                        <label for="inspection-walk-note" class="mb-0">Notes</label>
                    @else
                        Notes
                    @endif
                </h3>
                @if ($canEdit)
                    <textarea
                        id="inspection-walk-note"
                        rows="2"
                        class="form-control"
                        placeholder="Optional note for this inspection point"
                        x-model="note"
                        x-on:change.debounce.500ms="saveNote()"
                    ></textarea>
                @elseif (filled($living_record['note'] ?? null))
                    <div style="white-space: pre-wrap">{{ $living_record['note'] }}</div>
                @else
                    <div class="text-secondary">-</div>
                @endif
            </div>
        </section>

        @if ($living_record['recommendation_hint'] ?? null)
            <div class="alert alert-info mb-0">
                <h4 class="alert-title">Recommendation hint</h4>
                <div>{{ $living_record['recommendation_hint']['label'] }}</div>
                @if ($living_record['recommendation_hint']['summary'] ?? null)
                    <div class="text-secondary">{{ $living_record['recommendation_hint']['summary'] }}</div>
                @endif
            </div>
        @endif

        @if ($canEdit && in_array($living_record['status'] ?? '', ['fail', 'needs_attention', 'monitor'], true))
            <section class="card">
                <div class="card-body p-3">
                    <h3 class="card-title mb-2">Recommendation</h3>
                    <form method="post" action="{{ route('operations.repair-orders.inspection.items.recommendations.store', [$repairOrder, $living_record['id']]) }}">
                        @csrf
                        <input type="hidden" name="title" value="{{ $living_record['label'] ?? '' }}">
                        @if (($living_record['status'] ?? '') === 'fail')
                            <input type="hidden" name="safety_related" value="1">
                            <input type="hidden" name="due_kind" value="now">
                        @endif
                        <button type="submit" class="btn w-100">Create recommendation</button>
                    </form>
                </div>
            </section>
        @endif
    @endif

    @if ($tabletMode)
            </div>
        </div>

        <nav class="sticky-bottom bg-body border-top py-2" aria-label="Walk navigation" data-inspection-walk-nav>
            <div class="row g-2 align-items-center">
                <div class="col">
                    @if ($nav['prior_url'] ?? null)
                        <a href="{{ $nav['prior_url'] }}" class="btn btn-lg w-100" data-inspection-walk-prior>
                            <x-tabler.icon name="arrow-left" />
                            Prev
                        </a>
                    @else
                        <span class="btn btn-lg w-100 disabled" aria-disabled="true">
                            <x-tabler.icon name="arrow-left" />
                            Prev
                        </span>
                    @endif
                </div>
                <div class="col-auto fw-bold text-secondary">{{ $nav['index'] ?? '-' }}/{{ $nav['total'] ?? '-' }}</div>
                <div class="col">
                    @if ($nav['next_url'] ?? null)
                        <a href="{{ $nav['next_url'] }}" class="btn btn-primary btn-lg w-100" data-inspection-walk-next>
                            Next
                            <x-tabler.icon name="arrow-right" />
                        </a>
                    @else
                        <span class="btn btn-primary btn-lg w-100 disabled" aria-disabled="true">Done</span>
                    @endif
                </div>
            </div>
        </nav>
    @elseif ($canEdit && ($nav['next_url'] ?? null))
        <a href="{{ $nav['next_url'] }}" class="btn btn-primary btn-lg w-100" data-inspection-walk-next>
            Next
            <x-tabler.icon name="arrow-right" />
        </a>
    @endif
</div>
