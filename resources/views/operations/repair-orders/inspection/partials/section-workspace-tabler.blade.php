@php
    $tabletMode = (bool) ($tabletMode ?? false);
    $sectionWalk = $section_walk ?? [];
    $stages = $sectionWalk['stages'] ?? [];
    $progress = $sectionWalk['progress'] ?? ['addressed' => 0, 'total' => 0, 'remaining' => 0];
    $canEdit = (bool) ($canEdit ?? false);
    $photoPurposes = $photo_purposes ?? [
        \App\Ark\Operations\Inspections\InspectionPhotoPurpose::Customer,
        \App\Ark\Operations\Inspections\InspectionPhotoPurpose::Internal,
    ];
    $conditionColor = "({ good: 'success', monitor: 'warning', needs_attention: 'danger', failed: 'danger', na: 'secondary' }[option.value] ?? 'primary')";
@endphp

<div
    class="d-flex flex-column gap-3"
    id="inspection-sections"
    data-inspection-sections
    data-surface="{{ $tabletMode ? 'tablet' : 'standard' }}"
    x-data="arkInspectionSectionWalk(@js([
        'csrf' => csrf_token(),
        'stages' => $stages,
        'focusSectionKey' => $sectionWalk['focus_section_key'] ?? null,
        'rearAxleBrakeType' => $sectionWalk['rear_axle_brake_type'] ?? null,
        'progress' => $progress,
    ]))"
>
    <div class="card">
        <div class="card-body p-3">
            <div class="subheader">
                @if (! empty($template_name ?? null))
                    {{ $template_name }}
                @else
                    Vehicle Inspection
                @endif
            </div>
            <h2 class="card-title mb-1">Section walk</h2>
            <div class="text-secondary" x-text="`${progress.addressed} of ${progress.total} · ${progress.remaining} remaining`"></div>
            <div class="progress progress-sm mt-2">
                <div
                    class="progress-bar bg-success"
                    role="progressbar"
                    aria-label="Inspection coverage"
                    :style="{ width: (progress.total > 0 ? Math.round(progress.addressed / progress.total * 100) : 0) + '%' }"
                    :aria-valuenow="progress.addressed"
                    aria-valuemin="0"
                    :aria-valuemax="progress.total"
                ></div>
            </div>
        </div>
    </div>

    <nav class="card" aria-label="Inspection coverage" data-inspection-sections-toc>
        <div class="card-header">
            <h2 class="card-title">Coverage</h2>
            <div class="card-actions d-md-none">
                <button
                    type="button"
                    @class(['btn', 'btn-sm' => ! $tabletMode])
                    data-bs-toggle="collapse"
                    data-bs-target="#inspection-sections-toc-list"
                    aria-controls="inspection-sections-toc-list"
                    aria-expanded="false"
                >Sections</button>
            </div>
        </div>
        <div class="collapse d-md-block" id="inspection-sections-toc-list">
            <template x-for="stage in stages" :key="stage.key">
                <div>
                    <div class="subheader px-3 pt-3 pb-1">
                        <span x-text="stage.label"></span>
                        <span class="badge bg-secondary-lt ms-1" x-show="stage.optional" x-cloak>Optional</span>
                    </div>
                    <div class="list-group list-group-flush">
                        <template x-for="section in stage.sections" :key="section.key">
                            <button
                                type="button"
                                @class(['list-group-item list-group-item-action', 'py-3' => $tabletMode])
                                :class="{
                                    'is-complete': section.state === 'complete',
                                    'is-progress': section.state === 'in_progress',
                                    'is-pending': section.state === 'not_started',
                                }"
                                x-on:click="openSectionKeys[section.key] = true; $nextTick(() => document.getElementById('section-' + section.key)?.scrollIntoView({ behavior: 'smooth', block: 'start' }))"
                            >
                                <span class="row g-2 align-items-center">
                                    <span
                                        class="col-auto fw-bold"
                                        :class="section.state === 'complete' ? 'text-success' : (section.state === 'in_progress' ? 'text-warning' : 'text-secondary')"
                                        aria-hidden="true"
                                        x-text="section.state === 'complete' ? '✓' : (section.state === 'in_progress' ? '●' : '○')"
                                    ></span>
                                    <span class="col text-truncate" x-text="section.label"></span>
                                    <span class="col-auto small text-secondary" x-text="`${section.addressed}/${section.total}`"></span>
                                </span>
                            </button>
                        </template>
                    </div>
                </div>
            </template>
        </div>
        <div class="card-footer small">
            <p class="mb-0 text-secondary" x-show="progress.remaining > 0" x-cloak>
                <span x-text="progress.remaining"></span> points remaining
            </p>
            <p class="mb-0 text-success fw-medium" x-show="progress.remaining === 0 && progress.total > 0" x-cloak>
                All visible points addressed
            </p>
        </div>
    </nav>

    <template x-for="stage in stages" :key="'body-' + stage.key">
        <section :data-stage="stage.key">
            <div class="mb-2">
                <h3 class="h3 mb-0" x-text="stage.label"></h3>
                <p class="text-secondary small mb-0" x-show="stage.hint" x-text="stage.hint" x-cloak></p>
            </div>

            <div class="d-flex flex-column gap-2">
                <template x-for="section in stage.sections" :key="section.key">
                    <section
                        class="card"
                        :id="'section-' + section.key"
                        :class="{
                            'border-success': section.state === 'complete',
                            'is-complete': section.state === 'complete',
                            'is-open': isSectionOpen(section.key),
                        }"
                        data-inspection-section
                    >
                        <button
                            type="button"
                            class="card-header w-100 text-start text-reset bg-transparent border-0 py-3"
                            x-on:click="toggleSection(section.key)"
                            :aria-expanded="isSectionOpen(section.key) ? 'true' : 'false'"
                        >
                            <span class="row g-2 align-items-center w-100">
                                <span
                                    class="col-auto fw-bold"
                                    :class="section.state === 'complete' ? 'text-success' : (section.state === 'in_progress' ? 'text-warning' : 'text-secondary')"
                                    aria-hidden="true"
                                    x-text="section.state === 'complete' ? '✓' : (section.state === 'in_progress' ? '●' : '○')"
                                ></span>
                                <span class="col card-title mb-0" x-text="section.label"></span>
                                <span class="col-auto small text-secondary">
                                    <span x-text="section.state_label"></span>
                                    ·
                                    <span x-text="`${section.addressed}/${section.total}`"></span>
                                </span>
                            </span>
                        </button>

                        <div x-show="isSectionOpen(section.key)" x-cloak>
                            <div class="list-group list-group-flush border-top">
                                <template x-for="point in section.points" :key="point.id">
                                    <article
                                        class="list-group-item p-3"
                                        :class="{
                                            'is-addressed': point.addressed,
                                            'is-expanded': point.expanded,
                                            'has-error': point.saveState === 'error',
                                            'bg-red-lt': point.saveState === 'error',
                                        }"
                                        :data-point-id="point.id"
                                        data-inspection-point
                                    >
                                        <div class="d-flex align-items-center gap-2 mb-2">
                                            <h4 class="mb-0 flex-fill" x-text="point.label"></h4>
                                            <span class="text-success" x-show="point.addressed" x-cloak>
                                                <x-tabler.icon name="circle-check" />
                                                <span class="visually-hidden">Addressed</span>
                                            </span>
                                        </div>

                                        <div role="group" :aria-label="'Condition for ' + point.label">
                                            <template x-if="point.is_axle_gate && {{ $canEdit ? 'true' : 'false' }}">
                                                <div class="d-flex gap-2">
                                                    <button type="button" class="btn btn-lg flex-fill" :class="rearAxleBrakeType === 'disc' ? 'btn-primary' : 'btn-outline-primary'" :aria-pressed="rearAxleBrakeType === 'disc' ? 'true' : 'false'" x-on:click="setRearAxle(point, 'disc')">Disc</button>
                                                    <button type="button" class="btn btn-lg flex-fill" :class="rearAxleBrakeType === 'drum' ? 'btn-primary' : 'btn-outline-primary'" :aria-pressed="rearAxleBrakeType === 'drum' ? 'true' : 'false'" x-on:click="setRearAxle(point, 'drum')">Drum</button>
                                                </div>
                                            </template>

                                            <template x-if="!point.is_axle_gate">
                                                <div class="d-flex flex-wrap gap-2">
                                                    @if ($canEdit)
                                                        <template x-for="option in point.condition_options" :key="option.value">
                                                            <button
                                                                type="button"
                                                                class="btn btn-lg flex-fill"
                                                                :class="point.status === option.value ? 'btn-' + {{ $conditionColor }} : 'btn-outline-' + {{ $conditionColor }}"
                                                                :aria-pressed="point.status === option.value ? 'true' : 'false'"
                                                                :data-condition="option.value"
                                                                :disabled="point.road_test_finding_locked || point.saveState === 'saving'"
                                                                x-on:click="setCondition(point, option.value)"
                                                                x-text="option.display"
                                                            ></button>
                                                        </template>
                                                    @else
                                                        <span class="badge bg-secondary-lt" x-text="point.status_label"></span>
                                                    @endif
                                                </div>
                                            </template>
                                        </div>

                                        <div x-show="(point.measurement_slots ?? []).length > 0" x-cloak>
                                            <div class="row g-2 mt-1">
                                                <template x-for="slot in point.measurement_slots" :key="slot.key">
                                                    <label class="col-6 col-sm-4">
                                                        <span class="form-label mb-1" x-text="slot.name"></span>
                                                        <span @class(['input-group', 'input-group-lg' => $tabletMode])>
                                                            <input
                                                                type="text"
                                                                inputmode="decimal"
                                                                class="form-control"
                                                                :class="{ 'is-invalid': (point.missing_measurement_slots ?? []).includes(slot.name) }"
                                                                x-model="slot.value"
                                                                @if ($canEdit)
                                                                    x-on:input="scheduleSlots(point)"
                                                                    x-on:change="saveSlots(point)"
                                                                @else
                                                                    disabled
                                                                @endif
                                                            >
                                                            <span class="input-group-text" x-text="slot.unit ?? ''" x-show="slot.unit"></span>
                                                        </span>
                                                    </label>
                                                </template>
                                            </div>
                                        </div>

                                        <div class="small mt-2" aria-live="polite">
                                            <span class="text-secondary" x-show="point.saveState === 'saving'" x-cloak>Saving…</span>
                                            <span class="text-success" x-show="point.saveState === 'saved'" x-cloak>Saved</span>
                                            <button
                                                type="button"
                                                @class(['btn btn-outline-danger', 'btn-sm' => ! $tabletMode])
                                                x-show="point.saveState === 'error'"
                                                x-cloak
                                                x-on:click="retry(point)"
                                            >Save failed · Retry</button>
                                        </div>

                                        {{-- Documentation tools only when Builder expand_when matches (Yellow/Red) --}}
                                        <div class="mt-2" x-show="shouldExpandForStatus(point, point.status)" x-cloak>
                                            <button
                                                type="button"
                                                @class(['btn', 'btn-sm' => ! $tabletMode])
                                                :aria-expanded="point.expanded ? 'true' : 'false'"
                                                x-on:click="toggleExpand(point)"
                                                x-text="point.expanded ? 'Hide details' : 'Details'"
                                            ></button>
                                        </div>

                                        <div
                                            x-show="point.expanded && shouldExpandForStatus(point, point.status)"
                                            x-cloak
                                            x-transition.opacity.duration.100ms
                                        >
                                            <div class="d-flex flex-column gap-3 mt-3">
                                                <div class="alert alert-warning mb-0" x-show="point.road_test_finding_locked" x-cloak>Mark road test performed before recording findings.</div>

                                                <div x-show="(point.observation_options ?? []).length > 0" x-cloak>
                                                    <div class="form-label">Observation</div>
                                                    <div class="btn-list" role="group" :aria-label="'Observations for ' + point.label">
                                                        <template x-for="option in point.observation_options" :key="option.key">
                                                            <button
                                                                type="button"
                                                                class="btn"
                                                                :class="(point.selected_observations ?? []).includes(option.key) ? 'btn-primary' : 'btn-outline-secondary'"
                                                                :aria-pressed="(point.selected_observations ?? []).includes(option.key) ? 'true' : 'false'"
                                                                @if ($canEdit)
                                                                    x-on:click="toggleObservation(point, option.key)"
                                                                @else
                                                                    disabled
                                                                @endif
                                                                x-text="option.label"
                                                            ></button>
                                                        </template>
                                                    </div>
                                                </div>

                                                @if ($canEdit)
                                                    <label class="d-block">
                                                        <span class="form-label">Technician note</span>
                                                        <textarea
                                                            class="form-control"
                                                            rows="2"
                                                            x-model="point.note"
                                                            x-on:input="scheduleNote(point)"
                                                            x-on:change="saveNote(point)"
                                                            placeholder="Observation only - not a recommendation or diagnosis"
                                                        ></textarea>
                                                    </label>

                                                    <form
                                                        method="post"
                                                        enctype="multipart/form-data"
                                                        :action="point.photo_store_url + (point.photo_store_url.includes('?') ? '&' : '?') + 'return=sections&section=' + encodeURIComponent(section.key)"
                                                        data-inspection-point-photo
                                                    >
                                                        @csrf
                                                        <div class="form-label">Photo / video</div>
                                                        <div class="row g-2">
                                                            <div class="col-12 col-sm-4">
                                                                <select name="purpose" @class(['form-select', 'form-select-lg' => $tabletMode]) aria-label="Photo purpose" required>
                                                                    @foreach ($photoPurposes as $purpose)
                                                                        <option value="{{ $purpose->value }}" @selected($purpose === \App\Ark\Operations\Inspections\InspectionPhotoPurpose::Customer)>
                                                                            {{ $purpose->label() }}
                                                                        </option>
                                                                    @endforeach
                                                                </select>
                                                            </div>
                                                            <div class="col-12 col-sm">
                                                                <input type="file" name="photo" accept="image/*,video/mp4,video/quicktime,video/webm" required @class(['form-control', 'form-control-lg' => $tabletMode]) aria-label="Photo or video file">
                                                            </div>
                                                            <div class="col-12 col-sm-auto">
                                                                <button type="submit" @class(['btn w-100', 'btn-lg' => $tabletMode])>Attach</button>
                                                            </div>
                                                        </div>
                                                    </form>
                                                @else
                                                    <p class="mb-0" style="white-space: pre-wrap" x-show="point.note" x-text="point.note" x-cloak></p>
                                                @endif

                                                <div x-show="(point.photos ?? []).length > 0" x-cloak>
                                                    <div class="btn-list">
                                                        <template x-for="photo in point.photos" :key="photo.id">
                                                            <a :href="photo.url" target="_blank" rel="noopener" class="avatar avatar-xl rounded" aria-label="Open photo or video">
                                                                <img x-show="photo.is_image" :src="photo.url" alt="" class="avatar avatar-xl rounded object-fit-cover">
                                                                <span class="small" x-show="photo.is_video" x-cloak>Video</span>
                                                            </a>
                                                        </template>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </article>
                                </template>
                            </div>
                        </div>
                    </section>
                </template>
            </div>
        </section>
    </template>
</div>
