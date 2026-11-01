export function arkInspectPopover(config = {}) {
    return {
        open: false,
        hover: false,
        touchMode: false,
        above: Boolean(config.above),
        align: config.align === 'end' ? 'end' : 'start',

        init() {
            this.touchMode = window.matchMedia('(hover: none)').matches;
        },

        panel() {
            return this.$el.querySelector('[data-inspect-popover-panel]');
        },

        enter() {
            if (this.touchMode) {
                return;
            }

            this.place();
            this.hover = true;
        },

        leave() {
            this.hover = false;
        },

        toggle(event) {
            if (! this.touchMode || ! this.panel()) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            this.open = ! this.open;
        },

        close() {
            this.open = false;
        },

        panelClasses() {
            if (this.touchMode) {
                return {
                    'offcanvas offcanvas-bottom h-auto overflow-auto p-3 text-start': true,
                    show: this.open,
                };
            }

            return {
                'dropdown-menu p-2 text-start': true,
                'top-100 mt-1': ! this.above,
                'bottom-100 mb-1': this.above,
                'end-0': this.align === 'end',
                'start-0': this.align !== 'end',
                show: this.hover || this.open,
            };
        },

        place() {
            if (this.touchMode) {
                return;
            }

            const panel = this.panel();

            if (! panel) {
                return;
            }

            const footer = document.querySelector('[data-ro-orientation-header]');
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

            this.above = height > roomBelow && roomAbove >= roomBelow;
        },
    };
}
