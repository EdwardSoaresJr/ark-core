@php
    use App\Ark\Operations\Evidence\EvidenceVisibility;

    $gallery = $evidenceGallery ?? ['items' => collect(), 'concerns' => collect()];
    $items = $gallery['items'] ?? collect();
    $concerns = $gallery['concerns'] ?? collect();
    $isTerminal = $isTerminal ?? false;
@endphp

<section
    id="evidence-gallery"
    class="mb-4 rounded-md border border-slate-200 bg-white px-3 py-3"
    x-data="{ filter: 'all' }"
>
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Evidence</p>
            <p class="text-sm font-medium text-slate-900">Proof for this repair order</p>
        </div>
        <p class="text-[11px] text-slate-500">{{ $items->count() }} active</p>
    </div>

    <div class="mt-2 flex flex-wrap gap-1.5">
        <button type="button" class="rounded border px-2 py-1 text-[11px] font-medium" :class="filter === 'all' ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-300 text-slate-700'" @click="filter = 'all'">All</button>
        <button type="button" class="rounded border px-2 py-1 text-[11px] font-medium" :class="filter === 'general' ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-300 text-slate-700'" @click="filter = 'general'">General</button>
        @foreach ($concerns as $concern)
            <button
                type="button"
                class="rounded border px-2 py-1 text-[11px] font-medium"
                :class="filter === 'concern:{{ $concern['id'] }}' ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-300 text-slate-700'"
                @click="filter = 'concern:{{ $concern['id'] }}'"
            >{{ \Illuminate\Support\Str::limit($concern['summary'], 28) }}</button>
        @endforeach
    </div>

    @unless ($isTerminal || ($presentationOnly ?? false))
        <form
            method="POST"
            action="{{ route('operations.repair-orders.evidence.store', $repairOrder) }}"
            enctype="multipart/form-data"
            class="mt-3 grid gap-2 border-t border-slate-100 pt-3 sm:grid-cols-[1fr_1fr_1fr_auto]"
        >
            @csrf
            <div>
                <label class="block text-[11px] font-medium text-slate-600" for="evidence-file">File</label>
                <input id="evidence-file" name="file" type="file" required accept="image/*,video/*,application/pdf" class="mt-0.5 w-full text-xs">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-[11px] font-medium text-slate-600" for="evidence-target">Attach to</label>
                <select
                    id="evidence-target"
                    name="attachable_id"
                    required
                    class="mt-0.5 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm"
                    onchange="document.getElementById('evidence-attachable-kind').value = this.options[this.selectedIndex].dataset.kind"
                >
                    <option value="{{ $repairOrder->id }}" data-kind="repair_order" selected>General (this RO)</option>
                    @foreach ($concerns as $concern)
                        <option value="{{ $concern['id'] }}" data-kind="concern">{{ \Illuminate\Support\Str::limit($concern['summary'], 40) }}</option>
                    @endforeach
                </select>
                <input type="hidden" name="attachable_kind" id="evidence-attachable-kind" value="repair_order">
            </div>
            <div class="sm:col-span-3">
                <label class="block text-[11px] font-medium text-slate-600" for="evidence-caption">Caption</label>
                <input id="evidence-caption" name="caption" type="text" maxlength="500" placeholder="What was seen (not a diagnosis)" class="mt-0.5 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm">
            </div>
            <div class="flex items-end">
                <label class="flex items-center gap-1.5 text-[11px] text-slate-700">
                    <input type="checkbox" name="as_primary" value="1" class="rounded border-slate-300">
                    Primary
                </label>
            </div>
            <div class="flex items-end sm:col-span-3">
                <input type="hidden" name="source" value="upload">
                <button type="submit" class="rounded-md bg-slate-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-slate-800">Attach evidence</button>
            </div>
        </form>
    @endunless

    <div class="mt-3 space-y-2">
        @forelse ($items as $item)
            <div
                class="flex flex-wrap items-start gap-3 rounded border border-slate-100 bg-slate-50/60 px-2 py-2"
                x-show="filter === 'all' || filter === @js($item['filter'])"
            >
                <a href="{{ $item['url'] }}" target="_blank" rel="noopener" class="block h-16 w-16 shrink-0 overflow-hidden rounded bg-slate-200">
                    @if ($item['is_image'])
                        <img src="{{ $item['url'] }}" alt="" class="h-full w-full object-cover">
                    @elseif ($item['is_video'])
                        <span class="flex h-full items-center justify-center text-[10px] font-semibold text-slate-600">VIDEO</span>
                    @else
                        <span class="flex h-full items-center justify-center text-[10px] font-semibold text-slate-600">PDF</span>
                    @endif
                </a>
                <div class="min-w-0 flex-1 text-sm">
                    <p class="font-medium text-slate-900">
                        {{ $item['type_label'] }}
                        @if ($item['is_primary'])
                            <span class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-amber-900">Primary</span>
                        @endif
                        <span class="ml-1 text-xs font-normal text-slate-500">· {{ $item['visibility_label'] }}</span>
                    </p>
                    @if (filled($item['caption']))
                        <p class="text-slate-700">{{ $item['caption'] }}</p>
                    @endif
                    <p class="text-[11px] text-slate-500">
                        {{ $item['filter'] === 'general' ? 'General' : 'Concern' }}
                        @if ($item['shared_at'])
                            · Shared {{ \Illuminate\Support\Carbon::parse($item['shared_at'])->timezone(config('app.timezone'))->format('M j g:i A') }}
                        @endif
                        @if ($item['first_customer_viewed_at'])
                            · Viewed {{ \Illuminate\Support\Carbon::parse($item['first_customer_viewed_at'])->timezone(config('app.timezone'))->format('M j g:i A') }}
                        @endif
                    </p>
                </div>
                @unless ($isTerminal)
                    <div class="flex flex-wrap gap-1">
                        @if ($item['visibility'] !== EvidenceVisibility::Shared->value)
                            <form method="POST" action="{{ route('operations.repair-orders.evidence.visibility', [$repairOrder, $item['id']]) }}">
                                @csrf
                                <input type="hidden" name="visibility" value="{{ EvidenceVisibility::Shared->value }}">
                                <button type="submit" class="rounded border border-slate-300 px-2 py-1 text-[11px] font-medium text-slate-800 hover:bg-white">Show to Customer</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('operations.repair-orders.evidence.visibility', [$repairOrder, $item['id']]) }}">
                                @csrf
                                <input type="hidden" name="visibility" value="{{ EvidenceVisibility::Internal->value }}">
                                <button type="submit" class="rounded border border-slate-300 px-2 py-1 text-[11px] font-medium text-slate-800 hover:bg-white">Keep internal</button>
                            </form>
                        @endif
                        @unless ($item['is_primary'])
                            <form method="POST" action="{{ route('operations.repair-orders.evidence.primary', [$repairOrder, $item['id']]) }}">
                                @csrf
                                <button type="submit" class="rounded border border-slate-300 px-2 py-1 text-[11px] font-medium text-slate-800 hover:bg-white">Set primary</button>
                            </form>
                        @endunless
                        <form method="POST" action="{{ route('operations.repair-orders.evidence.retire', [$repairOrder, $item['id']]) }}" onsubmit="return confirm('Retire this evidence? File is kept; it leaves active galleries.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="rounded border border-rose-200 px-2 py-1 text-[11px] font-medium text-rose-800 hover:bg-rose-50">Retire</button>
                        </form>
                    </div>
                @endunless
            </div>
        @empty
            <p class="text-xs text-slate-500">No evidence yet. Attach photos, video, or PDFs as proof.</p>
        @endforelse
    </div>
</section>
