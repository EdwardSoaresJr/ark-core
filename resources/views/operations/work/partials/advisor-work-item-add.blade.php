@props([
    'label',
    'storeRoute',
])

@php
    $defaultDue = now()->addDay()->setHour(9)->setMinute(0)->format('Y-m-d\TH:i');
@endphp

<details class="position-relative" data-work-item-add>
    <summary class="btn btn-primary">
        <x-tabler.icon name="plus" />
        {{ $label }}
    </summary>
    <div class="card position-absolute end-0 mt-1 shadow-lg z-3" style="width: min(24rem, calc(100vw - 2rem))">
        <div class="card-header">
            <h3 class="card-title">{{ $label }}</h3>
            <div class="card-actions">
                <button type="button" class="btn-close" data-work-item-quick-add-cancel aria-label="Cancel"></button>
            </div>
        </div>
        <form method="POST" action="{{ $storeRoute }}" class="card-body">
            @csrf
            <div class="mb-3">
                <label class="form-label visually-hidden" for="work-item-add-notes">Notes</label>
                <textarea id="work-item-add-notes" name="notes" rows="2" required maxlength="1000" placeholder="What needs to happen?" class="form-control"></textarea>
            </div>
            <div class="row g-2 mb-3">
                <div class="col-sm-6">
                    <label class="form-label" for="work-item-add-due">Remind on</label>
                    <input id="work-item-add-due" type="datetime-local" name="due_at" required value="{{ $defaultDue }}" class="form-control">
                </div>
                <div class="col-sm-6">
                    <label class="form-label" for="work-item-add-ro">RO # (optional)</label>
                    <input id="work-item-add-ro" type="number" name="repair_order_shop_number" min="1" placeholder="Shop RO #" class="form-control">
                </div>
            </div>
            <div class="btn-list justify-content-end">
                <button type="button" class="btn" data-work-item-quick-add-cancel>Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</details>
