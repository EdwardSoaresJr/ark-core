<x-operations.app title="Redirects">
    <section class="space-y-3">
        <div class="border border-slate-300 bg-white">
            <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
                <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">Redirect manager</p>
                <h1 class="mt-0.5 text-lg font-black text-slate-950">Database-backed redirects</h1>
            </div>

            <form method="POST" action="{{ route('growth.redirects.store') }}" class="space-y-2 border-b border-slate-200 px-3 py-3">
                @csrf
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                    <label class="text-xs text-slate-600">
                        From path
                        <input name="from_path" required class="mt-0.5 block w-full rounded-sm border border-slate-300 px-2 py-1 text-sm" placeholder="/old-path">
                    </label>
                    <label class="text-xs text-slate-600">
                        To path
                        <input name="to_path" class="mt-0.5 block w-full rounded-sm border border-slate-300 px-2 py-1 text-sm" placeholder="/new-path">
                    </label>
                    <label class="text-xs text-slate-600">
                        Status
                        <select name="status_code" class="mt-0.5 block w-full rounded-sm border border-slate-300 px-2 py-1 text-sm">
                            <option value="301">301 Permanent</option>
                            <option value="302">302 Temporary</option>
                            <option value="410">410 Gone</option>
                        </select>
                    </label>
                    <label class="flex items-end gap-2 text-xs text-slate-600">
                        <input type="checkbox" name="is_wildcard" value="1" class="rounded border-slate-300">
                        Wildcard pattern
                    </label>
                </div>
                @if ($errors->any())
                    <p class="text-xs font-bold text-red-700">{{ $errors->first() }}</p>
                @endif
                @if (session('status'))
                    <p class="text-xs font-bold text-emerald-800">{{ session('status') }}</p>
                @endif
                <button type="submit" class="rounded-sm border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold text-slate-800">Save redirect</button>
            </form>

            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-xs">
                    <thead class="border-b border-slate-200 bg-slate-50 text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">
                        <tr>
                            <th class="px-3 py-2">From</th>
                            <th class="px-3 py-2">To</th>
                            <th class="px-3 py-2">Code</th>
                            <th class="px-3 py-2">Hits</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($redirects as $redirect)
                            <tr>
                                <td class="px-3 py-2 font-mono text-[11px]">{{ $redirect->from_path }}{{ $redirect->is_wildcard ? '*' : '' }}</td>
                                <td class="px-3 py-2 font-mono text-[11px]">{{ $redirect->to_path ?? '—' }}</td>
                                <td class="px-3 py-2 tabular-nums">{{ $redirect->status_code }}</td>
                                <td class="px-3 py-2 tabular-nums">{{ number_format($redirect->hit_count) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-3 py-4 text-slate-500">No redirects configured.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($redirects->hasPages())
                <div class="border-t border-slate-200 px-3 py-2">{{ $redirects->links() }}</div>
            @endif
        </div>
        <a href="{{ route('growth.opportunities.index') }}" class="inline-block text-xs font-bold text-sky-800 hover:text-sky-950">← Opportunities</a>
    </section>
</x-operations.app>
