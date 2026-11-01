@if ($repairOrder->status->isIntake())
    <x-operations.workspace-context-band :note="$repairOrder->statusDisplayLabel()">
        <x-slot:actions>
            <a href="{{ route('operations.index') }}" class="btn btn-sm btn-ghost-secondary">
                <x-tabler.icon name="arrow-left" />
                Job Board
            </a>
        </x-slot:actions>
    </x-operations.workspace-context-band>
@endif
