/**
 * Shared scheduler appointment popover - Month is the interaction reference.
 * Single-open, fixed placement, flip above near viewport edges.
 */
export default function arkScheduleAppointmentPopover() {
    return {
        openAppointmentId: null,
        anchor: null,
        popTop: 0,
        popLeft: 0,
        openAbove: false,

        toggle(id, button) {
            if (this.openAppointmentId === id) {
                this.close();

                return;
            }

            this.openAppointmentId = id;
            this.anchor = button;
            this.$nextTick(() => this.placeWhenReady());
        },

        close() {
            this.openAppointmentId = null;
            this.anchor = null;
            this.openAbove = false;
        },

        placeWhenReady(tries = 0) {
            if (this.openAppointmentId === null || ! this.anchor) {
                return;
            }

            const pop = this.anchor.parentElement?.querySelector('.ops-cal-month__popover');
            if (! pop || (pop.offsetHeight === 0 && tries < 8)) {
                requestAnimationFrame(() => this.placeWhenReady(tries + 1));

                return;
            }

            this.reposition();
        },

        reposition() {
            if (this.openAppointmentId === null || ! this.anchor) {
                return;
            }

            const pop = this.anchor.parentElement?.querySelector('.ops-cal-month__popover');
            if (! pop) {
                return;
            }

            const gap = 4;
            const margin = 8;
            const buttonRect = this.anchor.getBoundingClientRect();
            const height = Math.max(pop.offsetHeight, 1);
            const width = Math.max(pop.offsetWidth, 1);
            const spaceBelow = window.innerHeight - buttonRect.bottom - margin;
            const spaceAbove = buttonRect.top - margin;
            this.openAbove = spaceBelow < height && spaceAbove > spaceBelow;
            let top = this.openAbove
                ? buttonRect.top - height - gap
                : buttonRect.bottom + gap;
            let left = buttonRect.left;
            if (left + width > window.innerWidth - margin) {
                left = Math.max(margin, window.innerWidth - width - margin);
            }
            if (left < margin) {
                left = margin;
            }
            if (top < margin) {
                top = margin;
            }
            if (top + height > window.innerHeight - margin) {
                top = Math.max(margin, window.innerHeight - height - margin);
            }
            this.popTop = Math.round(top);
            this.popLeft = Math.round(left);
        },
    };
}
