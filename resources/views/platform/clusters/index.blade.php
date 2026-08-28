<x-operations.app title="Clusters">
    <div class="mx-auto max-w-6xl px-4 py-6">
        <header class="mb-6">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Platform · hidden</p>
            <h1 class="mt-1 text-xl font-semibold text-slate-900">Clusters</h1>
            <p class="mt-1 text-sm text-slate-600">Read-only. Current shops are computed from deployments.</p>
        </header>

        <div class="overflow-x-auto rounded border border-slate-200 bg-white">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-3 py-2 font-medium">Name</th>
                        <th class="px-3 py-2 font-medium">Type</th>
                        <th class="px-3 py-2 font-medium">Status</th>
                        <th class="px-3 py-2 font-medium">Accepting</th>
                        <th class="px-3 py-2 font-medium">Current shops</th>
                        <th class="px-3 py-2 font-medium">Version</th>
                        <th class="px-3 py-2 font-medium">Deployment target</th>
                        <th class="px-3 py-2 font-medium">Ingress</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($clusters as $cluster)
                        <tr class="text-slate-800">
                            <td class="px-3 py-2 font-medium">{{ $cluster->name }}</td>
                            <td class="px-3 py-2">{{ $cluster->type->label() }}</td>
                            <td class="px-3 py-2">{{ $cluster->status->label() }}</td>
                            <td class="px-3 py-2">{{ $cluster->accepting_new_shops ? 'Yes' : 'No' }}</td>
                            <td class="px-3 py-2 tabular-nums">{{ $cluster->deployments_count }}</td>
                            <td class="px-3 py-2 text-slate-600">{{ $cluster->current_version ?: '—' }}</td>
                            <td class="px-3 py-2 font-mono text-xs">{{ $cluster->deployment_target }}</td>
                            <td class="px-3 py-2 font-mono text-xs">{{ $cluster->ingress_endpoint }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-3 py-8 text-center text-slate-500">
                                No clusters. Seed with <code class="text-xs">php artisan db:seed --class=ClusterSeeder</code>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-operations.app>
