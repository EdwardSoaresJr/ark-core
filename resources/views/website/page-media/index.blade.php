<x-operations.app title="Website — Page media">
    <section class="space-y-3">
        <div class="border border-slate-300 bg-white">
            <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
                <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">Website</p>
                <h1 class="mt-0.5 text-lg font-black text-slate-950">Page featured media</h1>
                <p class="mt-1 max-w-3xl text-xs text-slate-500">
                    Optional real repair photos for common-problem and local-intent pages. Pages without media look exactly the same — no placeholders.
                </p>
            </div>

            <div class="px-3 py-3">
                @include('website.partials.subnav', ['activeTab' => 'page-media'])

                <p class="mt-4 text-sm text-slate-600">
                    <span class="font-semibold text-slate-900">{{ $mediaCount }}</span>
                    of
                    <span class="font-semibold text-slate-900">{{ count($pages) }}</span>
                    pages have featured media.
                </p>

                <div class="mt-4 overflow-x-auto border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-3 py-2">Page</th>
                                <th class="px-3 py-2">Media</th>
                                <th class="px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @foreach ($pages as $page)
                                <tr>
                                    <td class="px-3 py-2.5">
                                        <p class="font-semibold text-slate-900">{{ $page['title'] }}</p>
                                        <p class="text-xs text-slate-500">{{ $page['slug'] }}</p>
                                    </td>
                                    <td class="px-3 py-2.5">
                                        @if ($page['has_media'])
                                            <div class="flex items-center gap-2.5">
                                                <span class="text-sm leading-none" aria-hidden="true">✅</span>
                                                @if ($page['preview_url'])
                                                    <img
                                                        src="{{ $page['preview_url'] }}"
                                                        alt=""
                                                        class="h-10 w-16 rounded object-cover ring-1 ring-slate-200"
                                                    >
                                                @endif
                                                <span class="text-xs font-semibold text-emerald-800">
                                                    {{ $page['photo_count'] === 1 ? '1 photo' : $page['photo_count'].' photos' }}
                                                </span>
                                            </div>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 text-xs text-slate-500">
                                                <span class="text-sm leading-none" aria-hidden="true">⚪</span>
                                                No photo
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2.5 text-right">
                                        <a
                                            href="{{ route('website.page-media.edit', $page['slug']) }}"
                                            class="text-xs font-semibold text-[#0099cc] no-underline hover:text-[#0088b8]"
                                        >
                                            {{ $page['has_media'] ? 'Edit' : 'Add photo' }} →
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</x-operations.app>
