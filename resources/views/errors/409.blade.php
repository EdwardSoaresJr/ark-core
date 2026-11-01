<x-operations.app title="Repair Order Changed" ui="tabler">
    <x-tabler.page layout="focused" pretitle="Shared Shop State" title="Estimate changed while you were working.">
        <div class="card" data-estimate-conflict>
            <div class="card-status-start bg-warning"></div>
            <div class="card-body p-3">
                <p class="mb-0">
                    {{ $message ?? 'Refresh the worksheet and review the latest estimate state before saving.' }}
                </p>
            </div>
            <div class="card-footer">
                <div class="btn-list">
                    <a href="{{ url()->previous() }}" class="btn btn-primary">
                        Review Latest State
                    </a>
                    <a href="{{ route('operations.index') }}" class="btn">
                        Return To Queue
                    </a>
                </div>
            </div>
        </div>
    </x-tabler.page>
</x-operations.app>
