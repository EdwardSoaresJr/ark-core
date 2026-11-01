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
    <div class="alert alert-warning mb-0" role="status">
        Status catalog not seeded yet. Run <code>php artisan db:seed --class=RepairOrderStatusCatalogSeeder</code>.
    </div>
@else
    <form method="POST" action="{{ route('operations.settings.shop.status-catalog.update') }}" data-settings-status-catalog>
        @csrf
        @method('PATCH')

        <p class="text-secondary small">
            Each block below is one status. Set its name, lane, and whether mileage in, mileage out, or both must be entered before a repair order can move there.
        </p>

        <div class="card card-sm mb-3">
            <div class="card-header">
                <div>
                    <h4 class="card-title">Add lifecycle move</h4>
                    <div class="card-subtitle">Define who can move from one status to another - including backward moves like Approved → Waiting Approval.</div>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-md-6 col-xl-3">
                        <label for="status-transition-from" class="form-label">From status</label>
                        <select id="status-transition-from" name="create_transition[from_slug]" @class(['form-select', 'is-invalid' => $errors->has('create_transition.from_slug')])>
                            <option value="">Choose…</option>
                            @foreach ($statusFilterOptions as $option)
                                <option value="{{ $option['value'] }}" @selected(old('create_transition.from_slug') === $option['value'])>{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-6 col-xl-3">
                        <label for="status-transition-to" class="form-label">To status</label>
                        <select id="status-transition-to" name="create_transition[to_slug]" @class(['form-select', 'is-invalid' => $errors->has('create_transition.to_slug')])>
                            <option value="">Choose…</option>
                            @foreach ($statusFilterOptions as $option)
                                <option value="{{ $option['value'] }}" @selected(old('create_transition.to_slug') === $option['value'])>{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-xl-6">
                        <div class="form-label">Who can make this move</div>
                        <div class="d-flex flex-wrap gap-3">
                            @foreach ($transitionRoles as $role)
                                <label class="form-check form-check-inline mb-2 me-0">
                                    <input
                                        type="checkbox"
                                        name="create_transition[roles][]"
                                        value="{{ $role }}"
                                        @checked(in_array($role, old('create_transition.roles', [ArkRole::Admin->value, ArkRole::Advisor->value]), true))
                                        class="form-check-input"
                                    >
                                    <span class="form-check-label">{{ $roleLabels[$role] ?? $role }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
                @error('create_transition.from_slug')
                    <div class="text-danger small mt-2">{{ $message }}</div>
                @enderror
                @error('create_transition.to_slug')
                    <div class="text-danger small mt-2">{{ $message }}</div>
                @enderror
                @error('create_transition.roles')
                    <div class="text-danger small mt-2">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="card card-sm mb-3">
            <div class="card-header">
                <div>
                    <h4 class="card-title">Add custom status</h4>
                    <div class="card-subtitle">Custom statuses appear on the advisor workboard in their own lane or grouped with a built-in lane.</div>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-2 align-items-start">
                    <div class="col-12 col-md-6 col-xl-4">
                        <label for="status-create-name" class="form-label">Display name</label>
                        <input
                            id="status-create-name"
                            type="text"
                            name="create[name]"
                            value="{{ old('create.name') }}"
                            maxlength="64"
                            placeholder="e.g. Sublet Pending"
                            @class(['form-control fw-medium', 'is-invalid' => $errors->has('create.name')])
                        >
                    </div>
                    <div class="col-12 col-md-6 col-xl-4">
                        <label for="status-create-slug" class="form-label">Slug</label>
                        <input
                            id="status-create-slug"
                            type="text"
                            name="create[slug]"
                            value="{{ old('create.slug') }}"
                            maxlength="32"
                            placeholder="sublet_pending"
                            @class(['form-control font-monospace', 'is-invalid' => $errors->has('create.slug')])
                        >
                        <div class="form-hint">Lowercase letters, numbers, underscores.</div>
                    </div>
                    <div class="col-12 col-md-6 col-xl-4">
                        <label for="status-create-lane" class="form-label">Advisor lane</label>
                        <select id="status-create-lane" name="create[advisor_lane_key]" @class(['form-select', 'is-invalid' => $errors->has('create.advisor_lane_key')])>
                            @foreach ($advisorLaneOptions as $laneKey => $laneLabel)
                                <option value="{{ $laneKey }}" @selected(old('create.advisor_lane_key', 'work_in_progress') === $laneKey)>{{ $laneLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-6 col-xl-4">
                        <div id="status-create-color" class="form-label">Color</div>
                        @include('operations.settings.partials.ro-status-color-picker', [
                            'name' => 'create[color]',
                            'value' => old('create.color', RepairOrderStatusColor::SECONDARY),
                            'options' => $statusColorOptions,
                            'labelledBy' => 'status-create-color',
                        ])
                    </div>
                    <div class="col-12 col-md-6 col-xl-4">
                        <label for="status-create-mileage" class="form-label">Mileage required</label>
                        <select id="status-create-mileage" name="create[mileage_requirement]" class="form-select">
                            <option value="none" @selected(old('create.mileage_requirement', 'none') === 'none')>None</option>
                            <option value="in" @selected(old('create.mileage_requirement') === 'in')>Mileage in</option>
                            <option value="out" @selected(old('create.mileage_requirement') === 'out')>Mileage out</option>
                            <option value="both" @selected(old('create.mileage_requirement') === 'both')>Both</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-6 col-xl-4 align-self-end">
                        <div class="d-flex flex-wrap gap-3 pb-2">
                            <label class="form-check mb-0">
                                <input type="hidden" name="create[show_on_advisor_board]" value="0">
                                <input type="checkbox" name="create[show_on_advisor_board]" value="1" @checked(old('create.show_on_advisor_board', '1') === '1') class="form-check-input">
                                <span class="form-check-label">Advisor board</span>
                            </label>
                            <label class="form-check mb-0">
                                <input type="hidden" name="create[show_on_technician_board]" value="0">
                                <input type="checkbox" name="create[show_on_technician_board]" value="0" class="form-check-input">
                                <span class="form-check-label">Technician board</span>
                            </label>
                        </div>
                    </div>
                </div>
                @error('create.name')
                    <div class="text-danger small mt-2">{{ $message }}</div>
                @enderror
                @error('create.slug')
                    <div class="text-danger small mt-2">{{ $message }}</div>
                @enderror
                @error('create.advisor_lane_key')
                    <div class="text-danger small mt-2">{{ $message }}</div>
                @enderror
            </div>
        </div>

        @foreach ($statusGroups as $groupName => $statuses)
            <section class="mb-3" data-settings-status-group>
                <div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-2">
                    <div>
                        <div class="subheader">{{ $groupName }}</div>
                        @if ($groupName === 'Terminal')
                            <div class="text-secondary small">Off the Job Board. Closed visits can still have pickup or payment work. This is not the Completed lane.</div>
                        @endif
                    </div>
                    <span class="text-secondary small">{{ $statuses->count() }} {{ str()->plural('status', $statuses->count()) }}</span>
                </div>

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
                        $statusField = 'status-'.$status['slug'];
                    @endphp
                    <article @class(['card card-sm mb-2', 'opacity-75' => ! $status['active']]) data-settings-status="{{ $status['slug'] }}">
                        <div class="card-header">
                            <div class="row g-2 align-items-end flex-fill">
                                <div class="col-12 col-md">
                                    <label for="{{ $statusField }}-name" class="form-label">Status</label>
                                    <input
                                        id="{{ $statusField }}-name"
                                        type="text"
                                        name="statuses[{{ $status['slug'] }}][name]"
                                        value="{{ old('statuses.'.$status['slug'].'.name', $status['name']) }}"
                                        maxlength="64"
                                        class="form-control fw-medium"
                                    >
                                </div>
                                <div class="col-8 col-md-4 col-xl-3">
                                    <div id="{{ $statusField }}-color" class="form-label">Color</div>
                                    @include('operations.settings.partials.ro-status-color-picker', [
                                        'name' => 'statuses['.$status['slug'].'][color]',
                                        'value' => old('statuses.'.$status['slug'].'.color', $status['color'] ?? RepairOrderStatusColor::SECONDARY),
                                        'options' => $statusColorOptions,
                                        'labelledBy' => $statusField.'-color',
                                    ])
                                </div>
                                <div class="col-4 col-md-auto">
                                    <div class="font-monospace small text-secondary pb-2 text-truncate">{{ $status['slug'] }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="row g-2 align-items-end">
                                @unless ($status['is_terminal'])
                                    <div class="col-12 col-sm-6 col-xl-3">
                                        <label for="{{ $statusField }}-lane" class="form-label">Job Board lane</label>
                                        <select id="{{ $statusField }}-lane" name="statuses[{{ $status['slug'] }}][advisor_lane_key]" class="form-select">
                                            @foreach ($advisorLaneOptions as $laneKey => $laneLabel)
                                                @php
                                                    $selectedLane = old('statuses.'.$status['slug'].'.advisor_lane_key', $status['advisor_lane_key'] ?? 'work_in_progress');
                                                    $optionValue = $laneKey === 'custom' ? 'custom' : $laneKey;
                                                @endphp
                                                <option value="{{ $optionValue }}" @selected($selectedLane === $laneKey || ($laneKey === 'custom' && ! in_array($selectedLane, $knownLaneKeys, true)))>{{ $laneLabel }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-4 col-sm-2 col-xl-1">
                                        <label for="{{ $statusField }}-order" class="form-label">Order</label>
                                        <input
                                            id="{{ $statusField }}-order"
                                            type="number"
                                            name="statuses[{{ $status['slug'] }}][sort_order]"
                                            value="{{ old('statuses.'.$status['slug'].'.sort_order', $status['sort_order'] ?? 0) }}"
                                            min="0"
                                            max="999"
                                            class="form-control"
                                        >
                                    </div>
                                @endunless
                                <div class="col-8 col-sm-4 col-xl-3">
                                    <label for="{{ $statusField }}-mileage" class="form-label">Mileage required</label>
                                    <select id="{{ $statusField }}-mileage" name="statuses[{{ $status['slug'] }}][mileage_requirement]" class="form-select">
                                        <option value="none" @selected($mileageRequirement === 'none')>None</option>
                                        <option value="in" @selected($mileageRequirement === 'in')>Mileage in</option>
                                        <option value="out" @selected($mileageRequirement === 'out')>Mileage out</option>
                                        <option value="both" @selected($mileageRequirement === 'both')>Both</option>
                                    </select>
                                </div>
                                <div class="col-12 col-xl">
                                    <div class="d-flex flex-wrap gap-3 pb-2">
                                        @unless ($status['is_terminal'])
                                            <label class="form-check mb-0">
                                                <input type="hidden" name="statuses[{{ $status['slug'] }}][show_on_advisor_board]" value="0">
                                                <input
                                                    type="checkbox"
                                                    name="statuses[{{ $status['slug'] }}][show_on_advisor_board]"
                                                    value="1"
                                                    @checked(old('statuses.'.$status['slug'].'.show_on_advisor_board', $status['show_on_advisor_board']))
                                                    class="form-check-input"
                                                >
                                                <span class="form-check-label">Advisor board</span>
                                            </label>
                                            <label class="form-check mb-0">
                                                <input type="hidden" name="statuses[{{ $status['slug'] }}][show_on_technician_board]" value="0">
                                                <input
                                                    type="checkbox"
                                                    name="statuses[{{ $status['slug'] }}][show_on_technician_board]"
                                                    value="1"
                                                    @checked(old('statuses.'.$status['slug'].'.show_on_technician_board', $status['show_on_technician_board']))
                                                    class="form-check-input"
                                                >
                                                <span class="form-check-label">Technician board</span>
                                            </label>
                                        @endunless
                                        @if (! ($status['is_system'] ?? true))
                                            <label class="form-check mb-0">
                                                <input type="hidden" name="statuses[{{ $status['slug'] }}][is_terminal]" value="0">
                                                <input
                                                    type="checkbox"
                                                    name="statuses[{{ $status['slug'] }}][is_terminal]"
                                                    value="1"
                                                    @checked(old('statuses.'.$status['slug'].'.is_terminal', $status['is_terminal']))
                                                    class="form-check-input"
                                                >
                                                <span class="form-check-label">Hide from Job Board</span>
                                            </label>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            @if ($status['variants'] !== [])
                                <div class="row g-2 border-top pt-2 mt-2">
                                    @foreach ($status['variants'] as $variant)
                                        <div class="col-12 col-sm-6 col-xl-3">
                                            <label for="status-variant-{{ $variant['id'] }}" class="form-label">Close - {{ $variant['key'] }}</label>
                                            <input
                                                id="status-variant-{{ $variant['id'] }}"
                                                type="text"
                                                name="variants[{{ $variant['id'] }}][name]"
                                                value="{{ old('variants.'.$variant['id'].'.name', $variant['name']) }}"
                                                maxlength="64"
                                                class="form-control"
                                            >
                                            @if ($variant['bypass_standard_close_rules'])
                                                <div class="form-hint text-warning">Skips the usual close checks</div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        @if ($status['transitions'] !== [])
                            <details class="card-footer p-0">
                                <summary class="px-3 py-2 small fw-medium" style="cursor: pointer">
                                    Who can move this
                                    <span class="badge bg-secondary-lt ms-1">{{ $allowedMoves }}</span>
                                </summary>
                                <div class="table-responsive">
                                    <table class="table table-sm table-vcenter card-table mb-0">
                                        <thead>
                                            <tr>
                                                <th>Move to</th>
                                                @foreach ($transitionRoles as $role)
                                                    <th class="text-center">{{ $roleLabels[$role] ?? $role }}</th>
                                                @endforeach
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($status['transitions'] as $transition)
                                                @php
                                                    $transitionKey = $transition['form_key'];
                                                    $oldRoles = old('transitions.'.$transitionKey.'.roles', $transition['roles']);
                                                @endphp
                                                <tr @class(['text-secondary' => $oldRoles === []])>
                                                    <td class="fw-medium">{{ $transition['to_name'] }}</td>
                                                    @foreach ($transitionRoles as $role)
                                                        <td class="text-center">
                                                            <input
                                                                type="checkbox"
                                                                name="transitions[{{ $transitionKey }}][roles][]"
                                                                value="{{ $role }}"
                                                                @checked(in_array($role, $oldRoles, true))
                                                                class="form-check-input m-0"
                                                                aria-label="{{ ($roleLabels[$role] ?? $role).' can move '.$status['name'].' to '.$transition['to_name'] }}"
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
            </section>
        @endforeach

        <div class="d-flex justify-content-end border-top pt-3">
            <button type="submit" class="btn btn-primary">Save status catalog</button>
        </div>
    </form>
@endif
