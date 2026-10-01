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

        place() {
            if (this.touchMode) {
                return;
            }

            const panel = this.$el.querySelector('.ops-inspect-popover__panel');

            if (! panel) {
                return;
            }

            const footer = document.querySelector('.ops-ro-orientation-header--dock');
            const limit = footer ? footer.getBoundingClientRect().top : window.innerHeight;
            const trigger = this.$el.getBoundingClientRect();
            const gap = 8;
            const previousDisplay = panel.style.display;
            const previousVisibility = panel.style.visibility;

            panel.style.visibility = 'hidden';
            panel.style.display = 'block';
            const height = panel.getBoundingClientRect().height;
            panel.style.display = previousDisplay;
            panel.style.visibility = previousVisibility;

            const roomBelow = limit - trigger.bottom - gap;
            const roomAbove = trigger.top - gap;

            this.$el.classList.toggle('ops-inspect-popover--above', height > roomBelow && roomAbove >= roomBelow);
        },
    };
}
