<x-operations.app title="Growth Session">
    <section class="space-y-3">
        <div class="border border-slate-300 bg-white">
            <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
                <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">Session #{{ $session->id }}</p>
                <h1 class="mt-0.5 text-lg font-black text-slate-950">{{ $session->first_landing_page ?: 'Unknown landing' }}</h1>
                <p class="mt-1 text-xs text-slate-500">
                    Started {{ $session->started_at?->format('M j, Y g:ia') }} · Visitor {{ \Illuminate\Support\Str::limit($session->visitor_id, 8, '') }}
                </p>
            </div>
            <dl class="grid gap-px border-b border-slate-200 bg-slate-200 sm:grid-cols-2 lg:grid-cols-4">
                <div class="bg-white px-3 py-2">
                    <dt class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">First touch</dt>
                    <dd class="mt-1 text-xs text-slate-800">{{ $session->first_search_query ?: '—' }}</dd>
                </div>
                <div class="bg-white px-3 py-2">
                    <dt class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">Campaign</dt>
                    <dd class="mt-1 text-xs text-slate-800">{{ $session->first_campaign ?: $session->utm_campaign ?: '—' }}</dd>
                </div>
                <div class="bg-white px-3 py-2">
                    <dt class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">Content</dt>
                    <dd class="mt-1 text-xs text-slate-800">{{ $session->firstContent?->title ?: '—' }}</dd>
                </div>
                <div class="bg-white px-3 py-2">
                    <dt class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">Last page</dt>
                    <dd class="mt-1 text-xs text-slate-800">{{ $session->lastTouch?->landing_page ?: '—' }}</dd>
                </div>
            </dl>
        </div>

        <div class="grid gap-3 lg:grid-cols-2">
            <div class="border border-slate-300 bg-white">
                <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
                    <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Touchpoints ({{ $session->touchpoints->count() }})</p>
                </div>
                <ul class="divide-y divide-slate-100">
                    @forelse ($session->touchpoints as $touchpoint)
                        <li class="px-3 py-2">
                            <div class="flex items-baseline justify-between gap-2">
                                <p class="text-sm font-bold text-slate-950">{{ $touchpoint->type->label() }}</p>
                                <span class="text-[11px] tabular-nums text-slate-500">{{ $touchpoint->recorded_at?->format('M j g:ia') }}</span>
                            </div>
                            <p class="mt-0.5 text-xs text-slate-600">{{ $touchpoint->path ?: '—' }} @if($touchpoint->content) · {{ $touchpoint->content->title }} @endif</p>
                        </li>
                    @empty
                        <li class="px-3 py-4 text-xs text-slate-500">No touchpoints yet.</li>
                    @endforelse
                </ul>
            </div>

            <div class="space-y-3">
                <div class="border border-slate-300 bg-white">
                    <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Linked leads</p>
                    </div>
                    <ul class="divide-y divide-slate-100">
                        @forelse ($session->leads as $lead)
                            <li class="px-3 py-2 text-xs text-slate-800">#{{ $lead->id }} · {{ $lead->contact_name ?: 'Unknown' }} · {{ $lead->state?->value }}</li>
                        @empty
                            <li class="px-3 py-3 text-xs text-slate-500">No leads linked.</li>
                        @endforelse
                    </ul>
                </div>
                <div class="border border-slate-300 bg-white">
                    <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Linked repair orders</p>
                    </div>
                    <ul class="divide-y divide-slate-100">
                        @forelse ($session->repairOrders as $repairOrder)
                            <li class="px-3 py-2 text-xs text-slate-800">RO #{{ $repairOrder->repair_order_id }} · {{ $repairOrder->status?->value }}</li>
                        @empty
                            <li class="px-3 py-3 text-xs text-slate-500">No repair orders linked.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>

        <div class="flex gap-3 text-xs">
            <a href="{{ route('growth.sessions.index') }}" class="font-bold text-slate-600 hover:text-slate-900">← All sessions</a>
            <a href="{{ route('growth.opportunities.index') }}" class="font-bold text-slate-600 hover:text-slate-900">Opportunities</a>
        </div>
    </section>
</x-operations.app>
