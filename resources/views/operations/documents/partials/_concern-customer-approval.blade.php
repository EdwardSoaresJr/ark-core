@php
    $isPdf = ($variant ?? 'browser') === 'pdf';
    $dispositionEnum = \App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition::fromStored((string) ($disposition ?? ''));
    $disposition = $dispositionEnum?->value ?? '';
    $approveSelected = $disposition === 'approved';
    $deferSelected = $disposition === 'deferred';
    $declineSelected = $disposition === 'declined';
    $decisionRecorded = in_array($disposition, ['approved', 'deferred', 'declined'], true);
    $showCustomerDecision = ($snapshot['document_type'] ?? 'estimate') === 'estimate'
        && (! $isPdf || ! $decisionRecorded);
    $boxClass = $isPdf ? 'concern-approval-box' : 'inline-flex h-3.5 w-3.5 shrink-0 items-center justify-center border border-slate-600 align-middle text-[10px] font-black leading-none';
    $pdfApproveCheckedClass = ' concern-approval-box--approve';
    $pdfDeferCheckedClass = ' concern-approval-box--defer';
    $pdfDeclineCheckedClass = ' concern-approval-box--decline';
    $browserCheckedClass = ' bg-slate-900 text-white';
@endphp

@if ($showCustomerDecision)
    <div class="{{ $isPdf ? 'concern-customer-approval' : 'border-t border-slate-200 bg-slate-50 px-3 py-2' }}">
        <div class="{{ $isPdf ? 'concern-customer-approval-row' : 'flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-700' }}">
            <span class="{{ $isPdf ? 'concern-approval-label' : 'font-bold uppercase tracking-wide text-slate-500' }}">Customer decision</span>
            <span class="{{ $isPdf ? 'concern-approval-option' : 'inline-flex items-center gap-1.5' }}">
                <span class="{{ $boxClass }}{{ $approveSelected ? ($isPdf ? $pdfApproveCheckedClass : $browserCheckedClass) : ($isPdf ? '' : ' bg-white') }}">{{ $approveSelected ? '✓' : '' }}</span> Approve
            </span>
            <span class="{{ $isPdf ? 'concern-approval-option' : 'inline-flex items-center gap-1.5' }}">
                <span class="{{ $boxClass }}{{ $deferSelected ? ($isPdf ? $pdfDeferCheckedClass : $browserCheckedClass) : ($isPdf ? '' : ' bg-white') }}">{{ $deferSelected ? '✓' : '' }}</span> Defer
            </span>
            <span class="{{ $isPdf ? 'concern-approval-option' : 'inline-flex items-center gap-1.5' }}">
                <span class="{{ $boxClass }}{{ $declineSelected ? ($isPdf ? $pdfDeclineCheckedClass : $browserCheckedClass) : ($isPdf ? '' : ' bg-white') }}">{{ $declineSelected ? '✓' : '' }}</span> Decline
            </span>
        </div>
    </div>
@endif
