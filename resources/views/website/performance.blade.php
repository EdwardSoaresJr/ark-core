@php
    /** @var array<string, mixed> $performance */
@endphp

<x-operations.app title="Website">
    <section class="space-y-3">
        <div class="border border-slate-300 bg-white">
            <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
                <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">Website</p>
                <h1 class="mt-0.5 text-lg font-black text-slate-950">Performance</h1>
                <p class="mt-1 max-w-3xl text-xs text-slate-500">
                    Market pressure — selection, retention, and reputation. Website cards below show acquisition signals; open Growth for search opportunities.
                </p>
            </div>

            <div class="px-3 py-3">
                @include('website.partials.subnav', ['activeTab' => 'performance'])

                @if ($performance['publish_queue'])
                    <div class="mt-4 rounded-md border border-[#0099cc]/30 bg-[#0099cc]/5 px-3 py-3">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-[#007aa3]">Next website improvement</p>
                        <p class="mt-1 text-sm font-black text-slate-950">{{ $performance['publish_queue']['title'] }}</p>
                        <p class="mt-1 text-xs text-slate-600">
                            Estimated effort: {{ $performance['publish_queue']['effort'] }}
                            · {{ $performance['publish_queue']['status'] }}
                        </p>
                        <div class="mt-3 flex flex-wrap gap-3">
                            <a href="{{ $performance['publish_queue']['continue_url'] }}" class="inline-flex rounded-md bg-slate-950 px-3 py-1.5 text-xs font-semibold text-white no-underline hover:bg-slate-800">
                                Continue →
                            </a>
                            @can(App\Ark\Runtime\Authorization\ArkCapability::GrowthAccess->value)
                                <a href="{{ $performance['publish_queue']['growth_url'] }}" class="inline-flex items-center text-xs font-semibold text-[#0099cc] no-underline hover:text-[#0088b8]">
                                    Open in Growth →
                                </a>
                            @endcan
                        </div>
                    </div>
                @endif

                <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach ([
                        ['key' => 'leads', 'title' => 'Leads this week', 'value' => $performance['leads']['value'], 'subtitle' => $performance['leads']['subtitle'], 'url' => $performance['leads']['growth_url']],
                        ['key' => 'top_landing_page', 'title' => 'Top landing page', 'value' => $performance['top_landing_page']['value'], 'subtitle' => $performance['top_landing_page']['path'], 'url' => $performance['top_landing_page']['growth_url']],
                        ['key' => 'top_opportunity', 'title' => 'Top opportunity', 'value' => $performance['top_opportunity']['value'], 'subtitle' => $performance['top_opportunity']['available'] ? 'Highest priority in queue' : 'Accept opportunities in Growth', 'url' => $performance['top_opportunity']['growth_url']],
                        ['key' => 'organic_trend', 'title' => 'Organic trend', 'value' => $performance['organic_trend']['value'], 'subtitle' => $performance['organic_trend']['available'] ? 'Last 7 days vs prior week' : 'Connect Search Console in Growth', 'url' => $performance['organic_trend']['growth_url']],
                    ] as $card)
                        <div class="border border-slate-200 bg-white px-3 py-3">
                            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">{{ $card['title'] }}</p>
                            <p class="mt-1 text-xl font-black tabular-nums text-slate-950">{{ $card['value'] }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $card['subtitle'] }}</p>
                            @can(App\Ark\Runtime\Authorization\ArkCapability::GrowthAccess->value)
                                <a href="{{ $card['url'] }}" class="mt-3 inline-block text-xs font-semibold text-[#0099cc] no-underline hover:text-[#0088b8]">
                                    Open in Growth →
                                </a>
                            @endcan
                        </div>
                    @endforeach
                </div>

                <div class="mt-4 grid gap-3 lg:grid-cols-2">
                    @include('website.partials.market-pressure', ['marketPressure' => $performance['market_pressure']])

                    <div class="space-y-3">
                    <div class="border border-slate-200 bg-white">
                        <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
                            <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Website health</p>
                            <p class="text-xs text-slate-500">Readiness checklist — not a score.</p>
                        </div>
                        <ul class="divide-y divide-slate-100">
                            @foreach ($performance['health'] as $item)
                                <li class="flex items-start justify-between gap-3 px-3 py-2.5">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-950">{{ $item['label'] }}</p>
                                        @if ($item['hint'])
                                            <p class="mt-0.5 text-[11px] text-slate-500">{{ $item['hint'] }}</p>
                                        @endif
                                    </div>
                                    <span @class([
                                        'shrink-0 text-sm font-bold',
                                        'text-emerald-700' => $item['ready'],
                                        'text-amber-700' => ! $item['ready'],
                                    ])>{{ $item['ready'] ? '✓' : '⚠' }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <div class="border border-slate-200 bg-white">
                        <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
                            <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Recent form submissions</p>
                            <p class="text-xs text-slate-500">{{ $performance['period_label'] }}</p>
                        </div>
                        @if ($performance['recent_submissions'] === [])
                            <p class="px-3 py-3 text-xs text-slate-500">No website leads yet this week.</p>
                        @else
                            <ul class="divide-y divide-slate-100">
                                @foreach ($performance['recent_submissions'] as $submission)
                                    <li class="px-3 py-2.5">
                                        <a href="{{ $submission['url'] }}" class="block text-sm font-semibold text-slate-950 no-underline hover:text-[#0099cc]">
                                            {{ $submission['label'] }}
                                        </a>
                                        <p class="mt-0.5 text-[11px] text-slate-500">{{ $submission['created_label'] }}</p>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        @can(App\Ark\Runtime\Authorization\ArkCapability::GrowthAccess->value)
                            <div class="border-t border-slate-100 px-3 py-2">
                                <a href="{{ $performance['leads']['growth_url'] }}" class="text-xs font-semibold text-[#0099cc] no-underline hover:text-[#0088b8]">
                                    Open in Growth →
                                </a>
                            </div>
                        @endcan
                    </div>

                    <div class="border border-slate-200 bg-white">
                        <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
                            <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Spam observation</p>
                            <p class="text-xs text-slate-500">Owner review — not on advisor Inbox.</p>
                        </div>
                        @if (($performance['spam_observation'] ?? []) === [])
                            <p class="px-3 py-3 text-xs text-slate-500">No spam-flagged website leads yet.</p>
                        @else
                            <ul class="divide-y divide-slate-100">
                                @foreach ($performance['spam_observation'] as $spam)
                                    <li class="px-3 py-2.5">
                                        <p class="text-sm font-semibold text-slate-950">{{ $spam['label'] }}</p>
                                        <p class="mt-0.5 text-[11px] text-slate-500">{{ $spam['created_label'] }}</p>
                                        @if (filled($spam['ingress_ip'] ?? null))
                                            <p class="mt-0.5 text-[11px] text-slate-500">IP {{ $spam['ingress_ip'] }}</p>
                                        @endif
                                        @if (filled($spam['ingress_referrer'] ?? null))
                                            <p class="mt-0.5 truncate text-[11px] text-slate-500">{{ $spam['ingress_referrer'] }}</p>
                                        @endif
                                        @if (filled($spam['ingress_user_agent'] ?? null))
                                            <p class="mt-0.5 truncate text-[11px] text-slate-500">{{ $spam['ingress_user_agent'] }}</p>
                                        @endif
                                        @if (filled($spam['signals_label'] ?? null))
                                            <p class="mt-0.5 text-[11px] font-medium text-amber-800">{{ $spam['signals_label'] }}</p>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</x-operations.app>
