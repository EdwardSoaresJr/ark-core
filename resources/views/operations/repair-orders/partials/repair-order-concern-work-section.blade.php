@php
    $concernWorkGroups = $concern->workGroups;
    $concernUsesRepairActions = $concern->usesRepairActions();
    $concernUngroupedLines = \App\Ark\Operations\RepairOrders\RepairOrderLineWorksheetOrder::sort(
        $concern->lines->filter(fn ($line) => $line->repair_order_work_group_id === null && $line->shouldDisplayOnEstimateWorksheet())
    );
    $displayLines = $concernUsesRepairActions
        ? $concernUngroupedLines
        : \App\Ark\Operations\RepairOrders\RepairOrderLineWorksheetOrder::sort(
            $concern->lines->filter(fn ($line) => $line->shouldDisplayOnEstimateWorksheet())
        );
    $concernDefaultPartPricingMode = $concern->billing_posture->prefersManualPartPricing() ? 'manual' : 'matrix';
    $concernDefaultPartSell = $concernDefaultPartPricingMode === 'manual' ? '0' : '';
@endphp

@include('operations.repair-orders.partials.repair-order-scope-repair-action-suggestions', [
    'repairOrder' => $repairOrder,
    'concern' => $concern,
    'isTerminal' => $isTerminal,
    'estimateVersion' => $estimateVersion,
])

@foreach ($concernWorkGroups as $workGroup)
    @php
        $workGroupLines = \App\Ark\Operations\RepairOrders\RepairOrderLineWorksheetOrder::sort(
            $workGroup->lines->filter(fn ($line) => $line->shouldDisplayOnEstimateWorksheet())
        );
        $workGroupLaborCount = \App\Ark\Operations\RepairOrders\LaborDescriptionPresentation::laborCountInGroup($workGroup->lines);
        $allowedComposerTypes = $workGroup->allowedComposerLineTypes();
        $composeDefaultType = $workGroup->hasPartsAttachAnchor() && ! $workGroup->hasLaborAnchor()
            ? App\Ark\Operations\RepairOrders\RepairOrderLineType::Part->value
            : $allowedComposerTypes[0]->value;
        $oldInputBelongsToWorkGroup = (string) old('repair_order_work_group_id') === (string) $workGroup->id;
        $defaultLineType = $oldInputBelongsToWorkGroup ? old('type', '') : ($workGroup->hasLaborAnchor() ? '' : $composeDefaultType);
        $defaultLineDescription = $oldInputBelongsToWorkGroup
            ? old('description', '')
            : ($defaultLineType === App\Ark\Operations\RepairOrders\RepairOrderLineType::Labor->value ? $workGroup->title : '');
        $defaultLineSell = $oldInputBelongsToWorkGroup
            ? old('unit_price', in_array(old('type'), ['part'], true) ? $concernDefaultPartSell : (in_array(old('type'), ['note'], true) ? '0' : $concernDefaultLaborRate))
            : ($defaultLineType === App\Ark\Operations\RepairOrders\RepairOrderLineType::Labor->value ? $concernDefaultLaborRate : '');
        $laborStoreNeedsAdvanced = $oldInputBelongsToWorkGroup && (
            old('labor_adjustment', 'normal') !== 'normal'
            || old('labor_category_key', $concernDefaultLaborCategoryKey) !== $concernDefaultLaborCategoryKey
            || old('labor_hours_overridden')
            || filled(old('labor_override_reason'))
        );
        $suppressComposeLaborDescription = ! $workGroup->hasLaborAnchor();
    @endphp

    @php
        $actionStatus = $workGroup->status instanceof \App\Ark\Operations\RepairOrders\RepairActionStatus
            ? $workGroup->status
            : \App\Ark\Operations\RepairOrders\RepairActionStatus::Pending;
        $ownerLabel = $workGroup->ownerUser?->name ?? 'Unassigned';
        $canManageRepairAction = auth()->user()?->can(App\Ark\Runtime\Authorization\ArkCapability::RepairOrdersManage->value);
    @endphp

    <section id="repair-action-{{ $workGroup->id }}" class="border-bottom scroll-mt-24" data-repair-action>
        <div class="card-body py-2 d-flex align-items-start gap-2">
            <div class="flex-fill" style="min-width: 0">
                <div class="fw-medium">{{ $workGroup->title }}</div>

                @if ($canManageRepairAction && ! $isTerminal)
                    <button
                        type="button"
                        class="btn btn-link btn-sm p-0 text-secondary"
                        data-repair-action-meta
                        title="Owner, status, and update"
                        @click="window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', { detail: { task: 'repair-action-meta', context: { workGroupId: {{ $workGroup->id }}, concernId: {{ $concern->id }} }, invokeEl: $event.currentTarget } }))"
                    >
                        {{ $actionStatus->label() }} · {{ $ownerLabel }}
                    </button>
                @else
                    <p class="small text-secondary mb-0" data-repair-action-meta>
                        {{ $actionStatus->label() }} · {{ $ownerLabel }}
                        @if ($workGroup->updated_at)
                            · Updated {{ $workGroup->updated_at->timezone(config('app.timezone'))->format('g:i A') }}
                        @endif
                    </p>
                @endif

                @if (filled($workGroup->latest_update))
                    <p class="small mt-1 mb-0">{{ $workGroup->latest_update }}</p>
                @endif
            </div>
            @unless ($isTerminal)
                @can(App\Ark\Runtime\Authorization\ArkCapability::RepairOrdersDestructive->value)
                    @if ($workGroupLines->isEmpty())
                        <form
                            method="POST"
                            action="{{ route('operations.repair-orders.work-groups.destroy', [$repairOrder, $workGroup]) }}"
                            data-refresh-scope="worksheet"
                            data-continuity-focus="#concern-{{ $concern->id }}"
                            @submit.prevent="submitWorksheetForm($event)"
                        >
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
                            <button type="submit" class="btn btn-sm btn-ghost-danger">Remove</button>
                        </form>
                    @endif
                @endcan
            @endunless
        </div>

        {{-- Worksheet continuity mounts new line regions by this container's id, so rows stay its direct children. --}}
        <div id="repair-action-lines-{{ $workGroup->id }}" class="list-group list-group-flush">
            @if ($workGroupLines->isNotEmpty())
                @include('operations.repair-orders.partials.repair-order-line-ledger-head')
            @endif
            @foreach ($workGroupLines as $line)
                @include('operations.repair-orders.partials.repair-order-concern-line-row', [
                    'line' => $line,
                    'workGroup' => $workGroup,
                    'workGroupLaborCount' => $workGroupLaborCount,
                ])
            @endforeach
        </div>

        @unless ($isTerminal)
            @php
                $composeButtons = collect($allowedComposerTypes)->map(function ($type) {
                    $value = $type->value;
                    $ariaLabel = match ($type) {
                        App\Ark\Operations\RepairOrders\RepairOrderLineType::Labor => 'Add Labor',
                        App\Ark\Operations\RepairOrders\RepairOrderLineType::Part => 'Add Part',
                        App\Ark\Operations\RepairOrders\RepairOrderLineType::Note => 'Add Note',
                        App\Ark\Operations\RepairOrders\RepairOrderLineType::Sublet => 'Add Sublet',
                        App\Ark\Operations\RepairOrders\RepairOrderLineType::Fee => 'Add Fee',
                        default => 'Add '.$type->staffLabel(),
                    };
                    $label = match ($type) {
                        App\Ark\Operations\RepairOrders\RepairOrderLineType::Labor => 'Labor',
                        App\Ark\Operations\RepairOrders\RepairOrderLineType::Part => 'Part',
                        App\Ark\Operations\RepairOrders\RepairOrderLineType::Note => 'Note',
                        App\Ark\Operations\RepairOrders\RepairOrderLineType::Sublet => 'Sublet',
                        App\Ark\Operations\RepairOrders\RepairOrderLineType::Fee => 'Fee',
                        default => $type->staffLabel(),
                    };
                    $icon = match ($type) {
                        App\Ark\Operations\RepairOrders\RepairOrderLineType::Labor => 'labor',
                        App\Ark\Operations\RepairOrders\RepairOrderLineType::Part => 'part',
                        App\Ark\Operations\RepairOrders\RepairOrderLineType::Note => 'note',
                        App\Ark\Operations\RepairOrders\RepairOrderLineType::Sublet => 'sublet',
                        App\Ark\Operations\RepairOrders\RepairOrderLineType::Fee => 'fee',
                        default => 'labor',
                    };
                    $color = $type->tablerColor();

                    return compact('value', 'label', 'ariaLabel', 'icon', 'color');
                });
            @endphp
            <div class="card-body py-2 btn-list" data-repair-action-compose>
                @foreach ($composeButtons as $composeButton)
                    <button
                        type="button"
                        class="btn btn-sm"
                        aria-label="{{ $composeButton['ariaLabel'] }}"
                        title="{{ $composeButton['ariaLabel'] }}"
                        @click="window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', { detail: { task: '{{ $composeButton['value'] }}', context: { lineType: '{{ $composeButton['value'] }}', workGroupId: {{ $workGroup->id }}, concernId: {{ $concern->id }} }, invokeEl: $event.currentTarget } }))"
                    >
                        <span class="text-{{ $composeButton['color'] }} d-inline-flex">@include('operations.repair-orders.partials.workspace-modal.compose-icon', ['icon' => $composeButton['icon']])</span>
                        <span class="ms-1" data-compose-label>{{ $composeButton['label'] }}</span>
                    </button>
                @endforeach
                <button
                    type="button"
                    class="btn btn-sm"
                    aria-label="Add Photo"
                    title="Add Photo"
                    @click="window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', { detail: { task: 'evidence', context: { concernId: {{ $concern->id }} }, invokeEl: $event.currentTarget } }))"
                >
                    @include('operations.repair-orders.partials.workspace-modal.compose-icon', ['icon' => 'evidence'])
                    <span class="ms-1" data-compose-label>Photo</span>
                </button>
            </div>
        @endunless
    </section>
@endforeach

<div id="concern-lines-{{ $concern->id }}" class="list-group list-group-flush">
    @if ($displayLines->isNotEmpty())
        @include('operations.repair-orders.partials.repair-order-line-ledger-head', [
            'ledgerHeadLabel' => $concernUsesRepairActions ? 'Standalone scope lines' : 'Scope lines',
        ])
    @endif

    @foreach ($displayLines as $line)
        @include('operations.repair-orders.partials.repair-order-concern-line-row', [
            'line' => $line,
        ])
    @endforeach
</div>

@unless ($isTerminal)
    @if ($concernUsesRepairActions)
        <div class="card-body py-2 btn-list">
            <button
                type="button"
                class="btn btn-sm"
                aria-label="Add Common Job"
                title="Add Common Job to this concern"
                @click="window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', { detail: { task: 'saved-work', context: { concernId: {{ $concern->id }} }, invokeEl: $event.currentTarget } }))"
            >
                + Common Job
            </button>
        </div>
    @endif
@endunless

@unless ($isTerminal)
    @if ($concernUsesRepairActions && $concernWorkGroups->isEmpty())
        {{-- No Repair Action yet - deepest context is the concern. --}}
        <div class="card-body py-2 btn-list" data-scope-compose="{{ $concern->id }}">
            <button
                type="button"
                class="btn btn-sm"
                aria-label="Add Common Job"
                title="Add Common Job to this concern"
                @click="window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', { detail: { task: 'saved-work', context: { concernId: {{ $concern->id }} }, invokeEl: $event.currentTarget } }))"
            >
                + Common Job
            </button>
            <button
                type="button"
                class="btn btn-sm"
                aria-label="Add Note"
                title="Add Note"
                @click="window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', { detail: { task: 'note', context: { lineType: 'note', concernId: {{ $concern->id }} }, invokeEl: $event.currentTarget } }))"
            >
                + Note
            </button>
        </div>
    @elseif (! $concernUsesRepairActions)
        {{-- Diagnostic / legacy concerns have no Repair Action - full compose lives here. --}}
        @php
            $scopeComposeTypes = [
                App\Ark\Operations\RepairOrders\RepairOrderLineType::Labor,
                App\Ark\Operations\RepairOrders\RepairOrderLineType::Part,
                App\Ark\Operations\RepairOrders\RepairOrderLineType::Note,
                App\Ark\Operations\RepairOrders\RepairOrderLineType::Sublet,
            ];
            $scopeComposeButtons = collect($scopeComposeTypes)->map(function ($type) {
                $value = $type->value;
                $ariaLabel = match ($type) {
                    App\Ark\Operations\RepairOrders\RepairOrderLineType::Labor => 'Add Labor',
                    App\Ark\Operations\RepairOrders\RepairOrderLineType::Part => 'Add Part',
                    App\Ark\Operations\RepairOrders\RepairOrderLineType::Note => 'Add Note',
                    App\Ark\Operations\RepairOrders\RepairOrderLineType::Sublet => 'Add Sublet',
                    default => 'Add '.$type->staffLabel(),
                };
                $label = match ($type) {
                    App\Ark\Operations\RepairOrders\RepairOrderLineType::Labor => 'Labor',
                    App\Ark\Operations\RepairOrders\RepairOrderLineType::Part => 'Part',
                    App\Ark\Operations\RepairOrders\RepairOrderLineType::Note => 'Note',
                    App\Ark\Operations\RepairOrders\RepairOrderLineType::Sublet => 'Sublet',
                    default => $type->staffLabel(),
                };
                $icon = match ($type) {
                    App\Ark\Operations\RepairOrders\RepairOrderLineType::Labor => 'labor',
                    App\Ark\Operations\RepairOrders\RepairOrderLineType::Part => 'part',
                    App\Ark\Operations\RepairOrders\RepairOrderLineType::Note => 'note',
                    App\Ark\Operations\RepairOrders\RepairOrderLineType::Sublet => 'sublet',
                    default => 'labor',
                };
                $color = $type->tablerColor();

                return compact('value', 'label', 'ariaLabel', 'icon', 'color');
            });
        @endphp
        <div class="card-body py-2 btn-list" data-scope-compose="{{ $concern->id }}">
            <button
                type="button"
                class="btn btn-sm"
                aria-label="Add Common Job"
                title="Add Common Job to this concern"
                @click="window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', { detail: { task: 'saved-work', context: { concernId: {{ $concern->id }} }, invokeEl: $event.currentTarget } }))"
            >
                @include('operations.repair-orders.partials.workspace-modal.compose-icon', ['icon' => 'saved-work'])
                <span class="ms-1" data-compose-label>Common Job</span>
            </button>
            @foreach ($scopeComposeButtons as $composeButton)
                <button
                    type="button"
                    class="btn btn-sm"
                    aria-label="{{ $composeButton['ariaLabel'] }}"
                    title="{{ $composeButton['ariaLabel'] }}"
                    @click="window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', { detail: { task: '{{ $composeButton['value'] }}', context: { lineType: '{{ $composeButton['value'] }}', concernId: {{ $concern->id }} }, invokeEl: $event.currentTarget } }))"
                >
                    <span class="text-{{ $composeButton['color'] }} d-inline-flex">@include('operations.repair-orders.partials.workspace-modal.compose-icon', ['icon' => $composeButton['icon']])</span>
                    <span class="ms-1" data-compose-label>{{ $composeButton['label'] }}</span>
                </button>
            @endforeach
            <button
                type="button"
                class="btn btn-sm"
                aria-label="Add Photo"
                title="Add Photo"
                @click="window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', { detail: { task: 'evidence', context: { concernId: {{ $concern->id }} }, invokeEl: $event.currentTarget } }))"
            >
                @include('operations.repair-orders.partials.workspace-modal.compose-icon', ['icon' => 'evidence'])
                <span class="ms-1" data-compose-label>Photo</span>
            </button>
        </div>
    @endif
@endunless
