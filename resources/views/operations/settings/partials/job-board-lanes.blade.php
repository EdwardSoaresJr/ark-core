@php
    use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusColor;

    $laneColorOptions = RepairOrderStatusColor::options();
    $jobBoardLanes = $jobBoardLanes ?? [];
@endphp

<form method="POST" action="{{ route('operations.settings.shop.job-board-lanes.update') }}" class="mt-4 space-y-4">
    @csrf
    @method('PATCH')

    <section class="border border-slate-200 bg-white">
        <div class="border-b border-slate-200 px-3 py-2">
            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Job Board lanes</p>
            <p class="mt-0.5 text-[11px] leading-4 text-slate-500">
                These are the shop queues on the Job Board. Rename, reorder, and recolor them. Lane color is visual identity - it is not ARK attention.
            </p>
        </div>

        <div class="divide-y divide-slate-100">
            @foreach ($jobBoardLanes as $lane)
                <article class="grid gap-3 px-3 py-3 sm:grid-cols-2 xl:grid-cols-[minmax(16rem,1.6fr)_13rem_5.5rem_auto] xl:items-end">
                    <label class="block min-w-0 text-[11px] font-medium text-slate-500">
                        Name
                        <input
                            type="text"
                            name="lanes[{{ $lane['key'] }}][name]"
                            value="{{ old('lanes.'.$lane['key'].'.name', $lane['name']) }}"
                            maxlength="64"
                            class="mt-1 w-full min-w-0 rounded-md border border-slate-300 px-2.5 py-1.5 text-sm font-semibold text-slate-950"
                        >
                        <span class="mt-0.5 block font-mono text-[10px] text-slate-400">{{ $lane['key'] }}</span>
                    </label>
                    <div class="block min-w-0 text-[11px] font-medium text-slate-500">
                        Color
                        @include('operations.settings.partials.ro-status-color-picker', [
                            'name' => 'lanes['.$lane['key'].'][color]',
                            'value' => old('lanes.'.$lane['key'].'.color', $lane['color']),
                            'options' => $laneColorOptions,
                        ])
                    </div>
                    <label class="block w-24 text-[11px] font-medium text-slate-500">
                        Order
                        <input
                            type="number"
                            name="lanes[{{ $lane['key'] }}][sort_order]"
                            value="{{ old('lanes.'.$lane['key'].'.sort_order', $lane['sort_order']) }}"
                            min="0"
                            max="999"
                            class="mt-1 w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-sm text-slate-950"
                        >
                    </label>
                    <label class="inline-flex items-center gap-2 pb-1 text-xs font-medium text-slate-600">
                        <input type="hidden" name="lanes[{{ $lane['key'] }}][active]" value="0">
                        <input
                            type="checkbox"
                            name="lanes[{{ $lane['key'] }}][active]"
                            value="1"
                            @checked(old('lanes.'.$lane['key'].'.active', $lane['active']))
                            class="rounded border-slate-300 text-slate-800"
                        >
                        Show on Job Board
                    </label>
                </article>
            @endforeach
        </div>
    </section>

    <section class="border border-slate-200 bg-slate-50/60">
        <div class="border-b border-slate-200 px-3 py-2">
            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Add lane</p>
            <p class="mt-0.5 text-[11px] leading-4 text-slate-500">Add another shop queue, then assign statuses to it below.</p>
        </div>
        <div class="grid gap-3 px-3 py-3 md:grid-cols-3">
            <label class="block text-[11px] font-medium text-slate-500">
                Name
                <input
                    type="text"
                    name="create[name]"
                    value="{{ old('create.name') }}"
                    maxlength="64"
                    placeholder="e.g. Sublet"
                    class="mt-1 w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-sm font-semibold text-slate-950"
                >
            </label>
            <div class="block text-[11px] font-medium text-slate-500">
                Color
                @include('operations.settings.partials.ro-status-color-picker', [
                    'name' => 'create[color]',
                    'value' => old('create.color', RepairOrderStatusColor::SECONDARY),
                    'options' => $laneColorOptions,
                ])
            </div>
            <div class="flex items-end">
                <button type="submit" class="min-h-10 rounded-md bg-slate-950 px-4 text-sm font-semibold text-white hover:bg-slate-800">
                    Save Job Board lanes
                </button>
            </div>
        </div>
        @error('create.name')
            <p class="px-3 pb-2 text-xs text-red-600">{{ $message }}</p>
        @enderror
        @error('lanes')
            <p class="px-3 pb-2 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </section>
</form>
