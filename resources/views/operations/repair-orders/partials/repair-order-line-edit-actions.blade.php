@props([
    'line',
    'repairOrder',
    'estimateVersion',
    'gridPlacement' => true,
])

<div
    @class([
        'd-flex flex-wrap align-items-center justify-content-between gap-2',
        'col-12' => $gridPlacement,
    ])
    data-line-edit-actions
    x-data="{
        confirmDelete: false,
        confirmTimer: null,
        armDelete() {
            this.confirmDelete = true;
            this.clearDeleteTimer();
            this.confirmTimer = setTimeout(() => {
                this.confirmDelete = false;
                this.confirmTimer = null;
            }, 3000);
        },
        cancelDelete() {
            this.clearDeleteTimer();
            this.confirmDelete = false;
        },
        clearDeleteTimer() {
            if (this.confirmTimer) {
                clearTimeout(this.confirmTimer);
                this.confirmTimer = null;
            }
        },
        submitDelete(event) {
            if (! this.confirmDelete) {
                event.preventDefault();
                this.armDelete();

                return;
            }

            this.clearDeleteTimer();
            const worksheet = this.$el.closest('[data-worksheet-root]')?._x_dataStack?.[0];

            if (typeof worksheet?.submitWorksheetForm === 'function') {
                worksheet.submitWorksheetForm(event);

                return;
            }

            event.target.submit();
        },
    }"
>
    <button
        type="submit"
        form="line-update-{{ $line->id }}"
        class="btn btn-primary btn-sm"
        aria-label="Save line"
        title="Save line"
    >
        <x-tabler.icon name="check" />
        <span>Save</span>
    </button>

    @can(App\Ark\Runtime\Authorization\ArkCapability::RepairOrdersDestructive->value)
        <div>
            <form
                method="POST"
                action="{{ route('operations.repair-orders.lines.destroy', [$repairOrder, $line]) }}"
                data-worksheet-continuity
                data-refresh-scope="worksheet"
                data-continuity-focus="#line-store-concern-{{ $line->repair_order_concern_id }} [name='description']"
                @submit.prevent="submitDelete($event)"
            >
                @csrf
                @method('DELETE')
                <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">

                <div class="btn-list">
                    <button
                        type="button"
                        x-show="! confirmDelete"
                        class="btn btn-ghost-danger btn-sm"
                        aria-label="Delete line"
                        title="Remove this line (requires confirmation)"
                        @click="armDelete()"
                    >
                        <x-tabler.icon name="trash" />
                        <span>Delete</span>
                    </button>

                    <button
                        type="submit"
                        x-show="confirmDelete"
                        x-cloak
                        class="btn btn-danger btn-sm"
                    >
                        Confirm delete
                    </button>

                    <button
                        type="button"
                        x-show="confirmDelete"
                        x-cloak
                        class="btn btn-sm"
                        @click="cancelDelete()"
                    >
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    @endcan
</div>
