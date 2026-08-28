export function arkReviewPrintMenu() {
    return {
        menuOpen: false,
        menuStyle: '',

        init() {
            const releaseListeners = () => {
                document.removeEventListener('click', onDocumentClick);
                window.removeEventListener('scroll', onScroll, true);
            };

            const onDocumentClick = (event) => {
                if (! this.$el.isConnected) {
                    releaseListeners();

                    return;
                }

                if (! this.menuOpen) {
                    return;
                }

                const roots = [
                    this.$refs.printMenuRoot,
                    this.$refs.printMenuPanel,
                ].filter(Boolean);

                for (const root of roots) {
                    if (root.contains(event.target)) {
                        return;
                    }
                }

                this.menuOpen = false;
            };

            const onScroll = (event) => {
                if (! this.$el.isConnected) {
                    releaseListeners();

                    return;
                }

                if (! this.menuOpen) {
                    return;
                }

                const panel = this.$refs.menuPanel;

                if (panel && (panel === event.target || panel.contains(event.target))) {
                    return;
                }

                this.menuOpen = false;
            };

            document.addEventListener('click', onDocumentClick);
            window.addEventListener('scroll', onScroll, true);
        },

        syncMenuPosition() {
            this.$nextTick(() => {
                const trigger = this.$refs.printMenuTrigger;

                if (! trigger) {
                    return;
                }

                const rect = trigger.getBoundingClientRect();
                const top = Math.round(rect.bottom + 4);
                const left = Math.round(rect.left);
                const minWidth = Math.max(Math.round(rect.width), 176);

                this.menuStyle = `top:${top}px;left:${left}px;min-width:${minWidth}px;`;
            });
        },

        toggleMenu() {
            const nextOpen = ! this.menuOpen;
            this.menuOpen = nextOpen;

            if (nextOpen) {
                this.syncMenuPosition();
            }
        },

        closeMenu() {
            this.menuOpen = false;
        },
    };
}
