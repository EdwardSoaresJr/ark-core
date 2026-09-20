@php
    /** @var array<string, mixed> $workspace */
@endphp

<section class="space-y-3 rounded-sm border border-sky-200 bg-sky-50/50 p-3">
    <header class="space-y-1">
        <p class="text-[10px] font-bold uppercase tracking-wide text-sky-800">Connect this device</p>
        <p class="text-xs leading-5 text-slate-700">
            After factory reset, enter this provisioning server on the phone. Credentials download automatically — nothing else to type on the device.
        </p>
    </header>

    <dl class="space-y-2 text-sm">
        <div>
            <dt class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Provisioning server</dt>
            <dd>
                <code class="mt-1 block break-all rounded-sm bg-white px-2 py-1.5 text-xs font-semibold text-slate-900">{{ $workspace['provisioning_server_url'] }}</code>
            </dd>
        </div>
        <div class="grid gap-2 sm:grid-cols-3">
            <div>
                <dt class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Protocol</dt>
                <dd class="font-semibold text-slate-950">{{ $workspace['provisioning_server_scheme'] }}</dd>
            </div>
            <div>
                <dt class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Server user</dt>
                <dd class="font-semibold text-slate-600">Leave blank</dd>
            </div>
            <div>
                <dt class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Server password</dt>
                <dd class="font-semibold text-slate-600">Leave blank</dd>
            </div>
        </div>
    </dl>

    @if ($workspace['provision_url'] && auth()->user()?->isMasterAdmin())
        <div class="border-t border-sky-100 pt-2">
            <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Bench verification URL</p>
            <code class="mt-1 block break-all rounded-sm bg-white px-2 py-1.5 text-xs font-semibold text-slate-800">{{ $workspace['provision_url'] }}</code>
        </div>
    @endif
</section>
