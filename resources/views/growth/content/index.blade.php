<x-operations.app title="Content Registry">
    <section class="space-y-3">
        <div class="border border-slate-300 bg-white">
            <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
                <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">Content registry</p>
                <h1 class="mt-0.5 text-lg font-black text-slate-950">Public content authority</h1>
                <p class="mt-1 text-xs text-slate-500">Every indexable public page — slug, template, revenue, search signals.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-xs">
                    <thead class="border-b border-slate-200 bg-slate-50 text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">
                        <tr>
                            <th class="px-3 py-2">Path</th>
                            <th class="px-3 py-2">Title</th>
                            <th class="px-3 py-2">Template</th>
                            <th class="px-3 py-2">Revenue</th>
                            <th class="px-3 py-2">Clicks</th>
                            <th class="px-3 py-2">Indexable</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($contents as $content)
                            <tr>
                                <td class="px-3 py-2 font-mono text-[11px] text-slate-700">{{ $content->path }}</td>
                                <td class="px-3 py-2 font-semibold text-slate-950">{{ $content->title }}</td>
                                <td class="px-3 py-2 text-slate-600">{{ $content->template }}</td>
                                <td class="px-3 py-2 tabular-nums font-bold text-slate-900">${{ number_format($content->revenue_cents / 100, 0) }}</td>
                                <td class="px-3 py-2 tabular-nums text-slate-700">{{ number_format($content->search_clicks) }}</td>
                                <td class="px-3 py-2">{{ $content->indexable ? 'Yes' : 'No' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-3 py-4 text-slate-500">
                                    No content registered yet. ARK synchronizes the public content registry automatically when opportunities publish or during nightly maintenance.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($contents->hasPages())
                <div class="border-t border-slate-200 px-3 py-2">{{ $contents->links() }}</div>
            @endif
        </div>
        <a href="{{ route('growth.opportunities.index') }}" class="inline-block text-xs font-bold text-sky-800 hover:text-sky-950">← Opportunities</a>
    </section>
</x-operations.app>
