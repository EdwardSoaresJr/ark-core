<x-operations.app title="Clusters" ui="tabler">
    <x-tabler.page layout="fluid" pretitle="Platform · hidden" title="Clusters" subtitle="Read-only. Current shops are computed from deployments.">
        <div class="card" data-platform-clusters>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Accepting</th>
                            <th>Current shops</th>
                            <th>Version</th>
                            <th>Deployment target</th>
                            <th>Ingress</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($clusters as $cluster)
                            <tr>
                                <td class="fw-medium">{{ $cluster->name }}</td>
                                <td>{{ $cluster->type->label() }}</td>
                                <td>{{ $cluster->status->label() }}</td>
                                <td>{{ $cluster->accepting_new_shops ? 'Yes' : 'No' }}</td>
                                <td style="font-variant-numeric: tabular-nums">{{ $cluster->deployments_count }}</td>
                                <td class="text-secondary">{{ $cluster->current_version ?: '-' }}</td>
                                <td class="font-monospace small">{{ $cluster->deployment_target }}</td>
                                <td class="font-monospace small">{{ $cluster->ingress_endpoint }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-secondary py-5">
                                    No clusters. Seed with <code>php artisan db:seed --class=ClusterSeeder</code>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </x-tabler.page>
</x-operations.app>
