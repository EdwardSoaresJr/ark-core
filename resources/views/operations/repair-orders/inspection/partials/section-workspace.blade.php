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
@endphp

<div
    class="ops-inspection-sections {{ $tabletMode ? 'ops-inspection-sections--tablet' : '' }}"
    id="inspection-sections"
    data-surface="{{ $tabletMode ? 'tablet' : 'standard' }}"
    x-data="arkInspectionSectionWalk(@js([
        'csrf' => csrf_token(),
        'stages' => $stages,
        'focusSectionKey' => $sectionWalk['focus_section_key'] ?? null,
        'rearAxleBrakeType' => $sectionWalk['rear_axle_brake_type'] ?? null,
        'progress' => $progress,
    ]))"
>
    <header class="ops-inspection-sections__head">
        <div>
            <p class="ops-inspection-sections__eyebrow">
                @if (! empty($template_name ?? null))
                    {{ $template_name }}
                @else
                    Vehicle Inspection
                @endif
            </p>
            <h2 class="ops-inspection-sections__title">Section walk</h2>
            <p class="ops-inspection-sections__progress" x-text="`${progress.addressed} of ${progress.total} · ${progress.remaining} remaining`"></p>
        </div>
    </header>

    <nav class="ops-inspection-sections__toc" aria-label="Inspection coverage">
        <template x-for="stage in stages" :key="stage.key">
            <div class="ops-inspection-sections__toc-stage">
                <p class="ops-inspection-sections__toc-stage-label">
                    <span x-text="stage.label"></span>
                    <span class="ops-inspection-sections__optional" x-show="stage.optional" x-cloak>Optional</span>
                </p>
                <template x-for="section in stage.sections" :key="section.key">
                    <button
                        type="button"
                        class="ops-inspection-sections__toc-row"
                        :class="{
                            'is-complete': section.state === 'complete',
                            'is-progress': section.state === 'in_progress',
                            'is-pending': section.state === 'not_started',
                        }"
                        x-on:click="openSectionKeys[section.key] = true; $nextTick(() => document.getElementById('section-' + section.key)?.scrollIntoView({ behavior: 'smooth', block: 'start' }))"
                    >
                        <span class="ops-inspection-sections__toc-mark" aria-hidden="true"
                            x-text="section.state === 'complete' ? '✓' : (section.state === 'in_progress' ? '●' : '○')"
                        ></span>
                        <span class="ops-inspection-sections__toc-name" x-text="section.label"></span>
                        <span class="ops-inspection-sections__toc-count" x-text="`${section.addressed}/${section.total}`"></span>
                    </button>
                </template>
            </div>
        </template>
        <p class="ops-inspection-sections__toc-remaining" x-show="progress.remaining > 0" x-cloak>
            <span x-text="progress.remaining"></span> points remaining
        </p>
        <p class="ops-inspection-sections__toc-remaining is-done" x-show="progress.remaining === 0 && progress.total > 0" x-cloak>
            All visible points addressed
        </p>
    </nav>

    <template x-for="stage in stages" :key="'body-' + stage.key">
        <section class="ops-inspection-sections__stage" :data-stage="stage.key">
            <header class="ops-inspection-sections__stage-head">
                <h3 class="ops-inspection-sections__stage-title" x-text="stage.label"></h3>
                <p class="ops-inspection-sections__stage-hint" x-show="stage.hint" x-text="stage.hint" x-cloak></p>
            </header>

            <template x-for="section in stage.sections" :key="section.key">
                <section
                    class="ops-inspection-sections__section"
                    :id="'section-' + section.key"
                    :class="{
                        'is-complete': section.state === 'complete',
                        'is-open': isSectionOpen(section.key),
                    }"
                >
                    <button
                        type="button"
                        class="ops-inspection-sections__section-toggle"
                        x-on:click="toggleSection(section.key)"
                        :aria-expanded="isSectionOpen(section.key) ? 'true' : 'false'"
                    >
                        <span class="ops-inspection-sections__section-mark" aria-hidden="true"
                            x-text="section.state === 'complete' ? '✓' : (section.state === 'in_progress' ? '●' : '○')"
                        ></span>
                        <span class="ops-inspection-sections__section-label" x-text="section.label"></span>
                        <span class="ops-inspection-sections__section-meta">
                            <span x-text="section.state_label"></span>
                            ·
                            <span x-text="`${section.addressed}/${section.total}`"></span>
                        </span>
                    </button>

                    <div class="ops-inspection-sections__section-body" x-show="isSectionOpen(section.key)" x-cloak>
                        <template x-for="point in section.points" :key="point.id">
                            <article
                                class="ops-inspection-point"
                                :class="{
                                    'is-addressed': point.addressed,
                                    'is-expanded': point.expanded,
                                    'has-error': point.saveState === 'error',
                                }"
                                :data-point-id="point.id"
                            >
                                <div class="ops-inspection-point__main">
                                    <div class="ops-inspection-point__identity">
                                        <h4 class="ops-inspection-point__label" x-text="point.label"></h4>
                                    </div>

                                    <div class="ops-inspection-point__conditions" role="group" :aria-label="'Condition for ' + point.label">
                                        <template x-if="point.is_axle_gate && {{ $canEdit ? 'true' : 'false' }}">
                                            <div class="ops-inspection-point__axle">
                                                <button type="button" class="ops-inspection-point__condition" :class="{ 'is-active': rearAxleBrakeType === 'disc' }" x-on:click="setRearAxle(point, 'disc')">Disc</button>
                                                <button type="button" class="ops-inspection-point__condition" :class="{ 'is-active': rearAxleBrakeType === 'drum' }" x-on:click="setRearAxle(point, 'drum')">Drum</button>
                                            </div>
                                        </template>

                                        <template x-if="!point.is_axle_gate">
                                            <div class="ops-inspection-point__condition-row">
                                                @if ($canEdit)
                                                    <template x-for="option in point.condition_options" :key="option.value">
                                                        <button
                                                            type="button"
                                                            class="ops-inspection-point__condition"
                                                            :class="{
                                                                'is-active': point.status === option.value,
                                                                ['ops-inspection-point__condition--' + option.value]: true,
                                                            }"
                                                            :disabled="point.road_test_finding_locked || point.saveState === 'saving'"
                                                            x-on:click="setCondition(point, option.value)"
                                                            x-text="option.display"
                                                        ></button>
                                                    </template>
                                                @else
                                                    <span class="ops-inspection-point__readonly" x-text="point.status_label"></span>
                                                @endif
                                            </div>
                                        </template>
                                    </div>

                                    <div
                                        class="ops-inspection-point__slots"
                                        x-show="(point.measurement_slots ?? []).length > 0"
                                        x-cloak
                                    >
                                        <template x-for="slot in point.measurement_slots" :key="slot.key">
                                            <label class="ops-inspection-point__slot">
                                                <span class="ops-inspection-point__slot-name" x-text="slot.name"></span>
                                                <span
                                                    class="ops-inspection-point__slot-input"
                                                    :class="{ 'is-missing': (point.missing_measurement_slots ?? []).includes(slot.name) }"
                                                >
                                                    <input
                                                        type="text"
                                                        inputmode="decimal"
                                                        class="ops-inspection-point__slot-field"
                                                        x-model="slot.value"
                                                        @if ($canEdit)
                                                            x-on:input="scheduleSlots(point)"
                                                            x-on:change="saveSlots(point)"
                                                        @else
                                                            disabled
                                                        @endif
                                                    >
                                                    <span class="ops-inspection-point__slot-unit" x-text="slot.unit ?? ''" x-show="slot.unit"></span>
                                                </span>
                                            </label>
                                        </template>
                                    </div>

                                    <div class="ops-inspection-point__save">
                                        <span class="ops-inspection-point__save-msg" x-show="point.saveState === 'saving'" x-cloak>Saving…</span>
                                        <span class="ops-inspection-point__save-msg is-saved" x-show="point.saveState === 'saved'" x-cloak>Saved</span>
                                        <button
                                            type="button"
                                            class="ops-inspection-point__save-msg is-error"
                                            x-show="point.saveState === 'error'"
                                            x-cloak
                                            x-on:click="retry(point)"
                                        >Save failed · Retry</button>
                                    </div>

                                    {{-- Documentation tools only when Builder expand_when matches (Yellow/Red) --}}
                                    <div
                                        class="ops-inspection-point__tools"
                                        x-show="shouldExpandForStatus(point, point.status)"
                                        x-cloak
                                    >
                                        <button
                                            type="button"
                                            class="ops-inspection-point__expand-btn"
                                            x-on:click="toggleExpand(point)"
                                            x-text="point.expanded ? 'Hide details' : 'Details'"
                                        ></button>
                                    </div>
                                </div>

                                <div
                                    class="ops-inspection-point__detail"
                                    x-show="point.expanded && shouldExpandForStatus(point, point.status)"
                                    x-cloak
                                    x-transition.opacity.duration.100ms
                                >
                                    <p
                                        class="ops-inspection-point__locked"
                                        x-show="point.road_test_finding_locked"
                                        x-cloak
                                    >Mark road test performed before recording findings.</p>

                                    <div
                                        class="ops-inspection-point__observations"
                                        x-show="(point.observation_options ?? []).length > 0"
                                        x-cloak
                                    >
                                        <span class="ops-inspection-point__note-label">Observation</span>
                                        <div class="ops-inspection-point__obs-chips" role="group" :aria-label="'Observations for ' + point.label">
                                            <template x-for="option in point.observation_options" :key="option.key">
                                                <button
                                                    type="button"
                                                    class="ops-inspection-point__obs-chip"
                                                    :class="{ 'is-active': (point.selected_observations ?? []).includes(option.key) }"
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
                                        <label class="ops-inspection-point__note">
                                            <span class="ops-inspection-point__note-label">Technician note</span>
                                            <textarea
                                                class="ops-inspection-point__note-field"
                                                rows="2"
                                                x-model="point.note"
                                                x-on:input="scheduleNote(point)"
                                                x-on:change="saveNote(point)"
                                                placeholder="Observation only — not a recommendation or diagnosis"
                                            ></textarea>
                                        </label>

                                        <form
                                            class="ops-inspection-point__photo"
                                            method="post"
                                            enctype="multipart/form-data"
                                            :action="point.photo_store_url + (point.photo_store_url.includes('?') ? '&' : '?') + 'return=sections&section=' + encodeURIComponent(section.key)"
                                        >
                                            @csrf
                                            <label class="ops-inspection-point__photo-label">
                                                <span>Photo / video</span>
                                                <select name="purpose" class="ops-inspection-point__photo-purpose" required>
                                                    @foreach ($photoPurposes as $purpose)
                                                        <option value="{{ $purpose->value }}" @selected($purpose === \App\Ark\Operations\Inspections\InspectionPhotoPurpose::Customer)>
                                                            {{ $purpose->label() }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <input type="file" name="photo" accept="image/*,video/mp4,video/quicktime,video/webm" required class="ops-inspection-point__photo-file">
                                            </label>
                                            <button type="submit" class="ops-inspection-point__photo-submit">Attach</button>
                                        </form>
                                    @else
                                        <p class="ops-inspection-point__note-readonly" x-show="point.note" x-text="point.note" x-cloak></p>
                                    @endif

                                    <div class="ops-inspection-point__thumbs" x-show="(point.photos ?? []).length > 0" x-cloak>
                                        <template x-for="photo in point.photos" :key="photo.id">
                                            <a :href="photo.url" target="_blank" rel="noopener" class="ops-inspection-point__thumb">
                                                <img x-show="photo.is_image" :src="photo.url" alt="" class="ops-inspection-point__thumb-img">
                                                <span x-show="photo.is_video" x-cloak>Video</span>
                                            </a>
                                        </template>
                                    </div>
                                </div>
                            </article>
                        </template>
                    </div>
                </section>
            </template>
        </section>
    </template>
</div>
