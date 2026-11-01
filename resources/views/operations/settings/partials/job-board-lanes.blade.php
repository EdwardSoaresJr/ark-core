@php
    use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusColor;

    $laneColorOptions = RepairOrderStatusColor::options();
    $jobBoardLanes = $jobBoardLanes ?? [];
@endphp

<form method="POST" action="{{ route('operations.settings.shop.job-board-lanes.update') }}" data-settings-job-board-lanes>
    @csrf
    @method('PATCH')

    <div class="card card-sm mb-3">
        <div class="card-header">
            <div>
                <h4 class="card-title">Job Board lanes</h4>
                <div class="card-subtitle">These are the shop queues on the Job Board. Rename, reorder, and recolor them. Lane color is visual identity - it is not ARK attention.</div>
            </div>
        </div>
        <div class="list-group list-group-flush">
            @foreach ($jobBoardLanes as $lane)
                <div class="list-group-item">
                    <div class="row g-2 align-items-end">
                        <div class="col-12 col-sm-6 col-xl-5">
                            <label for="lane-{{ $lane['key'] }}-name" class="form-label">Name</label>
                            <input
                                id="lane-{{ $lane['key'] }}-name"
                                type="text"
                                name="lanes[{{ $lane['key'] }}][name]"
                                value="{{ old('lanes.'.$lane['key'].'.name', $lane['name']) }}"
                                maxlength="64"
                                class="form-control fw-medium"
                            >
                            <div class="form-hint font-monospace">{{ $lane['key'] }}</div>
                        </div>
                        <div class="col-12 col-sm-6 col-xl-3">
                            <div id="lane-{{ $lane['key'] }}-color" class="form-label">Color</div>
                            @include('operations.settings.partials.ro-status-color-picker', [
                                'name' => 'lanes['.$lane['key'].'][color]',
                                'value' => old('lanes.'.$lane['key'].'.color', $lane['color']),
                                'options' => $laneColorOptions,
                                'labelledBy' => 'lane-'.$lane['key'].'-color',
                            ])
                        </div>
                        <div class="col-4 col-sm-3 col-xl-1">
                            <label for="lane-{{ $lane['key'] }}-order" class="form-label">Order</label>
                            <input
                                id="lane-{{ $lane['key'] }}-order"
                                type="number"
                                name="lanes[{{ $lane['key'] }}][sort_order]"
                                value="{{ old('lanes.'.$lane['key'].'.sort_order', $lane['sort_order']) }}"
                                min="0"
                                max="999"
                                class="form-control"
                            >
                        </div>
                        <div class="col-8 col-sm-9 col-xl-3">
                            <label class="form-check mb-2">
                                <input type="hidden" name="lanes[{{ $lane['key'] }}][active]" value="0">
                                <input
                                    type="checkbox"
                                    name="lanes[{{ $lane['key'] }}][active]"
                                    value="1"
                                    @checked(old('lanes.'.$lane['key'].'.active', $lane['active']))
                                    class="form-check-input"
                                >
                                <span class="form-check-label">Show on Job Board</span>
                            </label>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="card card-sm">
        <div class="card-header">
            <div>
                <h4 class="card-title">Add lane</h4>
                <div class="card-subtitle">Add another shop queue, then assign statuses to it below.</div>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-5">
                    <label for="lane-create-name" class="form-label">Name</label>
                    <input
                        id="lane-create-name"
                        type="text"
                        name="create[name]"
                        value="{{ old('create.name') }}"
                        maxlength="64"
                        placeholder="e.g. Sublet"
                        @class(['form-control', 'is-invalid' => $errors->has('create.name')])
                    >
                </div>
                <div class="col-12 col-md-4">
                    <div id="lane-create-color" class="form-label">Color</div>
                    @include('operations.settings.partials.ro-status-color-picker', [
                        'name' => 'create[color]',
                        'value' => old('create.color', RepairOrderStatusColor::SECONDARY),
                        'options' => $laneColorOptions,
                        'labelledBy' => 'lane-create-color',
                    ])
                </div>
                <div class="col-12 col-md-3 d-grid d-md-flex justify-content-md-end">
                    <button type="submit" class="btn btn-primary">Save Job Board lanes</button>
                </div>
            </div>
            @error('create.name')
                <div class="text-danger small mt-2">{{ $message }}</div>
            @enderror
            @error('lanes')
                <div class="text-danger small mt-2">{{ $message }}</div>
            @enderror
        </div>
    </div>
</form>
