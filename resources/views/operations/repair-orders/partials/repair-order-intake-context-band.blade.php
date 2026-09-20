@if ($repairOrder->status->isIntake())
    <div class="ops-board-shell">
        <x-operations.workspace-context-band :note="$repairOrder->statusDisplayLabel()">
            <x-slot:actions>
                <a href="{{ route('operations.index') }}" class="ops-page-link">← Job Board</a>
            </x-slot:actions>
        </x-operations.workspace-context-band>
    </div>
@endif
