<div class="ops-inspection-detail">
    @if ($canEdit)
        <form method="post" action="{{ route('operations.repair-orders.inspection.items.update', [$repairOrder, $item]) }}" class="ops-inspection-detail__form">
            @csrf
            @method('patch')
            <label class="ops-inspection-field">
                <span class="ops-inspection-field__label">Observed</span>
                <select name="observed_state" class="ops-inspection-field__input">
                    @foreach ($observed_states as $state)
                        <option value="{{ $state->value }}" @selected($item->observed_state === $state)>{{ $state->label() }}</option>
                    @endforeach
                </select>
            </label>
            <label class="ops-inspection-field">
                <span class="ops-inspection-field__label">Scope link</span>
                <select name="repair_order_concern_id" class="ops-inspection-field__input">
                    <option value="">None</option>
                    @foreach ($concerns as $concern)
                        <option value="{{ $concern->id }}" @selected($item->repair_order_concern_id === $concern->id)>{{ $concern->summary }}</option>
                    @endforeach
                </select>
            </label>
            <label class="ops-inspection-field">
                <span class="ops-inspection-field__label">Notes</span>
                <textarea name="notes" rows="2" class="ops-inspection-field__input">{{ old('notes', $item->notes) }}</textarea>
            </label>
            <button type="submit" class="ops-inspection-btn ops-inspection-btn--secondary">Update finding</button>
        </form>
    @elseif (filled($item->notes))
        <p class="ops-inspection-detail__readonly">{{ $item->notes }}</p>
    @endif

    <div class="ops-inspection-detail__section">
        <p class="ops-inspection-detail__section-label">Measurements</p>
        @if ($item->measurements->isNotEmpty())
            <ul class="ops-inspection-detail__list">
                @foreach ($item->measurements as $measurement)
                    <li class="ops-inspection-detail__list-row">
                        <span>{{ $measurement->name }}: <strong>{{ $measurement->formattedValue() }}</strong></span>
                        @if ($canEdit)
                            <form method="post" action="{{ route('operations.repair-orders.inspection.measurements.destroy', [$repairOrder, $measurement]) }}">
                                @csrf
                                @method('delete')
                                <button type="submit" class="ops-inspection-link-btn ops-inspection-link-btn--danger">Remove</button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        @else
            <p class="ops-inspection-detail__empty">No measurements.</p>
        @endif

        @if ($canEdit)
            <form method="post" action="{{ route('operations.repair-orders.inspection.measurements.store', [$repairOrder, $item]) }}" class="ops-inspection-detail__inline-form">
                @csrf
                <input type="text" name="name" placeholder="Name" class="ops-inspection-field__input" required maxlength="120">
                <input type="text" name="value" placeholder="Value" class="ops-inspection-field__input" required maxlength="64">
                <input type="text" name="unit" placeholder="Unit" class="ops-inspection-field__input" maxlength="32">
                <button type="submit" class="ops-inspection-btn ops-inspection-btn--secondary">Add</button>
            </form>
        @endif
    </div>

    <div class="ops-inspection-detail__section">
        <p class="ops-inspection-detail__section-label">Photos &amp; video</p>
        @if ($item->photos->isNotEmpty())
            @php
                $canContributeEvidence = auth()->user()?->can(\App\Ark\Runtime\Authorization\ArkCapability::RepairOrdersManage->value)
                    || auth()->user()?->can(\App\Ark\Runtime\Authorization\ArkCapability::SettingsManage->value);
                $contributePages = $canContributeEvidence
                    ? collect(\App\Ark\Operations\Leads\Public\CommonProblemRegistry::all())
                        ->map(fn (array $problem): array => [
                            'slug' => (string) $problem['slug'],
                            'title' => (string) $problem['title'],
                        ])
                        ->sortBy('title')
                        ->values()
                    : collect();
            @endphp
            <div class="ops-inspection-detail__photos">
                @foreach ($item->photos as $photo)
                    <div class="ops-inspection-detail__photo">
                        @php($evidenceUrl = route('operations.repair-orders.inspection.photos.show', [$repairOrder, $photo]))
                        <div class="ops-inspection-detail__photo-tile">
                            @if ($photo->isVideo())
                                <video
                                    src="{{ $evidenceUrl }}"
                                    controls
                                    playsinline
                                    preload="metadata"
                                    class="ops-inspection-detail__video"
                                ></video>
                            @else
                                <button
                                    type="button"
                                    class="ops-inspection-detail__photo-trigger"
                                    data-ops-lightbox="{{ $evidenceUrl }}"
                                    data-ops-lightbox-alt="{{ $item->label }} — {{ $photo->purposeLabel() }}"
                                    aria-label="View {{ $photo->purposeLabel() }} photo"
                                >
                                    <img src="{{ $evidenceUrl }}" alt="{{ $photo->purposeLabel() }}" class="ops-inspection-detail__photo-img">
                                </button>
                            @endif
                            @if ($canEdit)
                                <form
                                    method="post"
                                    action="{{ route('operations.repair-orders.inspection.photos.destroy', [$repairOrder, $photo]) }}"
                                    class="ops-inspection-detail__photo-delete"
                                    onsubmit="return confirm('Remove this photo?');"
                                >
                                    @csrf
                                    @method('delete')
                                    <button
                                        type="submit"
                                        class="ops-inspection-detail__photo-delete-btn"
                                        aria-label="Remove photo"
                                        title="Remove photo"
                                    >×</button>
                                </form>
                            @endif
                        </div>
                        <p class="ops-inspection-detail__photo-label">{{ $photo->purposeLabel() }}</p>
                        @if ($canContributeEvidence && $photo->isImage())
                            <details class="ops-contribute-evidence">
                                <summary class="ops-inspection-link-btn">Use on Website</summary>
                                <form
                                    method="post"
                                    action="{{ route('operations.repair-orders.inspection.photos.contribute', [$repairOrder, $photo]) }}"
                                    class="ops-contribute-evidence__form"
                                >
                                    @csrf
                                    <p class="ops-contribute-evidence__title">Publish Evidence</p>
                                    <p class="ops-contribute-evidence__photo">Photo: ✔</p>
                                    <label class="ops-inspection-field">
                                        <span class="ops-inspection-field__label">Page</span>
                                        <select name="common_problem_slug" required class="ops-inspection-field__input">
                                            <option value="">Select page…</option>
                                            @foreach ($contributePages as $page)
                                                <option value="{{ $page['slug'] }}">{{ $page['title'] }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="ops-inspection-field">
                                        <span class="ops-inspection-field__label">Caption</span>
                                        <input
                                            type="text"
                                            name="caption"
                                            value="{{ $photo->purposeLabel() }}"
                                            maxlength="500"
                                            class="ops-inspection-field__input"
                                        >
                                    </label>
                                    <div class="ops-contribute-evidence__actions">
                                        <button type="submit" class="ops-inspection-btn ops-inspection-btn--secondary">Contribute</button>
                                    </div>
                                </form>
                            </details>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <p class="ops-inspection-detail__empty">No photos or video yet.</p>
        @endif

        @if ($canEdit)
            <form method="post" action="{{ route('operations.repair-orders.inspection.photos.store', [$repairOrder, $item]) }}" enctype="multipart/form-data" class="ops-inspection-detail__inline-form">
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
                    required
                >
                <button type="submit" class="ops-inspection-btn ops-inspection-btn--secondary">Upload</button>
            </form>
        @endif
    </div>

    @if ($canEdit && in_array($item->observed_state?->value, ['fail', 'needs_attention', 'monitor'], true))
        <form method="post" action="{{ route('operations.repair-orders.inspection.items.recommendations.store', [$repairOrder, $item]) }}" class="ops-inspection-detail__section">
            @csrf
            <p class="ops-inspection-detail__section-label">Recommendation</p>
            <input type="hidden" name="title" value="{{ $item->label }}">
            @if ($item->observed_state?->value === 'fail')
                <input type="hidden" name="safety_related" value="1">
                <input type="hidden" name="due_kind" value="now">
            @endif
            <button type="submit" class="ops-inspection-btn ops-inspection-btn--secondary">Create recommendation</button>
        </form>
    @endif
</div>
