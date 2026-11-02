@php
    use App\Ark\Operations\Communications\CommunicationsAccentColor;
    use App\Ark\Operations\Communications\CommunicationsQuickReplyTemplates;

    $submitted = old('quick_reply_templates');
    $source = is_array($submitted) ? $submitted : CommunicationsQuickReplyTemplates::all();
    $editorRows = collect($source)
        ->filter(fn ($row): bool => is_array($row))
        ->values()
        ->map(fn (array $row): array => [
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'label' => (string) ($row['label'] ?? ''),
            'body' => (string) ($row['body'] ?? ''),
            'color' => CommunicationsAccentColor::normalize($row['color'] ?? null),
        ])
        ->all();
    $accentOptions = CommunicationsAccentColor::options();
@endphp

<div
    class="d-flex flex-column gap-3"
    x-data="{
        rows: @js($editorRows),
        add() {
            this.rows.push({ id: crypto.randomUUID(), label: '', body: '', color: 'neutral' });
        },
        remove(id) {
            this.rows = this.rows.filter((row) => row.id !== id);
        },
    }"
>
    <input type="hidden" name="quick_reply_templates_present" value="1">

    <div>
        <div class="subheader">Canned responses</div>
        <p class="text-secondary small mb-0">
            Each block is one reply. The name is the chip. Color is how that chip looks in the composer.
        </p>
    </div>

    @php
        $quickReplyError = collect($errors->getMessages())
            ->filter(fn (mixed $messages, string $key): bool => str_starts_with($key, 'quick_reply_templates'))
            ->flatten()
            ->first();
    @endphp
    @if (is_string($quickReplyError) && $quickReplyError !== '')
        <p class="alert alert-danger small mb-0">{{ $quickReplyError }}</p>
    @endif

    <div class="d-flex flex-column gap-3">
        <template x-for="(row, index) in rows" :key="row.id">
            <article class="card card-sm">
                <header class="card-header d-flex align-items-center justify-content-between gap-3">
                    <h4 class="mb-0 text-truncate" style="min-width: 0" x-text="row.label || 'New response'"></h4>
                    <button
                        type="button"
                        @click="remove(row.id)"
                        class="btn btn-sm btn-ghost-danger flex-shrink-0"
                    >
                        Delete
                    </button>
                </header>
                <div class="card-body">
                    <div class="row g-3">
                        <label class="col-12 col-sm-8">
                            <span class="form-label">Label</span>
                            <input
                                type="text"
                                :name="`quick_reply_templates[${index}][label]`"
                                x-model="row.label"
                                maxlength="40"
                                required
                                class="form-control"
                            >
                        </label>
                        <label class="col-12 col-sm-4">
                            <span class="form-label">Chip color</span>
                            <select
                                :name="`quick_reply_templates[${index}][color]`"
                                x-model="row.color"
                                class="form-select"
                            >
                                @foreach ($accentOptions as $option)
                                    <option value="{{ $option['key'] }}">{{ $option['label'] }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="col-12">
                            <span class="form-label">Message</span>
                            <textarea
                                :name="`quick_reply_templates[${index}][body]`"
                                x-model="row.body"
                                rows="2"
                                maxlength="1600"
                                required
                                class="form-control"
                            ></textarea>
                        </label>
                    </div>
                </div>
            </article>
        </template>
    </div>

    <div>
        <button
            type="button"
            @click="add()"
            class="btn"
        >
            Add response
        </button>
    </div>
</div>
