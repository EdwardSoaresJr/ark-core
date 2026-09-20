export function arkInspectPopover() {
    return {
        open: false,
        touchMode: false,

        init() {
            this.touchMode = window.matchMedia('(hover: none)').matches;
        },

        toggle(event) {
            if (! this.touchMode || ! this.$el.querySelector('.ops-inspect-popover__panel')) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            this.open = ! this.open;
        },

        close() {
            this.open = false;
        },
    };
}
