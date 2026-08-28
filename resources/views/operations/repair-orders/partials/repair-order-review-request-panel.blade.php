@php
    use App\Ark\Operations\Messaging\ReviewRequestProjection;

    $reviewRequest = $reviewRequest ?? app(ReviewRequestProjection::class)->for($repairOrder);
    $estimateVersion = $estimateVersion ?? $repairOrder->estimate_version;
    $closePaid = (bool) ($closePaid ?? false);
    $modal = (bool) ($modal ?? false);
@endphp

@if ($reviewRequest['already_sent'] && ($reviewRequest['history_entries'] !== [] || filled($reviewRequest['status_label'])))
    <div class="space-y-1" data-review-request-status>
        <p class="text-[11px] font-semibold leading-4 text-slate-900">Review Requested</p>
        @foreach ($reviewRequest['history_entries'] as $entry)
            <p class="text-[11px] leading-4 text-slate-700">
                {{ $entry['channel_label'] }} · {{ $entry['when_label'] }}
            </p>
        @endforeach
        @if (filled($reviewRequest['sent_by_label']))
            <p class="text-[11px] leading-4 text-slate-600">by {{ $reviewRequest['sent_by_label'] }}</p>
        @endif
    </div>
    @if ($closePaid)
        <div class="mt-3 flex flex-wrap items-center gap-2">
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
                <button type="submit" class="{{ $modal ? 'ops-workspace-modal__primary' : 'rounded-sm bg-emerald-900 px-2 py-1 text-xs font-bold text-white' }}">
                    Close paid
                </button>
            </form>
        </div>
    @endif
@elseif (! $reviewRequest['can_text'] && ! $reviewRequest['can_email'])
    <p class="text-sm leading-5 text-slate-700">{{ $reviewRequest['no_contact_message'] }}</p>
    @if ($closePaid)
        <div class="mt-4 flex flex-wrap items-center gap-2">
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
                <button type="submit" class="{{ $modal ? 'ops-workspace-modal__primary' : 'rounded-sm bg-emerald-900 px-2 py-1 text-xs font-bold text-white' }}">
                    Close paid
                </button>
            </form>
        </div>
    @endif
@else
    @if ($errors->has('review_request'))
        <p class="mb-3 text-sm font-semibold leading-5 text-rose-800" data-review-request-error>{{ $errors->first('review_request') }}</p>
    @endif

    <div class="flex flex-wrap items-center gap-2" data-review-request-actions>
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
                <button type="submit" class="{{ $modal ? 'ops-workspace-modal__primary' : 'rounded-sm bg-emerald-900 px-2 py-1 text-xs font-bold text-white' }}">
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
                @if ($closePaid)
                    <input type="hidden" name="close_paid" value="1">
                @endif
                <button type="submit" class="{{ $modal ? 'ops-workspace-modal__cancel border border-slate-300' : 'rounded-sm border border-emerald-700 bg-white px-2 py-1 text-xs font-bold text-emerald-950' }}">
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
                @if ($closePaid)
                    <input type="hidden" name="close_paid" value="1">
                @endif
                <button type="submit" class="{{ $modal ? 'ops-workspace-modal__cancel border border-slate-300' : 'rounded-sm border border-emerald-700 bg-white px-2 py-1 text-xs font-bold text-emerald-950' }}">
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
                <button type="submit" class="text-sm font-semibold text-slate-700 underline-offset-2 hover:underline">
                    Not Now
                </button>
            </form>
        @endif
    </div>

    <details class="mt-4" data-review-request-preview>
        <summary class="cursor-pointer text-sm font-semibold leading-5 text-slate-800 underline-offset-2 hover:underline">
            Preview
        </summary>
        <div class="mt-2 space-y-3 rounded-md border border-slate-200 bg-slate-50 p-3 text-sm leading-5 text-slate-900">
            @if ($reviewRequest['can_text'])
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Text</p>
                    <p class="mt-1 whitespace-pre-wrap text-slate-800">{{ $reviewRequest['preview_sms_body'] }}</p>
                </div>
            @endif
            @if ($reviewRequest['can_email'])
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Email</p>
                    <p class="mt-1 font-medium text-slate-700">Subject: {{ $reviewRequest['preview_email_subject'] }}</p>
                    <p class="mt-1 whitespace-pre-wrap text-slate-800">{{ $reviewRequest['preview_email_body'] }}</p>
                </div>
            @endif
        </div>
    </details>
@endif
