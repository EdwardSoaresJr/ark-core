@php
    /** @var \App\Ark\Operations\Communications\CommunicationsShopWorkstationRow $workstation */
@endphp

<form
    method="POST"
    action="{{ route('operations.shop.workstations.extension.assign', $workstation->workstationId) }}"
    class="space-y-3"
>
    @csrf

    @error('extension')
        <p class="text-xs font-semibold text-rose-700">{{ $message }}</p>
    @enderror

    <p class="text-sm text-slate-700">
        Ring customer calls at <span class="font-semibold">{{ $workstation->name }}</span>.
        ARK provisions the desk phone after you confirm.
    </p>

    <div class="grid gap-3 sm:grid-cols-2">
        <label class="block">
            <span class="text-xs font-semibold text-slate-700">Phone line</span>
            <input
                type="text"
                name="extension"
                required
                inputmode="numeric"
                pattern="\d{2,4}"
                value="{{ old('extension', $workstation->extensionNumber ?? $workstation->suggestedExtension) }}"
                class="mt-1 h-10 w-full rounded-sm border-slate-300 font-mono text-sm"
            >
            <span class="mt-1 block text-[11px] text-slate-500">Internal line identity for Twilio SIP registration.</span>
        </label>
        <label class="block">
            <span class="text-xs font-semibold text-slate-700">Caller ID label</span>
            <input
                type="text"
                name="display_name"
                required
                value="{{ old('display_name', $workstation->extensionDisplayName ?? $workstation->name) }}"
                class="mt-1 h-10 w-full rounded-sm border-slate-300 text-sm"
            >
        </label>
    </div>

    <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-sm bg-slate-950 px-5 text-sm font-semibold text-white hover:bg-slate-800">
        Use at {{ $workstation->name }}
    </button>
</form>
