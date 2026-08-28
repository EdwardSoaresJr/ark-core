export function arkFloatingCommsMenu(config = {}) {
    return {
        menuOpen: false,
        menuStyle: '',
        align: config.align ?? 'left',
        minWidth: config.minWidth ?? 112,
        flipThreshold: config.flipThreshold ?? 220,
        /** Ignore dismiss for a beat after open — board columns often fire scroll on layout. */
        ignoreDismissUntil: 0,

        init() {
            const releaseListeners = () => {
                document.removeEventListener('click', onDocumentClick);
                window.removeEventListener('scroll', onScroll, true);
                window.removeEventListener('resize', onResize);
            };

            const eventInsideMenu = (event) => {
                const roots = [
                    this.$refs.menuRoot,
                    this.$refs.menuPanel,
                ].filter(Boolean);

                for (const root of roots) {
                    if (root === event.target || root.contains(event.target)) {
                        return true;
                    }
                }

                return false;
            };

            const onDocumentClick = (event) => {
                if (! this.$el.isConnected) {
                    releaseListeners();

                    return;
                }

                if (! this.menuOpen || Date.now() < this.ignoreDismissUntil) {
                    return;
                }

                if (eventInsideMenu(event)) {
                    return;
                }

                this.menuOpen = false;
            };

            const onScroll = (event) => {
                if (! this.$el.isConnected) {
                    releaseListeners();

                    return;
                }

                if (! this.menuOpen || Date.now() < this.ignoreDismissUntil) {
                    return;
                }

                // Status list is overflow-y:auto — scrolling options must not dismiss.
                if (eventInsideMenu(event)) {
                    return;
                }

                this.menuOpen = false;
            };

            const onResize = () => {
                if (this.menuOpen) {
                    this.syncMenuPosition();
                }
            };

            document.addEventListener('click', onDocumentClick);
            window.addEventListener('scroll', onScroll, true);
            window.addEventListener('resize', onResize);
        },

        syncMenuPosition() {
            this.$nextTick(() => {
                const trigger = this.$refs.menuTrigger;

                if (! trigger) {
                    return;
                }

                const rect = trigger.getBoundingClientRect();
                const width = Math.max(Math.round(rect.width), this.minWidth);
                const left = this.align === 'right'
                    ? Math.round(rect.right - width)
                    : Math.round(rect.left);
                const roomBelow = window.innerHeight - rect.bottom;

                if (roomBelow < this.flipThreshold) {
                    const bottom = Math.round(window.innerHeight - rect.top + 4);
                    this.menuStyle = `top:auto;right:auto;bottom:${bottom}px;left:${left}px;width:${width}px;min-width:${width}px;`;

                    return;
                }

                const top = Math.round(rect.bottom + 4);
                this.menuStyle = `top:${top}px;right:auto;left:${left}px;width:${width}px;min-width:${width}px;`;
            });
        },

        toggleMenu() {
            const nextOpen = ! this.menuOpen;
            this.menuOpen = nextOpen;

            if (nextOpen) {
                this.ignoreDismissUntil = Date.now() + 250;
                this.syncMenuPosition();
            }
        },

        closeMenu() {
            this.menuOpen = false;
        },
    };
}
