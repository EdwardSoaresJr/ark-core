@props([
    'line',
    'repairOrder',
    'estimateVersion',
    'gridPlacement' => true,
])

<div
    @class([
        'ops-line-edit-actions',
        'lg:col-span-7 lg:col-start-1' => $gridPlacement,
    ])
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
        class="ops-line-edit-actions__save"
        aria-label="Save line"
        title="Save line"
    >
        <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 20 20" fill="none">
            <path d="M5 10.5l3.25 3.25L15 7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        <span class="ops-line-edit-actions__label">Save</span>
    </button>

    @can(App\Ark\Runtime\Authorization\ArkCapability::RepairOrdersDestructive->value)
        <div class="ops-line-edit-actions__danger">
            <form
                method="POST"
                action="{{ route('operations.repair-orders.lines.destroy', [$repairOrder, $line]) }}"
                data-refresh-scope="worksheet"
                data-continuity-focus="#line-store-concern-{{ $line->repair_order_concern_id }} [name='description']"
                @submit.prevent="submitDelete($event)"
            >
                @csrf
                @method('DELETE')
                <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">

                <div class="ops-line-edit-actions__danger-buttons">
                    <button
                        type="button"
                        x-show="! confirmDelete"
                        class="ops-line-edit-actions__delete"
                        aria-label="Delete line"
                        title="Remove this line (requires confirmation)"
                        @click="armDelete()"
                    >
                        <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 20 20" fill="none">
                            <path d="M7.25 5.5V4.75A1.75 1.75 0 019 3h2a1.75 1.75 0 011.75 1.75v.75M4.75 5.5h10.5M8 8.25v5M12 8.25v5M6.25 5.5l.5 10A1.75 1.75 0 008.5 17h3a1.75 1.75 0 001.75-1.5l.5-10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <span class="ops-line-edit-actions__label">Delete</span>
                    </button>

                    <button
                        type="submit"
                        x-show="confirmDelete"
                        x-cloak
                        class="ops-line-edit-actions__confirm-delete"
                    >
                        Confirm delete
                    </button>

                    <button
                        type="button"
                        x-show="confirmDelete"
                        x-cloak
                        class="ops-line-edit-actions__cancel-delete"
                        @click="cancelDelete()"
                    >
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    @endcan
</div>
