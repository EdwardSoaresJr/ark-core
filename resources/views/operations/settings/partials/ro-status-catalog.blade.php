@php
    use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusCatalog;
    use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusCatalogDefaults;
    use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusColor;
    use App\Ark\Runtime\Authorization\ArkRole;

    $statusFilterOptions = app(RepairOrderStatusCatalog::class)->filterOptions();
    $statusColorOptions = RepairOrderStatusColor::options();

    $transitionRoles = [
        ArkRole::Admin->value,
        ArkRole::Advisor->value,
        ArkRole::Technician->value,
    ];

    $roleLabels = collect(ArkRole::cases())
        ->mapWithKeys(fn (ArkRole $role): array => [$role->value => $role->label()])
        ->all();

    $compatSlugs = ['ready_for_work', 'ready_pickup'];

    $advisorLaneOptions = collect(RepairOrderStatusCatalogDefaults::advisorLaneTemplates())
        ->mapWithKeys(fn (array $lane): array => [$lane['key'] => $lane['label']])
        ->put('custom', 'Own lane (status name)')
        ->all();

    $resolveGroup = static function (array $status) use ($compatSlugs): string {
        if (in_array($status['slug'], $compatSlugs, true)) {
            return 'Compatibility';
        }

        if ($status['slug'] === 'closed') {
            return 'Terminal';
        }

        if (! ($status['is_system'] ?? true)) {
            return 'Custom';
        }

        return $status['dashboard_group_name'] ?? 'Other';
    };

    $groupOrder = ['Estimates', 'Work in progress', 'Finalizing & pickup', 'Completed', 'Custom', 'Terminal', 'Compatibility'];

    $statusGroups = collect($statusCatalogFormData)
        ->groupBy($resolveGroup)
        ->sortBy(fn ($items, string $group): int => array_search($group, $groupOrder, true) ?: 99);
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
            Rename statuses, set board colors, control workboard visibility, and choose who may use each lifecycle move. A move is on when at least one role is checked — uncheck every role to turn it off. Admin and advisor start checked by default; technician only on sensible bay moves.
        </p>

        <section class="border border-slate-200 bg-white">
            <div class="border-b border-slate-200 px-3 py-2">
                <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Add lifecycle move</p>
                <p class="mt-0.5 text-[11px] leading-4 text-slate-500">Define who can move from one status to another — including backward moves like Approved → Waiting Approval.</p>
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
                            <option value="{{ $laneKey }}" @selected(old('create.advisor_lane_key', 'shop_floor') === $laneKey)>{{ $laneLabel }}</option>
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
            <section @class([
                'border border-slate-200',
                'border-dashed border-slate-300 bg-slate-50/40' => $groupName === 'Compatibility',
            ])>
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 bg-slate-50 px-3 py-2">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">{{ $groupName }}</p>
                        @if ($groupName === 'Compatibility')
                            <p class="mt-0.5 text-[11px] leading-4 text-slate-500">Legacy status strings on imported repair orders. Prefer the primary statuses above for new workflow.</p>
                        @endif
                        @if ($groupName === 'Custom')
                            <p class="mt-0.5 text-[11px] leading-4 text-slate-500">Shop-defined workflow statuses. Slug keys are fixed after creation.</p>
                        @endif
                    </div>
                    <span class="text-[11px] font-semibold text-slate-400">{{ $statuses->count() }} {{ str()->plural('status', $statuses->count()) }}</span>
                </div>

                <div class="divide-y divide-slate-100">
                    @foreach ($statuses as $status)
                        <article @class(['px-3 py-3', 'opacity-80' => ! $status['active']])>
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-[12rem] flex-1">
                                    <div class="flex flex-wrap items-end gap-3">
                                        <label class="block min-w-[12rem] flex-1 text-[11px] font-medium text-slate-500">
                                            Display name
                                            <input
                                                type="text"
                                                name="statuses[{{ $status['slug'] }}][name]"
                                                value="{{ old('statuses.'.$status['slug'].'.name', $status['name']) }}"
                                                maxlength="64"
                                                class="mt-1 w-full max-w-sm rounded-md border border-slate-300 px-2.5 py-1.5 text-sm font-semibold text-slate-950"
                                            >
                                        </label>
                                        <div class="block min-w-[10rem] text-[11px] font-medium text-slate-500">
                                            Color
                                            @include('operations.settings.partials.ro-status-color-picker', [
                                                'name' => 'statuses['.$status['slug'].'][color]',
                                                'value' => old('statuses.'.$status['slug'].'.color', $status['color'] ?? RepairOrderStatusColor::SECONDARY),
                                                'options' => $statusColorOptions,
                                            ])
                                        </div>
                                    </div>
                                    <p class="mt-1 font-mono text-[10px] text-slate-400">{{ $status['slug'] }}</p>
                                    @if (! ($status['is_system'] ?? true))
                                        <label class="mt-2 block text-[11px] font-medium text-slate-500">
                                            Advisor lane
                                            <select
                                                name="statuses[{{ $status['slug'] }}][advisor_lane_key]"
                                                class="mt-1 w-full max-w-sm rounded-md border border-slate-300 px-2.5 py-1.5 text-sm text-slate-950"
                                            >
                                                @foreach ($advisorLaneOptions as $laneKey => $laneLabel)
                                                    @php
                                                        $selectedLane = old('statuses.'.$status['slug'].'.advisor_lane_key', $status['advisor_lane_key'] ?? 'shop_floor');
                                                        $optionValue = $laneKey === 'custom' ? 'custom' : $laneKey;
                                                    @endphp
                                                    <option value="{{ $optionValue }}" @selected($selectedLane === $laneKey || ($laneKey === 'custom' && ! in_array($selectedLane, array_keys($advisorLaneOptions), true)))>{{ $laneLabel }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                    @endif
                                </div>

                                @unless ($status['is_terminal'])
                                    <div class="flex flex-wrap gap-4 pt-5 text-xs font-medium text-slate-600">
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
                                    </div>
                                @endunless
                            </div>

                            @if ($status['variants'] !== [])
                                <div class="mt-3 flex flex-wrap gap-4">
                                    @foreach ($status['variants'] as $variant)
                                        <label class="block min-w-[10rem] text-[11px] font-medium text-slate-500">
                                            Close — {{ $variant['key'] }}
                                            <input
                                                type="text"
                                                name="variants[{{ $variant['id'] }}][name]"
                                                value="{{ old('variants.'.$variant['id'].'.name', $variant['name']) }}"
                                                maxlength="64"
                                                class="mt-1 w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-sm text-slate-950"
                                            >
                                            @if ($variant['bypass_standard_close_rules'])
                                                <span class="mt-1 block text-[10px] font-semibold text-amber-700">Bypasses standard close rules</span>
                                            @endif
                                        </label>
                                    @endforeach
                                </div>
                            @endif

                            @if ($status['transitions'] !== [])
                                <div class="mt-3 overflow-x-auto">
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
