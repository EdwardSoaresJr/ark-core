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
            <div class="small text-danger mb-1">{{ $errors->first('assigned_technician_id') }}</div>
        @endif

        <div style="max-width: 100%">
            <form
                method="POST"
                action="{{ route('operations.repair-orders.technician-assignment.update', $repairOrder) }}"
                data-refresh-scope="toolbar"
                @submit.prevent="submitWorksheetForm($event)"
                class="m-0"
                style="width: 12rem; max-width: 100%"
            >
                @csrf
                @method('PATCH')
                <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
                <label for="{{ $selectId }}-{{ $repairOrder->repair_order_id }}" class="sr-only">Technician</label>
                <select
                    id="{{ $selectId }}-{{ $repairOrder->repair_order_id }}"
                    name="assigned_technician_id"
                    @class(['form-select', 'border-warning' => $needsTechnician])
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
                <div class="small text-warning mt-1">Needed before In Progress</div>
            @endif
        </div>
    @endif
@endcan
