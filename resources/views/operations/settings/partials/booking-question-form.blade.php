@php
    use App\Ark\Operations\Booking\BookingQuestionType;

    /** @var \App\Ark\Operations\Booking\BookingQuestion|null $question */
    $key = $question?->id ?? 'new';
    $selectedServices = $question?->serviceIdList() ?? [];
@endphp

<form method="POST" action="{{ $action }}" data-booking-question-form="{{ $key }}">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif
    <div class="row g-3">
        <div class="col-md-8">
            <label class="form-label required" for="question-{{ $key }}-label">Question</label>
            <input id="question-{{ $key }}-label" name="label" required maxlength="200" class="form-control" value="{{ $question?->label }}" placeholder="Is the vehicle under warranty?">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="question-{{ $key }}-type">Answer type</label>
            <select id="question-{{ $key }}-type" name="type" class="form-select">
                @foreach (BookingQuestionType::cases() as $type)
                    <option value="{{ $type->value }}" @selected(($question?->type ?? BookingQuestionType::Text) === $type)>{{ $type->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-8">
            <label class="form-label" for="question-{{ $key }}-help">Help text <span class="text-secondary fw-normal">(optional)</span></label>
            <input id="question-{{ $key }}-help" name="help" maxlength="500" class="form-control" value="{{ $question?->help }}">
        </div>
        <div class="col-md-4">
            <label class="form-check mt-md-4">
                <input type="hidden" name="required" value="0">
                <input type="checkbox" name="required" value="1" class="form-check-input" @checked($question?->required ?? false)>
                <span class="form-check-label">Required</span>
            </label>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="question-{{ $key }}-choices">Choices for "Pick one"</label>
            <textarea id="question-{{ $key }}-choices" name="choices" rows="3" class="form-control" placeholder="One per line">{{ implode("\n", $question?->choiceList() ?? []) }}</textarea>
        </div>
        <div class="col-md-6">
            <span class="form-label">Ask for</span>
            @if ($services->isEmpty())
                <p class="text-secondary small mb-0">Every booking. Add services to ask only for some.</p>
            @else
                <div class="form-hint mb-1">Leave all unchecked to ask for every service.</div>
                @foreach ($services as $service)
                    <label class="form-check">
                        <input type="checkbox" name="service_ids[]" value="{{ $service->id }}" class="form-check-input" @checked(in_array($service->id, $selectedServices, true))>
                        <span class="form-check-label">{{ $service->name }}</span>
                    </label>
                @endforeach
            @endif
        </div>
    </div>
    <div class="d-flex justify-content-end mt-3">
        <button type="submit" class="btn btn-primary">{{ $submit }}</button>
    </div>
</form>
