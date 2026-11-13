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
    $fieldId = $selectId.'-'.$repairOrder->repair_order_id;
    $menuId = $fieldId.'-menu';
    $assignedTechnician = $technicians->firstWhere('id', $repairOrder->assigned_technician_id);
    $assignedLabel = $assignedTechnician?->name ?? 'Assign tech…';
@endphp

@can(ArkCapability::RepairOrdersManage->value)
    @if (! $repairOrder->isTerminal() && $technicians->isNotEmpty())
        <div class="row g-0 align-items-baseline ark-ro-visit-commands__tech">
            <div class="col-4 text-secondary">Technician</div>
            <div class="col-8">
                @if ($errors->has('assigned_technician_id'))
                    <div class="small text-danger mb-1">{{ $errors->first('assigned_technician_id') }}</div>
                @endif
                <form
                    method="POST"
                    action="{{ route('operations.repair-orders.technician-assignment.update', $repairOrder) }}"
                    data-refresh-scope="toolbar"
                    @submit.prevent="submitWorksheetForm($event)"
                    class="m-0"
                >
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">

                    <div class="dropdown">
                        <button
                            type="button"
                            class="ops-visit-inline-trigger fw-medium"
                            data-bs-toggle="dropdown"
                            data-bs-display="static"
                            aria-expanded="false"
                            aria-haspopup="listbox"
                            aria-controls="{{ $menuId }}"
                            aria-label="Technician"
                            title="Assign a technician so In Progress can start. Repair Actions can own work separately when present."
                            data-technician-trigger
                        >
                            <span @class(['ops-visit-inline-value', 'is-warning' => $needsTechnician])>{{ $assignedLabel }}</span>
                        </button>
                        <ul
                            id="{{ $menuId }}"
                            class="dropdown-menu"
                            role="listbox"
                            aria-label="Technician"
                        >
                            @if ($needsTechnician)
                                <li>
                                    <span class="dropdown-item-text small text-warning">Needed before In Progress</span>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                            @endif
                            @if ($allowsTechnicianClear)
                                <li>
                                    <button
                                        type="button"
                                        @class(['dropdown-item', 'active' => $repairOrder->assigned_technician_id === null])
                                        role="option"
                                        aria-selected="{{ $repairOrder->assigned_technician_id === null ? 'true' : 'false' }}"
                                        data-technician-option
                                        data-technician-value=""
                                    >Assign tech…</button>
                                </li>
                            @endif
                            @foreach ($technicians as $technician)
                                <li>
                                    <button
                                        type="button"
                                        @class(['dropdown-item', 'active' => (int) $repairOrder->assigned_technician_id === (int) $technician->id])
                                        role="option"
                                        aria-selected="{{ (int) $repairOrder->assigned_technician_id === (int) $technician->id ? 'true' : 'false' }}"
                                        data-technician-option
                                        data-technician-value="{{ $technician->id }}"
                                    >{{ $technician->name }}</button>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <label class="visually-hidden" for="{{ $fieldId }}">Technician</label>
                    <select
                        id="{{ $fieldId }}"
                        name="assigned_technician_id"
                        class="visually-hidden"
                        tabindex="-1"
                        aria-hidden="true"
                        data-technician-select
                        onchange="if (String(this.value || '') !== @js($assignedTechnicianId)) { this.form.requestSubmit(); }"
                    >
                        @if ($allowsTechnicianClear)
                            <option value="" @selected($repairOrder->assigned_technician_id === null)>Assign tech…</option>
                        @endif
                        @foreach ($technicians as $technician)
                            <option value="{{ $technician->id }}" @selected($repairOrder->assigned_technician_id === $technician->id)>{{ $technician->name }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
        </div>
    @endif
@endcan
