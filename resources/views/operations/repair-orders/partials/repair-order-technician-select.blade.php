@props([
    'repairOrder',
    'technicians',
    'estimateVersion',
    'selectId' => 'assigned-technician',
])

@php
    use App\Ark\Runtime\Authorization\ArkCapability;

    $assignedTechnicianId = $repairOrder->assigned_technician_id ? (string) $repairOrder->assigned_technician_id : '';
    $soloOwnerShop = $soloOwnerShop ?? false;
    $needsTechnician = $repairOrder->assigned_technician_id === null
        && ! $repairOrder->hasRepairActionOwner()
        && ! $soloOwnerShop
        && ! $repairOrder->isTerminal();
    $allowsTechnicianClear = $repairOrder->allowsTechnicianClear();
@endphp

@can(ArkCapability::RepairOrdersManage->value)
    @if (! $repairOrder->isTerminal() && $technicians->isNotEmpty())
        @if ($errors->has('assigned_technician_id'))
            <p class="mb-1 max-w-xs text-[11px] font-semibold leading-4 text-rose-800">{{ $errors->first('assigned_technician_id') }}</p>
        @endif

        <div @class([
            'ops-review-toolbar-section',
            'rounded-sm border border-amber-300 bg-amber-50/60 px-1 py-0.5' => $needsTechnician,
        ])>
            <form
                method="POST"
                action="{{ route('operations.repair-orders.technician-assignment.update', $repairOrder) }}"
                data-refresh-scope="toolbar"
                @submit.prevent="submitWorksheetForm($event)"
                class="ops-review-toolbar-control"
            >
                @csrf
                @method('PATCH')
                <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
                <label for="{{ $selectId }}-{{ $repairOrder->repair_order_id }}" class="sr-only">Technician</label>
                <select
                    id="{{ $selectId }}-{{ $repairOrder->repair_order_id }}"
                    name="assigned_technician_id"
                    class="h-9 min-w-[9rem] rounded-sm border-slate-300 bg-white py-1 pl-2 pr-8 text-xs font-semibold text-slate-700 {{ $needsTechnician ? 'border-amber-400' : '' }}"
                    onchange="if (String(this.value || '') !== @js($assignedTechnicianId)) { this.form.requestSubmit(); }"
                    title="Assign a technician so In Progress can start. Repair Actions can own work separately when present."
                >
                    @if ($allowsTechnicianClear)
                        <option value="" @selected($repairOrder->assigned_technician_id === null)>Assign tech…</option>
                    @endif
                    @foreach ($technicians as $technician)
                        <option value="{{ $technician->id }}" @selected($repairOrder->assigned_technician_id === $technician->id)>{{ $technician->name }}</option>
                    @endforeach
                </select>
            </form>
            @if ($needsTechnician)
                <p class="mt-0.5 max-w-[11rem] text-[10px] font-semibold leading-3 text-amber-900">Needed before In Progress</p>
            @endif
        </div>
    @endif
@endcan
