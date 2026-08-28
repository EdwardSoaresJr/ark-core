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
    $conditionOptions = $condition_options;
    if ($living_record['road_test_force_na'] ?? false) {
        $conditionOptions = array_values(array_filter(
            $condition_options,
            fn ($option) => ($option['value'] ?? '') === 'na',
        ));
    }
@endphp

<div
    class="ops-inspection-walk {{ $tabletMode ? 'ops-inspection-walk--tablet' : '' }}"
    id="inspection-walk"
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
    <header class="ops-inspection-walk__head">
        <div>
            <p class="ops-inspection-walk__progress">
                @if (! empty($template_name ?? null))
                    {{ $template_name }} ·
                @endif
                Point {{ $nav['index'] ?? '—' }} of {{ $nav['total'] ?? '—' }}
                · {{ $checked }}/{{ $total }} checked
            </p>
            <h2 class="ops-inspection-walk__title">{{ $living_record['label'] }}</h2>
            @if ($living_record['category_name'] ?? null)
                <p class="ops-inspection-walk__category">{{ $living_record['category_name'] }}</p>
            @endif
        </div>
        @if (! $tabletMode && $canEdit && ($nav['next_url'] ?? null))
            <a href="{{ $nav['next_url'] }}" class="ops-inspection-walk__next">Next →</a>
        @endif
    </header>

    @if ($tabletMode)
        <div class="ops-inspection-walk__meter" aria-hidden="true">
            <span class="ops-inspection-walk__meter-fill" style="width: {{ $pct }}%"></span>
        </div>
    @else
        <nav class="ops-inspection-walk__rail" aria-label="Inspection walk order">
            @foreach ($walk_points as $point)
                <a
                    href="{{ $point['url'] }}"
                    class="ops-inspection-walk__rail-chip {{ $point['is_current'] ? 'is-current' : '' }} {{ $point['checked'] ? 'is-checked' : '' }}"
                    title="{{ $point['label'] }}"
                >
                    <span class="sr-only">{{ $point['label'] }}</span>
                </a>
            @endforeach
        </nav>
    @endif

    @if ($isAxleGate && $canEdit)
        <section class="ops-inspection-walk__section" aria-labelledby="inspection-axle-heading">
            <h3 id="inspection-axle-heading" class="ops-inspection-walk__section-label">Rear axle brake type</h3>
            <p class="ops-inspection-walk__hint-copy mb-2">One choice for the rear axle — Disc or Drum.</p>
            <div class="ops-inspection-walk__conditions" role="group" aria-label="Rear axle type">
                <button type="button" class="ops-inspection-walk__condition" :class="{ 'is-active': rearAxleBrakeType === 'disc' }" x-on:click="setRearAxle('disc')">Disc</button>
                <button type="button" class="ops-inspection-walk__condition" :class="{ 'is-active': rearAxleBrakeType === 'drum' }" x-on:click="setRearAxle('drum')">Drum</button>
            </div>
            <p class="ops-inspection-walk__autosave" x-show="saving" x-cloak>Saving…</p>
        </section>
    @else
        <section class="ops-inspection-walk__section ops-inspection-walk__section--condition" aria-labelledby="inspection-condition-heading">
            <h3 id="inspection-condition-heading" class="ops-inspection-walk__section-label">
                @if (($living_record['gate_group'] ?? null) === 'road_test_performed')
                    Road test performed
                @else
                    Condition
                @endif
            </h3>
            @if (($living_record['road_test_finding_locked'] ?? false))
                <p class="ops-inspection-walk__hint-copy">Mark road test performed before recording findings.</p>
            @elseif ($canEdit)
                <div class="ops-inspection-walk__conditions" role="group" aria-label="Set condition">
                    @foreach ($conditionOptions as $option)
                        <button
                            type="button"
                            class="ops-inspection-walk__condition ops-inspection-walk__condition--{{ $option['value'] }}"
                            :class="{ 'is-active': currentStatus === @js($option['value']) }"
                            x-on:click="setCondition(@js($option['value']))"
                        >{{ $option['display'] }}</button>
                    @endforeach
                </div>
                <p class="ops-inspection-walk__autosave" x-show="saving" x-cloak>Saving…</p>
                <p class="ops-inspection-walk__autosave ops-inspection-walk__autosave--saved" x-show="saved" x-cloak>Saved</p>
                <p class="ops-inspection-walk__autosave ops-inspection-walk__autosave--error" x-show="saveError" x-cloak>Save failed — tap condition again to retry</p>
            @else
                <p class="ops-inspection-walk__readonly">{{ $living_record['status_display'] ?? 'Not checked' }}</p>
            @endif
        </section>
    @endif

    @if (count($slots) > 0)
        <section class="ops-inspection-walk__section" aria-labelledby="inspection-measurement-heading">
            <h3 id="inspection-measurement-heading" class="ops-inspection-walk__section-label">
                Measurements
                @if (count($living_record['missing_measurement_slots'] ?? []) > 0)
                    <span class="text-rose-700">· required</span>
                @endif
            </h3>
            @if ($canEdit)
                <div class="space-y-2">
                    <template x-for="(slot, index) in measurementSlots" :key="slot.key">
                        <div class="ops-inspection-walk__measure-row">
                            <label class="ops-inspection-walk__unit" x-text="slot.name + (slot.required ? ' *' : '')"></label>
                            <template x-if="slot.type === 'condition'">
                                <select
                                    class="ops-inspection-field__input"
                                    x-model="slot.value"
                                    x-on:change="saveSlots()"
                                >
                                    <option value="">—</option>
                                    <option value="good">Good</option>
                                    <option value="monitor">Monitor</option>
                                    <option value="needs_attention">Needs Attention</option>
                                    <option value="failed">Failed</option>
                                    <option value="na">N/A</option>
                                </select>
                            </template>
                            <template x-if="slot.type !== 'condition'">
                                <div class="ops-inspection-walk__measure-row">
                                    <input
                                        type="text"
                                        class="ops-inspection-field__input"
                                        :placeholder="slot.name"
                                        x-model="slot.value"
                                        x-on:change.debounce.500ms="saveSlots()"
                                        inputmode="decimal"
                                    >
                                    <span class="ops-inspection-walk__unit" x-show="slot.unit" x-text="slot.unit"></span>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
                <p class="ops-inspection-walk__hint-copy mt-2" x-show="missingSlots.length" x-cloak>
                    Still needed: <span x-text="missingSlots.join(', ')"></span>
                </p>
            @else
                <ul class="ops-inspection-walk__prior-list">
                    @foreach ($slots as $slot)
                        <li class="ops-inspection-walk__prior-row">
                            <span>{{ $slot['name'] }}</span>
                            <span>{{ filled($slot['value']) ? $slot['value'].($slot['unit'] ? ' '.$slot['unit'] : '') : '—' }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @elseif ($living_record['measurement_name'] ?? null)
        <section class="ops-inspection-walk__section" aria-labelledby="inspection-measurement-heading">
            <h3 id="inspection-measurement-heading" class="ops-inspection-walk__section-label">Measurement</h3>
            @if ($canEdit)
                <div class="ops-inspection-walk__measure-row">
                    <input
                        type="text"
                        class="ops-inspection-field__input"
                        placeholder="{{ $measurementName }}"
                        value="{{ $currentMeasurement }}"
                        x-model="measurementValue"
                        x-on:change.debounce.500ms="saveMeasurement()"
                        inputmode="decimal"
                    >
                    @if ($measurementUnit !== '')
                        <span class="ops-inspection-walk__unit">{{ $measurementUnit }}</span>
                    @else
                        <input
                            type="text"
                            class="ops-inspection-field__input ops-inspection-walk__unit-input"
                            placeholder="Unit"
                            value="{{ $currentUnit }}"
                            x-model="measurementUnit"
                            x-on:change.debounce.500ms="saveMeasurement()"
                        >
                    @endif
                </div>
            @elseif ($living_record['measurement']['formatted'] ?? null)
                <p class="ops-inspection-walk__readonly">{{ $living_record['measurement']['formatted'] }}</p>
            @else
                <p class="ops-inspection-walk__empty">—</p>
            @endif
        </section>
    @endif

    <template x-if="brakePrompts.length">
        <section class="ops-inspection-walk__section ops-inspection-walk__section--hint" aria-label="Brake comparison">
            <template x-for="(prompt, index) in brakePrompts" :key="index">
                <div class="mb-2">
                    <p class="ops-inspection-walk__hint" x-text="prompt.message"></p>
                    <p class="ops-inspection-walk__hint-copy" x-text="prompt.helper"></p>
                </div>
            </template>
        </section>
    </template>

    @if ($living_record['requires_scan_evidence'] ?? false)
        <section class="ops-inspection-walk__section">
            <p class="ops-inspection-walk__hint-copy">
                Scan evidence preferred — attach a photo/screenshot of the tool. Manual code typing is not required when an attachment is present.
            </p>
        </section>
    @endif

    @if ($tabletMode && $canEdit)
        <section class="ops-inspection-walk__section ops-inspection-walk__section--camera" aria-labelledby="inspection-camera-heading">
            <h3 id="inspection-camera-heading" class="ops-inspection-walk__section-label">Photo</h3>
            <form
                method="post"
                action="{{ $photoStoreUrl }}"
                enctype="multipart/form-data"
                class="ops-inspection-walk__camera"
            >
                @csrf
                <input type="hidden" name="surface" value="tablet">
                <input type="hidden" name="purpose" value="{{ $photo_purposes[0]->value ?? 'internal' }}">
                <label class="ops-inspection-walk__camera-btn">
                    <span>Take photo</span>
                    <input
                        type="file"
                        name="photo"
                        accept="image/jpeg,image/png,image/webp"
                        capture="environment"
                        class="sr-only"
                        onchange="this.form.submit()"
                    >
                </label>
            </form>
            @include('operations.repair-orders.inspection.partials.walk-evidence', [
                'photos' => $living_record['photos'] ?? [],
                'label' => $living_record['label'] ?? 'Inspection photo',
                'canEdit' => $canEdit,
                'surface' => $tabletMode ? 'tablet' : null,
            ])
        </section>
    @endif

    @if ($tabletMode)
        <details class="ops-inspection-walk__more" @if (($prior['available'] ?? false) && count($prior['items'] ?? []) > 0) open @endif>
            <summary>Previous visit &amp; notes</summary>
            <div class="ops-inspection-walk__more-body">
                <section class="ops-inspection-walk__section" aria-labelledby="inspection-prior-heading">
                    <h3 id="inspection-prior-heading" class="ops-inspection-walk__section-label">Previous visit</h3>
                    @if ($prior['available'] && count($prior['items']) > 0)
                        <ul class="ops-inspection-walk__prior-list">
                            @foreach ($prior['items'] as $visit)
                                <li class="ops-inspection-walk__prior-row">
                                    <span class="ops-inspection-walk__prior-when">{{ $visit['occurred_label'] }}</span>
                                    <span class="ops-inspection-walk__prior-value">
                                        {{ $visit['status_display'] }}
                                        @if ($visit['measurement'])
                                            · {{ $visit['measurement'] }}
                                        @endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="ops-inspection-walk__empty">{{ $prior['empty_label'] ?? 'No prior history for this inspection point yet.' }}</p>
                    @endif
                </section>

                <section class="ops-inspection-walk__section" aria-labelledby="inspection-note-heading">
                    <h3 id="inspection-note-heading" class="ops-inspection-walk__section-label">Notes</h3>
                    @if ($canEdit)
                        <textarea
                            rows="3"
                            class="ops-inspection-field__input w-full"
                            placeholder="Optional note for this inspection point"
                            x-model="note"
                            x-on:change.debounce.500ms="saveNote()"
                        ></textarea>
                    @elseif (filled($living_record['note'] ?? null))
                        <p class="ops-inspection-walk__readonly">{{ $living_record['note'] }}</p>
                    @else
                        <p class="ops-inspection-walk__empty">—</p>
                    @endif
                </section>
            </div>
        </details>
    @else
        <section class="ops-inspection-walk__section" aria-labelledby="inspection-prior-heading">
            <h3 id="inspection-prior-heading" class="ops-inspection-walk__section-label">Previous visit</h3>
            @if ($prior['available'] && count($prior['items']) > 0)
                <ul class="ops-inspection-walk__prior-list">
                    @foreach ($prior['items'] as $visit)
                        <li class="ops-inspection-walk__prior-row">
                            <span class="ops-inspection-walk__prior-when">{{ $visit['occurred_label'] }}</span>
                            <span class="ops-inspection-walk__prior-value">
                                {{ $visit['status_display'] }}
                                @if ($visit['measurement'])
                                    · {{ $visit['measurement'] }}
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="ops-inspection-walk__empty">{{ $prior['empty_label'] ?? 'No prior history for this inspection point yet.' }}</p>
            @endif
        </section>

        <section class="ops-inspection-walk__section" aria-labelledby="inspection-evidence-heading">
            <h3 id="inspection-evidence-heading" class="ops-inspection-walk__section-label">Photos &amp; video</h3>
            @include('operations.repair-orders.inspection.partials.walk-evidence', [
                'photos' => $living_record['photos'] ?? [],
                'label' => $living_record['label'] ?? 'Inspection photo',
                'canEdit' => $canEdit,
                'surface' => $tabletMode ? 'tablet' : null,
            ])
            @if ($canEdit)
                <form
                    method="post"
                    action="{{ $photoStoreUrl }}"
                    enctype="multipart/form-data"
                    class="ops-inspection-walk__upload"
                >
                    @csrf
                    <select name="purpose" class="ops-inspection-field__input">
                        @foreach ($photo_purposes as $purpose)
                            <option value="{{ $purpose->value }}">{{ $purpose->label() }}</option>
                        @endforeach
                    </select>
                    <input
                        type="file"
                        name="photo"
                        accept="image/jpeg,image/png,image/webp,video/mp4,video/webm,video/quicktime"
                        capture="environment"
                        class="ops-inspection-field__input"
                        onchange="this.form.submit()"
                    >
                </form>
            @elseif (count($living_record['photos'] ?? []) === 0)
                <p class="ops-inspection-walk__empty">—</p>
            @endif
        </section>

        <section class="ops-inspection-walk__section" aria-labelledby="inspection-note-heading">
            <h3 id="inspection-note-heading" class="ops-inspection-walk__section-label">Notes</h3>
            @if ($canEdit)
                <textarea
                    rows="2"
                    class="ops-inspection-field__input w-full"
                    placeholder="Optional note for this inspection point"
                    x-model="note"
                    x-on:change.debounce.500ms="saveNote()"
                ></textarea>
            @elseif (filled($living_record['note'] ?? null))
                <p class="ops-inspection-walk__readonly">{{ $living_record['note'] }}</p>
            @else
                <p class="ops-inspection-walk__empty">—</p>
            @endif
        </section>
    @endif

    @if (! $tabletMode && ($living_record['recommendation_hint'] ?? null))
        <section class="ops-inspection-walk__section ops-inspection-walk__section--hint">
            <h3 class="ops-inspection-walk__section-label">Recommendation hint</h3>
            <p class="ops-inspection-walk__hint">{{ $living_record['recommendation_hint']['label'] }}</p>
            @if ($living_record['recommendation_hint']['summary'] ?? null)
                <p class="ops-inspection-walk__hint-copy">{{ $living_record['recommendation_hint']['summary'] }}</p>
            @endif
        </section>
    @endif

    @if ($tabletMode)
        <nav class="ops-inspection-walk__sticky-nav" aria-label="Walk navigation">
            @if ($nav['prior_url'] ?? null)
                <a href="{{ $nav['prior_url'] }}" class="ops-inspection-walk__nav-btn">← Prev</a>
            @else
                <span class="ops-inspection-walk__nav-btn is-disabled" aria-disabled="true">← Prev</span>
            @endif
            <span class="ops-inspection-walk__nav-mid">{{ $nav['index'] ?? '—' }}/{{ $nav['total'] ?? '—' }}</span>
            @if ($nav['next_url'] ?? null)
                <a href="{{ $nav['next_url'] }}" class="ops-inspection-walk__nav-btn ops-inspection-walk__nav-btn--next">Next →</a>
            @else
                <span class="ops-inspection-walk__nav-btn ops-inspection-walk__nav-btn--next is-disabled" aria-disabled="true">Done</span>
            @endif
        </nav>
    @elseif ($canEdit && ($nav['next_url'] ?? null))
        <footer class="ops-inspection-walk__foot">
            <a href="{{ $nav['next_url'] }}" class="ops-inspection-walk__next ops-inspection-walk__next--primary">Next →</a>
        </footer>
    @endif
</div>
