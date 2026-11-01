@php
    /** @var array<string, mixed> $shop */
    $activeStep = $shop['active_setup_step'];
@endphp

@include('operations.shop.communications.partials.voice-setup-steps', ['shop' => $shop])

@if ($activeStep === 'workstation')
    <section class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">Step 1 · Name a station</h2>
                <div class="card-subtitle">Where does work happen? Front Counter, Service Desk, Bay 1.</div>
            </div>
        </div>

        <form method="POST" action="{{ route('operations.shop.workstations.store') }}">
            @csrf
            <div class="card-body p-2 p-md-3">
                <div class="mb-3">
                    <label class="form-label" for="shop-station-name">Station name</label>
                    <input
                        id="shop-station-name"
                        type="text"
                        name="name"
                        value="{{ old('name') }}"
                        required
                        autofocus
                        placeholder="Front Counter"
                        @class(['form-control', 'is-invalid' => $errors->has('name')])
                    >
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div>
                    <label class="form-label" for="shop-station-location">Location <span class="text-secondary fw-normal">(optional)</span></label>
                    <input
                        id="shop-station-location"
                        type="text"
                        name="location_label"
                        value="{{ old('location_label') }}"
                        placeholder="Front desk"
                        class="form-control"
                    >
                </div>
            </div>
            <div class="card-footer d-grid d-sm-flex justify-content-sm-end">
                <button type="submit" class="btn btn-primary">Add station</button>
            </div>
        </form>
    </section>
@elseif ($activeStep === 'phone')
    @include('operations.shop.communications.partials.connect-device-panel', ['shop' => $shop])
@endif

@if ($shop['workstation_count'] > 0)
    @include('operations.shop.communications.partials.stations-list', ['shop' => $shop])
@endif
