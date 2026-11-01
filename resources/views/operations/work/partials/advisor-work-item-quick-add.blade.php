@props([
    'kind',
    'storeRoute',
    'row',
])

@php
    $isFollowUp = $kind === 'follow-up';
    $summaryLabel = $isFollowUp ? 'Follow-up' : 'Task';
    $quickAddId = 'ops-decision-quick-add-'.$row['repair_order_shop_number'].'-'.$kind;
    $defaultDue = now()->addDay()->setHour(9)->setMinute(0)->format('Y-m-d\TH:i');
    $defaultNote = ($isFollowUp ? 'Follow up: ' : 'Task: ')
        .$row['customer_name']
        .' · RO #'.$row['repair_order_shop_number'];
@endphp

{{-- ark-work-quick-add.js moves the open panel into the row's panel slot and posts the form as JSON. --}}
<details id="{{ $quickAddId }}" data-decision-quick-add="{{ $kind }}">
    <summary class="btn btn-sm btn-ghost-secondary" data-work-item-quick-add-label="{{ $summaryLabel }}">{{ $summaryLabel }}</summary>
    <div class="card card-sm mt-2" data-decision-quick-add-owner="{{ $quickAddId }}">
        <div class="card-body">
            <div class="d-flex align-items-center gap-2 mb-2">
                <div class="small fw-medium text-truncate">
                    {{ $summaryLabel }} · {{ $row['customer_name'] }} · RO #{{ $row['repair_order_shop_number'] }}
                </div>
                <button
                    type="button"
                    class="btn-close ms-auto"
                    data-work-item-quick-add-cancel
                    aria-label="Cancel {{ strtolower($summaryLabel) }}"
                ></button>
            </div>
            <form
                method="POST"
                action="{{ $storeRoute }}"
                data-work-item-quick-add-form
                data-default-notes="{{ $defaultNote }}"
                data-default-due="{{ $defaultDue }}"
            >
                @csrf
                @if (! empty($row['customer_id']))
                    <input type="hidden" name="customer_id" value="{{ (int) $row['customer_id'] }}">
                @endif
                @if (! empty($row['vehicle_id']))
                    <input type="hidden" name="vehicle_id" value="{{ (int) $row['vehicle_id'] }}">
                @endif
                <input type="hidden" name="repair_order_shop_number" value="{{ (int) $row['repair_order_shop_number'] }}">
                <div class="mb-2">
                    <label class="form-label small mb-1" for="{{ $quickAddId }}-notes">What needs to happen?</label>
                    <textarea
                        id="{{ $quickAddId }}-notes"
                        name="notes"
                        rows="2"
                        required
                        maxlength="1000"
                        class="form-control form-control-sm"
                    >{{ $defaultNote }}</textarea>
                </div>
                <div class="mb-2">
                    <label class="form-label small mb-1" for="{{ $quickAddId }}-due">Remind on</label>
                    <input
                        id="{{ $quickAddId }}-due"
                        type="datetime-local"
                        name="due_at"
                        required
                        value="{{ $defaultDue }}"
                        class="form-control form-control-sm"
                    >
                </div>
                <div class="small fw-medium text-success mb-2 hidden" role="status" data-quick-add-feedback hidden></div>
                <div class="small fw-medium text-danger mb-2 hidden" role="alert" data-quick-add-error hidden></div>
                <div class="btn-list justify-content-end">
                    <button type="button" class="btn btn-sm" data-work-item-quick-add-cancel>Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">Save {{ strtolower($summaryLabel) }}</button>
                </div>
            </form>
        </div>
    </div>
</details>
