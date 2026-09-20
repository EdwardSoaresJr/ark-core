@php
    /** @var array<string, mixed> $workspace */
    $unknownMacProbeUrl = url('/provision/AABBCCDDEEFF.cfg');
@endphp

<section id="certification" class="scroll-mt-4 space-y-4 rounded-sm border border-emerald-200 bg-emerald-50/40 p-3">
    <header class="space-y-1">
        <p class="text-[10px] font-bold uppercase tracking-wide text-emerald-800">Certification · G1–G7</p>
        <p class="text-xs leading-5 text-slate-700">
            Factory-reset this phone, follow each gate in order, and record timestamps. Name failed gates (e.g. Gate G5), not subsystems.
        </p>
    </header>

    <dl class="grid gap-2 rounded-sm border border-emerald-100 bg-white p-3 text-sm sm:grid-cols-2">
        <div>
            <dt class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Device</dt>
            <dd class="font-black text-slate-950">{{ $workspace['device']->name }}</dd>
        </div>
        <div>
            <dt class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Station</dt>
            <dd class="font-semibold text-slate-950">{{ $workspace['workstation_name'] ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-[10px] font-bold uppercase tracking-wide text-slate-400">MAC</dt>
            <dd class="font-mono font-semibold text-slate-950">{{ $workspace['mac_address_display'] ?? '—' }}</dd>
        </div>
        @if ($workspace['provision_url'])
            <div class="sm:col-span-2">
                <dt class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Bench test URL (G4)</dt>
                <dd>
                    <code class="mt-1 block break-all rounded-sm bg-slate-100 px-2 py-1.5 text-xs font-semibold text-slate-900">{{ $workspace['provision_url'] }}</code>
                </dd>
            </div>
        @endif
        @if ($workspace['provisioning_host'])
            <div class="sm:col-span-2">
                <dt class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Voice host</dt>
                <dd class="font-mono text-xs font-semibold text-slate-900">{{ $workspace['provisioning_host'] }}</dd>
            </div>
        @endif
    </dl>

    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-emerald-200 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">
                <th class="pb-2 pr-3 font-bold">Gate</th>
                <th class="pb-2 pr-3 font-bold">Question</th>
                <th class="pb-2 font-bold">Expected</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-emerald-100">
            <tr>
                <td class="py-2 pr-3 font-black text-slate-950">G1</td>
                <td class="py-2 pr-3 text-slate-800">Is production healthy?</td>
                <td class="py-2 text-slate-700">
                    <a href="{{ url('/up') }}" target="_blank" rel="noopener" class="font-semibold text-sky-700 hover:underline">/up</a> = 200
                </td>
            </tr>
            <tr>
                <td class="py-2 pr-3 font-black text-slate-950">G2</td>
                <td class="py-2 pr-3 text-slate-800">Is the schema current?</td>
                <td class="py-2 text-slate-700">Migrations applied; device models seeded</td>
            </tr>
            <tr>
                <td class="py-2 pr-3 font-black text-slate-950">G3</td>
                <td class="py-2 pr-3 text-slate-800">Is provisioning alive?</td>
                <td class="py-2 text-slate-700">
                    Unknown MAC → <strong>404</strong> (not 500)
                    <code class="mt-1 block break-all rounded-sm bg-white px-2 py-1 text-[11px] font-semibold text-slate-800">{{ $unknownMacProbeUrl }}</code>
                </td>
            </tr>
            <tr>
                <td class="py-2 pr-3 font-black text-slate-950">G4</td>
                <td class="py-2 pr-3 text-slate-800">Do provisioning gates work?</td>
                <td class="py-2 text-slate-700">
                    This device configured → GET provision URL → <strong>200</strong> + Poly body
                </td>
            </tr>
            <tr>
                <td class="py-2 pr-3 font-black text-slate-950">G5</td>
                <td class="py-2 pr-3 text-slate-800">Does the phone consume configuration?</td>
                <td class="py-2 text-slate-700">
                    Factory reset · disable Poly ZTP · custom server URL above
                    (<code class="break-all text-[11px]">{{ $workspace['provisioning_server_url'] }}</code>,
                    {{ strtolower($workspace['provisioning_server_scheme']) }}, no server user/pass)
                </td>
            </tr>
            <tr>
                <td class="py-2 pr-3 font-black text-slate-950">G6</td>
                <td class="py-2 pr-3 text-slate-800">Does the device register?</td>
                <td class="py-2 text-slate-700">Voice host shows endpoint registered</td>
            </tr>
            <tr>
                <td class="py-2 pr-3 font-black text-slate-950">G7</td>
                <td class="py-2 pr-3 text-slate-800">Does ARK observe reality?</td>
                <td class="py-2 font-semibold text-emerald-800">Device status → Connected</td>
            </tr>
        </tbody>
    </table>

    <p class="text-[11px] leading-4 text-slate-600">
        Record gate timestamps on the floor. Full checklist:
        <span class="font-semibold text-slate-800">docs/communications/first-contact-floor-checklist.md</span>
    </p>
</section>
