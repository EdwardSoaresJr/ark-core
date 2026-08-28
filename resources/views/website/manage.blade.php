<x-operations.app title="Website">
    <section class="space-y-3">
        <div class="border border-slate-300 bg-white">
            <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
                <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">Website</p>
                <h1 class="mt-0.5 text-lg font-black text-slate-950">Manage your website</h1>
                <p class="mt-1 max-w-3xl text-xs text-slate-500">
                    What customers see on your public site — homepage, trust, photos, and reviews. For why traffic changed, use Growth.
                </p>
            </div>

            <div class="px-3 py-3">
                @include('website.partials.subnav', ['activeTab' => 'manage'])

                @if (session('status'))
                    <p class="mt-3 border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-900">{{ session('status') }}</p>
                @endif

                <div class="mt-4 grid gap-3 lg:grid-cols-2">
                    <div class="rounded-md border border-slate-200 bg-slate-50/60 px-3 py-2.5">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Contact information</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">{{ $shop->phone ?: 'Phone not set' }}</p>
                        <p class="mt-0.5 text-xs text-slate-600">{{ $shop->address_line_1 ?: 'Address not set' }}</p>
                        <a href="{{ route('operations.settings.shop.edit', ['section' => 'general']) }}" class="mt-2 inline-block text-xs font-semibold text-[#0099cc] no-underline hover:text-[#0088b8]">
                            Edit shop contact →
                        </a>
                    </div>
                    @can(App\Ark\Runtime\Authorization\ArkCapability::SettingsManage->value)
                        <div class="rounded-md border border-slate-200 bg-slate-50/60 px-3 py-2.5">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Content pages</p>
                            <p class="mt-1 text-xs text-slate-600">Common problem pages, local intent pages, and optional featured media per page.</p>
                            <div class="mt-2 flex flex-wrap gap-3 text-xs font-semibold">
                                <a href="{{ route('website.page-media.index') }}" class="text-[#0099cc] no-underline hover:text-[#0088b8]">Page media →</a>
                                <a href="{{ route('growth.content.index') }}" class="text-[#0099cc] no-underline hover:text-[#0088b8]">Service pages →</a>
                                <a href="{{ route('public.common-problems.index') }}" target="_blank" rel="noopener" class="text-[#0099cc] no-underline hover:text-[#0088b8]">View common problems ↗</a>
                            </div>
                        </div>
                    @endcan
                </div>

                <div class="mt-5 border-t border-slate-200 pt-4">
                    <div class="border-b border-slate-200 pb-2">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Homepage &amp; trust</p>
                        <h2 class="text-base font-black text-slate-950">Presentation</h2>
                        <p class="mt-0.5 text-xs text-slate-500">Headline, reviews, trust line, and proof photos for the lead intake homepage.</p>
                    </div>

                    @include('website.partials.manage-form', [
                        'publicSurfaceSettings' => $publicSurfaceSettings,
                        'servicePageOptions' => $servicePageOptions,
                    ])
                </div>
            </div>
        </div>
    </section>
</x-operations.app>
