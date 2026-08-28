<x-operations.app title="Growth Sessions">
    <section class="space-y-3">
        <div class="border border-slate-300 bg-white">
            <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
                <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">Public Surface Intelligence</p>
                <h1 class="mt-0.5 text-lg font-black text-slate-950">Landing sessions</h1>
                <p class="mt-1 text-xs text-slate-500">Debug spine: visitor → touchpoints → lead → repair order. Read-only.</p>
            </div>
            @if ($sessions->isEmpty())
                <div class="px-3 py-6 text-sm text-slate-600">No landing sessions recorded yet. Public page views will appear here.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-xs">
                        <thead class="border-b border-slate-200 bg-slate-50 text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">
                            <tr>
                                <th class="px-3 py-2">Started</th>
                                <th class="px-3 py-2">First landing</th>
                                <th class="px-3 py-2">Search</th>
                                <th class="px-3 py-2">Device</th>
                                <th class="px-3 py-2">Touchpoints</th>
                                <th class="px-3 py-2">Leads</th>
                                <th class="px-3 py-2">ROs</th>
                                <th class="px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($sessions as $session)
                                <tr>
                                    <td class="px-3 py-2 tabular-nums text-slate-800">{{ $session->started_at?->format('M j g:ia') }}</td>
                                    <td class="max-w-[12rem] truncate px-3 py-2 text-slate-800" title="{{ $session->first_landing_page }}">{{ $session->first_landing_page ?: '—' }}</td>
                                    <td class="max-w-[10rem] truncate px-3 py-2 text-slate-600" title="{{ $session->first_search_query }}">{{ $session->first_search_query ?: '—' }}</td>
                                    <td class="px-3 py-2 text-slate-600">{{ $session->device ?: '—' }}</td>
                                    <td class="px-3 py-2 tabular-nums text-slate-800">{{ $session->touchpoints_count }}</td>
                                    <td class="px-3 py-2 tabular-nums text-slate-800">{{ $session->leads_count }}</td>
                                    <td class="px-3 py-2 tabular-nums text-slate-800">{{ $session->repair_orders_count }}</td>
                                    <td class="px-3 py-2">
                                        <a href="{{ route('growth.sessions.show', $session) }}" class="font-bold text-sky-700 hover:text-sky-900">View</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <a href="{{ route('growth.opportunities.index') }}" class="text-xs font-bold text-slate-600 hover:text-slate-900">← Opportunities</a>
    </section>
</x-operations.app>
