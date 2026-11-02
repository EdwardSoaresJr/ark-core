@php
    use App\Ark\Operations\Messaging\ReviewRequestProjection;

    $reviewRequest = $reviewRequest ?? app(ReviewRequestProjection::class)->for($repairOrder);
    $estimateVersion = $estimateVersion ?? $repairOrder->estimate_version;
    $closePaid = (bool) ($closePaid ?? false);
    $modal = (bool) ($modal ?? false);
    $primaryButtonClass = $modal ? 'btn btn-primary' : 'btn btn-sm btn-success';
    $secondaryButtonClass = $modal ? 'btn' : 'btn btn-sm btn-outline-success';
@endphp

@if ($reviewRequest['already_sent'] && ($reviewRequest['history_entries'] !== [] || filled($reviewRequest['status_label'])))
    <div class="small" data-review-request-status>
        <div class="fw-bold">Review Requested</div>
        @foreach ($reviewRequest['history_entries'] as $entry)
            <div class="text-secondary">
                {{ $entry['channel_label'] }} · {{ $entry['when_label'] }}
            </div>
        @endforeach
        @if (filled($reviewRequest['sent_by_label']))
            <div class="text-secondary">by {{ $reviewRequest['sent_by_label'] }}</div>
        @endif
    </div>
    @if ($closePaid)
        <div class="btn-list mt-3">
            <form
                method="POST"
                action="{{ route('operations.repair-orders.lifecycle.update', $repairOrder) }}"
                data-refresh-scope="toolbar"
                @if ($modal) data-workspace-modal-form="review-request" @endif
                @submit.prevent="submitWorksheetForm($event)"
            >
                @csrf
                @method('PATCH')
                <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
                <input type="hidden" name="status" value="closed:paid">
                <button type="submit" class="{{ $primaryButtonClass }}">
                    Close paid
                </button>
            </form>
        </div>
    @endif
@elseif (! $reviewRequest['can_text'] && ! $reviewRequest['can_email'])
    <p class="mb-0">{{ $reviewRequest['no_contact_message'] }}</p>
    @if ($closePaid)
        <div class="btn-list mt-3">
            <form
                method="POST"
                action="{{ route('operations.repair-orders.lifecycle.update', $repairOrder) }}"
                data-refresh-scope="toolbar"
                @if ($modal) data-workspace-modal-form="review-request" @endif
                @submit.prevent="submitWorksheetForm($event)"
            >
                @csrf
                @method('PATCH')
                <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
                <input type="hidden" name="status" value="closed:paid">
                <button type="submit" class="{{ $primaryButtonClass }}">
                    Close paid
                </button>
            </form>
        </div>
    @endif
@else
    @if ($errors->has('review_request'))
        <div class="alert alert-danger mb-3" data-review-request-error>{{ $errors->first('review_request') }}</div>
    @endif

    <div class="btn-list" data-review-request-actions>
        @if ($reviewRequest['can_text'])
            <form
                method="POST"
                action="{{ route('operations.repair-orders.review-request.send', $repairOrder) }}"
                data-refresh-scope="toolbar"
                @if ($modal) data-workspace-modal-form="review-request" @endif
                @submit.prevent="submitWorksheetForm($event)"
            >
                @csrf
                <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
                <input type="hidden" name="delivery" value="sms">
                @if ($closePaid)
                    <input type="hidden" name="close_paid" value="1">
                @endif
                <button type="submit" class="{{ $primaryButtonClass }}">
                    Text
                </button>
            </form>
        @endif

        @if ($reviewRequest['can_email'])
            <form
                method="POST"
                action="{{ route('operations.repair-orders.review-request.send', $repairOrder) }}"
                data-refresh-scope="toolbar"
                @if ($modal) data-workspace-modal-form="review-request" @endif
                @submit.prevent="submitWorksheetForm($event)"
            >
                @csrf
                <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
                <input type="hidden" name="delivery" value="email">
                @if (filled($reviewRequest['recipient_email'] ?? null))
                    <input type="hidden" name="email" value="{{ $reviewRequest['recipient_email'] }}">
                @endif
                @if ($closePaid)
                    <input type="hidden" name="close_paid" value="1">
                @endif
                <button type="submit" class="{{ $secondaryButtonClass }}">
                    Email
                </button>
            </form>
        @endif

        @if ($reviewRequest['can_text'] && $reviewRequest['can_email'])
            <form
                method="POST"
                action="{{ route('operations.repair-orders.review-request.send', $repairOrder) }}"
                data-refresh-scope="toolbar"
                @if ($modal) data-workspace-modal-form="review-request" @endif
                @submit.prevent="submitWorksheetForm($event)"
            >
                @csrf
                <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
                <input type="hidden" name="delivery" value="both">
                @if (filled($reviewRequest['recipient_email'] ?? null))
                    <input type="hidden" name="email" value="{{ $reviewRequest['recipient_email'] }}">
                @endif
                @if ($closePaid)
                    <input type="hidden" name="close_paid" value="1">
                @endif
                <button type="submit" class="{{ $secondaryButtonClass }}">
                    Text + Email
                </button>
            </form>
        @endif

        @if ($closePaid)
            <form
                method="POST"
                action="{{ route('operations.repair-orders.lifecycle.update', $repairOrder) }}"
                data-refresh-scope="toolbar"
                @if ($modal) data-workspace-modal-form="review-request" @endif
                @submit.prevent="submitWorksheetForm($event)"
            >
                @csrf
                @method('PATCH')
                <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
                <input type="hidden" name="status" value="closed:paid">
                <button type="submit" class="btn btn-ghost-secondary">
                    Not Now
                </button>
            </form>
        @endif
    </div>

    <details class="mt-3" data-review-request-preview>
        <summary class="fw-medium">
            Preview
        </summary>
        <div class="card card-sm mt-2">
            <div class="card-body">
                @if ($reviewRequest['can_text'])
                    <div @class(['mb-3' => $reviewRequest['can_email']])>
                        <div class="subheader mb-1">Text</div>
                        <div style="white-space: pre-wrap">{{ $reviewRequest['preview_sms_body'] }}</div>
                    </div>
                @endif
                @if ($reviewRequest['can_email'])
                    <div>
                        <div class="subheader mb-1">Email</div>
                        <div class="fw-medium text-secondary">Subject: {{ $reviewRequest['preview_email_subject'] }}</div>
                        <div class="mt-1" style="white-space: pre-wrap">{{ $reviewRequest['preview_email_body'] }}</div>
                    </div>
                @endif
            </div>
        </div>
    </details>
@endif
