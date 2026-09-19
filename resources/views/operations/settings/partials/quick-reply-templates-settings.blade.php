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
    class="space-y-3"
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
        <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Canned responses</p>
        <p class="mt-1 text-xs leading-5 text-slate-500">
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
        <p class="rounded-sm border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-900">{{ $quickReplyError }}</p>
    @endif

    <div class="space-y-3">
        <template x-for="(row, index) in rows" :key="row.id">
            <article class="overflow-hidden rounded-sm border border-slate-300 bg-white">
                <header class="flex items-center justify-between gap-3 border-b border-slate-200 bg-slate-50 px-3 py-2">
                    <p class="min-w-0 truncate text-sm font-semibold text-slate-950" x-text="row.label || 'New response'"></p>
                    <button
                        type="button"
                        @click="remove(row.id)"
                        class="shrink-0 rounded-md px-2 py-1 text-xs font-semibold text-slate-500 hover:bg-rose-50 hover:text-rose-700"
                    >
                        Delete
                    </button>
                </header>
                <div class="grid gap-3 p-3 sm:grid-cols-[1fr_11rem]">
                    <label class="block">
                        <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Label</span>
                        <input
                            type="text"
                            :name="`quick_reply_templates[${index}][label]`"
                            x-model="row.label"
                            maxlength="40"
                            required
                            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950"
                        >
                    </label>
                    <label class="block">
                        <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Chip color</span>
                        <select
                            :name="`quick_reply_templates[${index}][color]`"
                            x-model="row.color"
                            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950"
                        >
                            @foreach ($accentOptions as $option)
                                <option value="{{ $option['key'] }}">{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block sm:col-span-2">
                        <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Message</span>
                        <textarea
                            :name="`quick_reply_templates[${index}][body]`"
                            x-model="row.body"
                            rows="2"
                            maxlength="1600"
                            required
                            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950"
                        ></textarea>
                    </label>
                </div>
            </article>
        </template>
    </div>

    <button
        type="button"
        @click="add()"
        class="min-h-9 rounded-md border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:border-slate-400 hover:text-slate-950"
    >
        Add response
    </button>
</div>
