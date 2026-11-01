@php
    $defaultLaborCategory = $settings->defaultLaborCategory();
    $defaultVisitMode = $settings->default_visit_mode ?? \App\Ark\Operations\RepairOrders\RepairOrderVisitMode::DropOff->value;
@endphp

<form method="POST" action="{{ route('operations.settings.shop.workflow.update') }}">
    @csrf
    @method('PATCH')

    <div class="hr-text mt-0">Check In visit</div>
    <div class="row g-3">
        <div class="col-12 col-md-6">
            <label for="settings-default-visit-mode" class="form-label">Default visit type</label>
            <select id="settings-default-visit-mode" name="default_visit_mode" required class="form-select">
                @foreach (\App\Ark\Operations\RepairOrders\RepairOrderVisitMode::cases() as $mode)
                    <option value="{{ $mode->value }}" @selected(old('default_visit_mode', $defaultVisitMode) === $mode->value)>{{ $mode->label() }}</option>
                @endforeach
            </select>
            <div class="form-hint">Pre-selected when opening a repair order from Check In.</div>
        </div>
        <div class="col-12 col-md-6 small text-secondary">
            <div class="fw-medium text-body">Billing at Check In</div>
            <p class="mb-2">Fleet and warranty billing follow the billing class. Advisors can override per visit at Check In.</p>
            <p class="mb-2">Fees and parts matrices: <button type="button" class="btn btn-link p-0 align-baseline small" @click="setActive('financial'); setFinancialTab('billing-classes')">Financial Rules → Billing classes</button>.</p>
            <p class="mb-0">Whether a key tag needs mileage in before it prints: <button type="button" class="btn btn-link p-0 align-baseline small" @click="setActive('printing'); setPrintingTab('printers')">Label Printing → Printers &amp; labels</button>.</p>
        </div>
    </div>

    <div class="hr-text">Scopes &amp; concerns</div>
    <div class="row g-3">
        <div class="col-12 col-md-6">
            <label for="default_recommendation_posture" class="form-label">Default priority</label>
            @include('operations.repair-orders.partials.recommendation-priority-select', [
                'fieldName' => 'default_recommendation_posture',
                'selectedPriority' => old('default_recommendation_posture', $settings->default_recommendation_priority),
                'informationOnly' => (bool) old('default_information_only', $settings->defaultsToInformationOnly()),
                'inputId' => 'default_recommendation_posture',
                'selectClass' => 'form-select',
            ])
            <div class="form-hint">Customer-facing order and color for new scopes. Information Only clears priority.</div>
        </div>
        <div class="col-12 col-md-6">
            <label for="default_recommendation_intent" class="form-label">Default work type</label>
            @include('operations.repair-orders.partials.recommendation-intent-select', [
                'fieldName' => 'default_recommendation_intent',
                'selected' => old('default_recommendation_intent', $settings->default_recommendation_intent),
                'inputId' => 'default_recommendation_intent',
                'selectClass' => 'form-select',
            ])
            <div class="form-hint">Diagnostic, maintenance, or repair verification. This is not urgency.</div>
        </div>
        <div class="col-12 small text-secondary">
            <div class="fw-medium text-body">Default labor category</div>
            <p class="mb-2"><span class="fw-medium text-body">{{ $defaultLaborCategory['name'] }}</span> at ${{ number_format($defaultLaborCategory['rate_cents'] / 100, 2) }}/hr - <button type="button" class="btn btn-link p-0 align-baseline small" @click="setActive('financial'); setFinancialTab('labor')">Financial Rules → Labor</button>.</p>
            <p class="mb-0">RO lifecycle status is configured under <button type="button" class="btn btn-link p-0 align-baseline small" @click="setWorkflowTab('statuses')">RO statuses</button>.</p>
        </div>
    </div>

    <div class="hr-text">Worksheet notes</div>
    <div style="max-width: 32rem">
        @php
            $noteDefaults = \App\Ark\Operations\RepairOrders\NoteAudience::defaultsFromShop();
            $noteAudience = [
                'advisor' => old('default_notes_visible_to_advisor', $noteDefaults->advisor ? '1' : '0') === '1',
                'technician' => old('default_notes_visible_to_technician', $noteDefaults->technician ? '1' : '0') === '1',
                'customer' => old('default_notes_visible_to_customer', $noteDefaults->customer ? '1' : '0') === '1',
            ];
        @endphp
        @include('operations.repair-orders.partials.repair-order-note-privacy-field', [
            'audience' => $noteAudience,
            'inputId' => 'default-note-audience',
            'ui' => 'tabler',
            'fields' => [
                'advisor' => 'default_notes_visible_to_advisor',
                'technician' => 'default_notes_visible_to_technician',
                'customer' => 'default_notes_visible_to_customer',
            ],
        ])
        <div class="form-hint">New notes start with these views selected. The same choices are on each note.</div>
    </div>

    <div class="d-flex justify-content-end border-top pt-3 mt-4">
        <button type="submit" class="btn btn-primary">Save Check In defaults</button>
    </div>
</form>
