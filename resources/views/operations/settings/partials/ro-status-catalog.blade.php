@php
    use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusCatalog;
    use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusColor;
    use App\Ark\Operations\Workboard\JobBoardLaneCatalog;
    use App\Ark\Runtime\Authorization\ArkRole;

    $statusFilterOptions = app(RepairOrderStatusCatalog::class)->filterOptions();
    $statusColorOptions = RepairOrderStatusColor::options();
    $laneCatalog = app(JobBoardLaneCatalog::class);

    $transitionRoles = [
        ArkRole::Admin->value,
        ArkRole::Advisor->value,
        ArkRole::Technician->value,
    ];

    $roleLabels = collect(ArkRole::cases())
        ->mapWithKeys(fn (ArkRole $role): array => [$role->value => $role->label()])
        ->all();

    $advisorLaneOptions = collect($laneCatalog->all())
        ->mapWithKeys(fn (array $lane): array => [$lane['key'] => $lane['name']])
        ->put('custom', 'Own lane (status name)')
        ->all();

    $knownLaneKeys = $laneCatalog->knownKeys();

    $resolveGroup = static function (array $status) use ($laneCatalog): string {
        if ($status['is_terminal'] ?? false) {
            return 'Terminal';
        }

        $laneKey = $status['advisor_lane_key'] ?? '';

        return $laneCatalog->labelForKey((string) $laneKey) ?? 'Unassigned';
    };

    $statusGroups = collect($statusCatalogFormData)
        ->groupBy($resolveGroup)
        ->sortBy(function ($items, string $group) use ($laneCatalog): int {
            if ($group === 'Terminal') {
                return 900;
            }

            if ($group === 'Unassigned') {
                return 800;
            }

            foreach ($laneCatalog->homeBoardColumns() as $index => $column) {
                if ($column['label'] === $group) {
                    return $index;
                }
            }

            return 500;
        });
@endphp

@if ($statusCatalogFormData === [])
    <div class="mt-4 rounded-md border border-amber-200 bg-amber-50 px-3 py-3 text-xs text-amber-900">
        Status catalog not seeded yet. Run <code class="rounded bg-white px-1 py-0.5 text-[11px]">php artisan db:seed --class=RepairOrderStatusCatalogSeeder</code>.
    </div>
@else
    <form method="POST" action="{{ route('operations.settings.shop.status-catalog.update') }}" class="mt-4 space-y-4">
        @csrf
        @method('PATCH')

        <p class="text-xs leading-5 text-slate-500">
            Each block below is one status. Set its name, lane, and whether mileage in, mileage out, or both must be entered before a repair order can move there.
        </p>

        <section class="border border-slate-200 bg-white">
            <div class="border-b border-slate-200 px-3 py-2">
                <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Add lifecycle move</p>
                <p class="mt-0.5 text-[11px] leading-4 text-slate-500">Define who can move from one status to another - including backward moves like Approved → Waiting Approval.</p>
            </div>
            <div class="grid gap-3 px-3 py-3 md:grid-cols-2 xl:grid-cols-5">
                <label class="block text-[11px] font-medium text-slate-500">
                    From status
                    <select name="create_transition[from_slug]" class="mt-1 w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-sm text-slate-950">
                        <option value="">Choose…</option>
                        @foreach ($statusFilterOptions as $option)
                            <option value="{{ $option['value'] }}" @selected(old('create_transition.from_slug') === $option['value'])>{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-[11px] font-medium text-slate-500">
                    To status
                    <select name="create_transition[to_slug]" class="mt-1 w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-sm text-slate-950">
                        <option value="">Choose…</option>
                        @foreach ($statusFilterOptions as $option)
                            <option value="{{ $option['value'] }}" @selected(old('create_transition.to_slug') === $option['value'])>{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                </label>
                @foreach ($transitionRoles as $role)
                    <label class="flex items-end gap-2 pb-1 text-xs font-medium text-slate-600">
                        <input
                            type="checkbox"
                            name="create_transition[roles][]"
                            value="{{ $role }}"
                            @checked(in_array($role, old('create_transition.roles', [ArkRole::Admin->value, ArkRole::Advisor->value]), true))
                            class="rounded border-slate-300 text-slate-800"
                        >
                        {{ $roleLabels[$role] ?? $role }}
                    </label>
                @endforeach
            </div>
            @error('create_transition.from_slug')
                <p class="px-3 pb-2 text-xs text-red-600">{{ $message }}</p>
            @enderror
            @error('create_transition.to_slug')
                <p class="px-3 pb-2 text-xs text-red-600">{{ $message }}</p>
            @enderror
            @error('create_transition.roles')
                <p class="px-3 pb-2 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </section>

        <section class="border border-slate-200 bg-slate-50/60">
            <div class="border-b border-slate-200 px-3 py-2">
                <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Add custom status</p>
                <p class="mt-0.5 text-[11px] leading-4 text-slate-500">Custom statuses appear on the advisor workboard in their own lane or grouped with a built-in lane.</p>
            </div>
            <div class="grid gap-3 px-3 py-3 md:grid-cols-2 xl:grid-cols-5">
                <label class="block text-[11px] font-medium text-slate-500">
                    Display name
                    <input
                        type="text"
                        name="create[name]"
                        value="{{ old('create.name') }}"
                        maxlength="64"
                        placeholder="e.g. Sublet Pending"
                        class="mt-1 w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-sm font-semibold text-slate-950"
                    >
                </label>
                <label class="block text-[11px] font-medium text-slate-500">
                    Slug
                    <input
                        type="text"
                        name="create[slug]"
                        value="{{ old('create.slug') }}"
                        maxlength="32"
                        placeholder="sublet_pending"
                        class="mt-1 w-full rounded-md border border-slate-300 px-2.5 py-1.5 font-mono text-sm text-slate-950"
                    >
                    <span class="mt-0.5 block text-[10px] text-slate-400">Lowercase letters, numbers, underscores.</span>
                </label>
                <label class="block text-[11px] font-medium text-slate-500">
                    Advisor lane
                    <select
                        name="create[advisor_lane_key]"
                        class="mt-1 w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-sm text-slate-950"
                    >
                        @foreach ($advisorLaneOptions as $laneKey => $laneLabel)
                            <option value="{{ $laneKey }}" @selected(old('create.advisor_lane_key', 'work_in_progress') === $laneKey)>{{ $laneLabel }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="block text-[11px] font-medium text-slate-500">
                    Color
                    @include('operations.settings.partials.ro-status-color-picker', [
                        'name' => 'create[color]',
                        'value' => old('create.color', RepairOrderStatusColor::SECONDARY),
                        'options' => $statusColorOptions,
                    ])
                </div>
                <div class="flex flex-col justify-end gap-2 pt-5 text-xs font-medium text-slate-600">
                    <label class="inline-flex items-center gap-2">
                        <input type="hidden" name="create[show_on_advisor_board]" value="0">
                        <input type="checkbox" name="create[show_on_advisor_board]" value="1" @checked(old('create.show_on_advisor_board', '1') === '1') class="rounded border-slate-300 text-slate-800">
                        Advisor board
                    </label>
                    <label class="inline-flex items-center gap-2">
                        <input type="hidden" name="create[show_on_technician_board]" value="0">
                        <input type="checkbox" name="create[show_on_technician_board]" value="0" class="rounded border-slate-300 text-slate-800">
                        Technician board
                    </label>
                    <label class="block text-[11px] font-medium text-slate-500">
                        Mileage required
                        <select name="create[mileage_requirement]" class="mt-1 w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-sm text-slate-950">
                            <option value="none" @selected(old('create.mileage_requirement', 'none') === 'none')>None</option>
                            <option value="in" @selected(old('create.mileage_requirement') === 'in')>Mileage in</option>
                            <option value="out" @selected(old('create.mileage_requirement') === 'out')>Mileage out</option>
                            <option value="both" @selected(old('create.mileage_requirement') === 'both')>Both</option>
                        </select>
                    </label>
                </div>
            </div>
            @error('create.name')
                <p class="px-3 pb-2 text-xs text-red-600">{{ $message }}</p>
            @enderror
            @error('create.slug')
                <p class="px-3 pb-2 text-xs text-red-600">{{ $message }}</p>
            @enderror
            @error('create.advisor_lane_key')
                <p class="px-3 pb-2 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </section>

        @foreach ($statusGroups as $groupName => $statuses)
            <section class="space-y-2">
                <div class="flex flex-wrap items-end justify-between gap-2 px-0.5">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">{{ $groupName }}</p>
                        @if ($groupName === 'Terminal')
                            <p class="mt-0.5 text-[11px] leading-4 text-slate-500">Off the Job Board. Closed visits can still have pickup or payment work. This is not the Completed lane.</p>
                        @endif
                    </div>
                    <span class="text-[11px] font-semibold text-slate-400">{{ $statuses->count() }} {{ str()->plural('status', $statuses->count()) }}</span>
                </div>

                <div class="space-y-3">
                    @foreach ($statuses as $status)
                        @php
                            $mileageRequirement = old(
                                'statuses.'.$status['slug'].'.mileage_requirement',
                                match (true) {
                                    ($status['requires_mileage_in'] ?? false) && ($status['requires_mileage_out'] ?? false) => 'both',
                                    (bool) ($status['requires_mileage_in'] ?? false) => 'in',
                                    (bool) ($status['requires_mileage_out'] ?? false) => 'out',
                                    default => 'none',
                                },
                            );
                            $allowedMoves = collect($status['transitions'])->filter(
                                fn (array $transition): bool => (bool) ($transition['active'] ?? false),
                            )->count();
                        @endphp
                        <article @class([
                            'overflow-hidden rounded-sm border border-slate-300 bg-white',
                            'opacity-80' => ! $status['active'],
                        ])>
                            <div class="flex flex-wrap items-end gap-3 border-b border-slate-200 bg-slate-50 px-3 py-2.5">
                                <label class="block min-w-[14rem] flex-1 text-[11px] font-medium text-slate-500">
                                    Status
                                    <input
                                        type="text"
                                        name="statuses[{{ $status['slug'] }}][name]"
                                        value="{{ old('statuses.'.$status['slug'].'.name', $status['name']) }}"
                                        maxlength="64"
                                        class="mt-1 w-full rounded-sm border border-slate-300 bg-white px-2.5 py-1.5 text-sm font-semibold text-slate-950"
                                    >
                                </label>
                                <div class="block w-44 text-[11px] font-medium text-slate-500">
                                    Color
                                    @include('operations.settings.partials.ro-status-color-picker', [
                                        'name' => 'statuses['.$status['slug'].'][color]',
                                        'value' => old('statuses.'.$status['slug'].'.color', $status['color'] ?? RepairOrderStatusColor::SECONDARY),
                                        'options' => $statusColorOptions,
                                    ])
                                </div>
                                <p class="pb-2 font-mono text-[10px] text-slate-400">{{ $status['slug'] }}</p>
                            </div>

                            <div class="grid gap-3 px-3 py-3 sm:grid-cols-2 xl:grid-cols-4">
                                @unless ($status['is_terminal'])
                                    <label class="block text-[11px] font-medium text-slate-500">
                                        Job Board lane
                                        <select
                                            name="statuses[{{ $status['slug'] }}][advisor_lane_key]"
                                            class="mt-1 w-full rounded-sm border border-slate-300 px-2.5 py-1.5 text-sm text-slate-950"
                                        >
                                            @foreach ($advisorLaneOptions as $laneKey => $laneLabel)
                                                @php
                                                    $selectedLane = old('statuses.'.$status['slug'].'.advisor_lane_key', $status['advisor_lane_key'] ?? 'work_in_progress');
                                                    $optionValue = $laneKey === 'custom' ? 'custom' : $laneKey;
                                                @endphp
                                                <option value="{{ $optionValue }}" @selected($selectedLane === $laneKey || ($laneKey === 'custom' && ! in_array($selectedLane, $knownLaneKeys, true)))>{{ $laneLabel }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="block max-w-[6rem] text-[11px] font-medium text-slate-500">
                                        Order
                                        <input
                                            type="number"
                                            name="statuses[{{ $status['slug'] }}][sort_order]"
                                            value="{{ old('statuses.'.$status['slug'].'.sort_order', $status['sort_order'] ?? 0) }}"
                                            min="0"
                                            max="999"
                                            class="mt-1 w-full rounded-sm border border-slate-300 px-2.5 py-1.5 text-sm text-slate-950"
                                        >
                                    </label>
                                @endunless
                                <label class="block text-[11px] font-medium text-slate-500">
                                    Mileage required
                                    <select
                                        name="statuses[{{ $status['slug'] }}][mileage_requirement]"
                                        class="mt-1 w-full rounded-sm border border-slate-300 px-2.5 py-1.5 text-sm text-slate-950"
                                    >
                                        <option value="none" @selected($mileageRequirement === 'none')>None</option>
                                        <option value="in" @selected($mileageRequirement === 'in')>Mileage in</option>
                                        <option value="out" @selected($mileageRequirement === 'out')>Mileage out</option>
                                        <option value="both" @selected($mileageRequirement === 'both')>Both</option>
                                    </select>
                                </label>
                                <div class="flex flex-col justify-end gap-2 pb-1 text-xs font-medium text-slate-600">
                                    @unless ($status['is_terminal'])
                                        <label class="inline-flex items-center gap-2">
                                            <input type="hidden" name="statuses[{{ $status['slug'] }}][show_on_advisor_board]" value="0">
                                            <input
                                                type="checkbox"
                                                name="statuses[{{ $status['slug'] }}][show_on_advisor_board]"
                                                value="1"
                                                @checked(old('statuses.'.$status['slug'].'.show_on_advisor_board', $status['show_on_advisor_board']))
                                                class="rounded border-slate-300 text-slate-800"
                                            >
                                            Advisor board
                                        </label>
                                        <label class="inline-flex items-center gap-2">
                                            <input type="hidden" name="statuses[{{ $status['slug'] }}][show_on_technician_board]" value="0">
                                            <input
                                                type="checkbox"
                                                name="statuses[{{ $status['slug'] }}][show_on_technician_board]"
                                                value="1"
                                                @checked(old('statuses.'.$status['slug'].'.show_on_technician_board', $status['show_on_technician_board']))
                                                class="rounded border-slate-300 text-slate-800"
                                            >
                                            Technician board
                                        </label>
                                    @endunless
                                    @if (! ($status['is_system'] ?? true))
                                        <label class="inline-flex items-center gap-2">
                                            <input type="hidden" name="statuses[{{ $status['slug'] }}][is_terminal]" value="0">
                                            <input
                                                type="checkbox"
                                                name="statuses[{{ $status['slug'] }}][is_terminal]"
                                                value="1"
                                                @checked(old('statuses.'.$status['slug'].'.is_terminal', $status['is_terminal']))
                                                class="rounded border-slate-300 text-slate-800"
                                            >
                                            Hide from Job Board
                                        </label>
                                    @endif
                                </div>
                            </div>

                            @if ($status['variants'] !== [])
                                <div class="flex flex-wrap gap-4 border-t border-slate-200 px-3 py-3">
                                    @foreach ($status['variants'] as $variant)
                                        <label class="block min-w-[10rem] text-[11px] font-medium text-slate-500">
                                            Close - {{ $variant['key'] }}
                                            <input
                                                type="text"
                                                name="variants[{{ $variant['id'] }}][name]"
                                                value="{{ old('variants.'.$variant['id'].'.name', $variant['name']) }}"
                                                maxlength="64"
                                                class="mt-1 w-full rounded-sm border border-slate-300 px-2.5 py-1.5 text-sm text-slate-950"
                                            >
                                            @if ($variant['bypass_standard_close_rules'])
                                                <span class="mt-1 block text-[10px] font-semibold text-amber-700">Skips the usual close checks</span>
                                            @endif
                                        </label>
                                    @endforeach
                                </div>
                            @endif

                            @if ($status['transitions'] !== [])
                                <details class="border-t border-slate-200">
                                    <summary class="cursor-pointer px-3 py-2 text-xs font-semibold text-slate-700">
                                        Who can move this
                                        <span class="font-medium text-slate-400">{{ $allowedMoves }}</span>
                                    </summary>
                                    <div class="overflow-x-auto px-3 pb-3">
                                        <table class="min-w-full border-collapse text-left text-[11px]">
                                            <thead>
                                                <tr class="border-b border-slate-200 text-[10px] font-bold uppercase tracking-wide text-slate-400">
                                                    <th class="py-1.5 pr-3 font-bold">Move to</th>
                                                    @foreach ($transitionRoles as $role)
                                                        <th class="px-2 py-1.5 text-center font-bold">{{ $roleLabels[$role] ?? $role }}</th>
                                                    @endforeach
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-100">
                                                @foreach ($status['transitions'] as $transition)
                                                    @php
                                                        $transitionKey = $transition['form_key'];
                                                        $oldRoles = old('transitions.'.$transitionKey.'.roles', $transition['roles']);
                                                    @endphp
                                                    <tr @class(['text-slate-400' => $oldRoles === []])>
                                                        <td class="py-2 pr-3 font-semibold text-slate-800">{{ $transition['to_name'] }}</td>
                                                        @foreach ($transitionRoles as $role)
                                                            <td class="px-2 py-2 text-center">
                                                                <input
                                                                    type="checkbox"
                                                                    name="transitions[{{ $transitionKey }}][roles][]"
                                                                    value="{{ $role }}"
                                                                    @checked(in_array($role, $oldRoles, true))
                                                                    class="rounded border-slate-300 text-slate-800"
                                                                >
                                                            </td>
                                                        @endforeach
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </details>
                            @endif
                        </article>
                    @endforeach
                </div>
            </section>
        @endforeach

        <div class="flex justify-end border-t border-slate-200 pt-4">
            <button type="submit" class="min-h-10 rounded-md bg-slate-950 px-5 text-sm font-semibold text-white hover:bg-slate-800">
                Save status catalog
            </button>
        </div>
    </form>
@endif
