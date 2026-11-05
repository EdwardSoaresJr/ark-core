@php
    use App\Ark\Operations\Booking\BookingLocationMode;
    use App\Ark\Operations\Appointments\AppointmentSlotMinutes;

    /** @var \App\Ark\Operations\Booking\BookingService|null $service */
    $key = $service?->id ?? 'new';
    $options = $service?->optionList() ?? [];
    $optionRows = array_pad($options, count($options) + 3, ['label' => '', 'duration_minutes' => null, 'requires_details' => false]);
    $durations = [15, 30, 45, 60, 90, 120, 180, 240, 300, 360, 480];
@endphp

<form method="POST" action="{{ $action }}" data-booking-service-form="{{ $key }}">
    @csrf
    @if ($method !== 'POST')
        @method($method)
        <input type="hidden" name="edit_service_id" value="{{ $service->id }}">
    @endif
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label required" for="service-{{ $key }}-name">Name</label>
            <input id="service-{{ $key }}-name" name="name" required maxlength="120" class="form-control" value="{{ $service?->name }}" placeholder="Brake inspection">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="service-{{ $key }}-duration">Visit length</label>
            <select id="service-{{ $key }}-duration" name="duration_minutes" class="form-select">
                @foreach ($durations as $minutes)
                    <option value="{{ $minutes }}" @selected((int) ($service?->duration_minutes ?? 60) === $minutes)>{{ AppointmentSlotMinutes::durationLabel($minutes) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="service-{{ $key }}-labor">Labor hours <span class="text-secondary fw-normal">(optional)</span></label>
            <input id="service-{{ $key }}-labor" type="number" step="0.25" min="0.25" max="24" name="labor_hours" class="form-control" value="{{ $service?->labor_hours }}" placeholder="Visit length">
        </div>
        <div class="col-12">
            <label class="form-label" for="service-{{ $key }}-description">Description customers see</label>
            <input id="service-{{ $key }}-description" name="description" maxlength="500" class="form-control" value="{{ $service?->description }}">
        </div>
        <div class="col-12">
            <label class="form-label" for="service-{{ $key }}-prompt">Hint in the description box <span class="text-secondary fw-normal">(optional)</span></label>
            <input id="service-{{ $key }}-prompt" name="concern_prompt" maxlength="500" class="form-control" value="{{ $service?->concern_prompt }}" placeholder="Grinding, squealing, pulsing pedal…">
        </div>
        <div class="col-md-6">
            <label class="form-label" for="service-{{ $key }}-location">Where the work happens</label>
            <select id="service-{{ $key }}-location" name="location_mode" class="form-select">
                @foreach (BookingLocationMode::cases() as $mode)
                    <option value="{{ $mode->value }}" @selected(($service?->locationMode() ?? BookingLocationMode::Shop) === $mode)>{{ $mode->label() }}</option>
                @endforeach
            </select>
            <div class="form-hint">At the customer's location asks for the service address.</div>
        </div>
        <div class="col-md-6">
            <label class="form-check mt-md-4">
                <input type="hidden" name="is_public" value="0">
                <input type="checkbox" name="is_public" value="1" class="form-check-input" @checked($service?->is_public ?? true)>
                <span class="form-check-label">Offer online</span>
            </label>
            <label class="form-check">
                <input type="hidden" name="requires_approval" value="0">
                <input type="checkbox" name="requires_approval" value="1" class="form-check-input" @checked($service?->requires_approval ?? false)>
                <span class="form-check-label">Always needs shop approval</span>
                <span class="form-check-description">Stays a request even when the form takes confirmed bookings.</span>
            </label>
        </div>
    </div>

    <fieldset class="mt-3">
        <legend class="form-label">Options <span class="text-secondary fw-normal">(optional, for example Front / Rear / Both)</span></legend>
        <div class="table-responsive">
            <table class="table table-sm table-vcenter mb-0">
                <thead>
                    <tr><th>Option</th><th style="width: 11rem">Visit length</th><th style="width: 9rem">Details required</th></tr>
                </thead>
                <tbody>
                    @foreach ($optionRows as $i => $option)
                        <tr>
                            <td><input name="options[{{ $i }}][label]" maxlength="120" class="form-control form-control-sm" value="{{ $option['label'] }}" aria-label="Option {{ $i + 1 }}"></td>
                            <td>
                                <select name="options[{{ $i }}][duration_minutes]" class="form-select form-select-sm" aria-label="Option {{ $i + 1 }} visit length">
                                    <option value="">Same as service</option>
                                    @foreach ($durations as $minutes)
                                        <option value="{{ $minutes }}" @selected((int) ($option['duration_minutes'] ?? 0) === $minutes)>{{ AppointmentSlotMinutes::durationLabel($minutes) }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <input type="hidden" name="options[{{ $i }}][requires_details]" value="0">
                                <input type="checkbox" name="options[{{ $i }}][requires_details]" value="1" class="form-check-input" @checked($option['requires_details']) aria-label="Option {{ $i + 1 }} requires details">
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </fieldset>

    <div class="d-flex justify-content-end mt-3">
        <button type="submit" class="btn btn-primary">{{ $submit }}</button>
    </div>
</form>
