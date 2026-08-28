@if ($vehicleRecordsLink ?? null)
    <div class="mt-6 rounded-md border border-slate-200 bg-slate-50 px-4 py-3">
        <p class="text-sm font-semibold text-slate-950">{{ $vehicleRecordsLink['label'] }}</p>
        <p class="mt-1 text-sm leading-6 text-slate-600">
            Past visits, invoices, and estimates for {{ $vehicleName ?? 'this vehicle' }}.
        </p>
        <a
            href="{{ $vehicleRecordsLink['url'] }}"
            class="mt-3 inline-flex text-sm font-semibold text-slate-900 underline decoration-slate-300 underline-offset-2 hover:text-slate-950"
        >
            {{ $vehicleRecordsLink['authenticated'] ? 'Open vehicle records' : 'Sign in to view records' }}
        </a>
    </div>
@endif
