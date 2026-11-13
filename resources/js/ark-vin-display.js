const VIN_PHONETIC = {
    0: 'Zero',
    1: 'One',
    2: 'Two',
    3: 'Three',
    4: 'Four',
    5: 'Five',
    6: 'Six',
    7: 'Seven',
    8: 'Eight',
    9: 'Nine',
    A: 'Alpha',
    B: 'Bravo',
    C: 'Charlie',
    D: 'Delta',
    E: 'Echo',
    F: 'Foxtrot',
    G: 'Golf',
    H: 'Hotel',
    I: 'India',
    J: 'Juliet',
    K: 'Kilo',
    L: 'Lima',
    M: 'Mike',
    N: 'November',
    O: 'Oscar',
    P: 'Papa',
    Q: 'Quebec',
    R: 'Romeo',
    S: 'Sierra',
    T: 'Tango',
    U: 'Uniform',
    V: 'Victor',
    W: 'Whiskey',
    X: 'X-ray',
    Y: 'Yankee',
    Z: 'Zulu',
};

export const vinPhonetic = (char) => VIN_PHONETIC[String(char || '').toUpperCase()] ?? String(char || '').toUpperCase();

export const arkVinDisplay = (vin) => ({
    vin: String(vin || '').toUpperCase().trim(),
    copied: false,
    readbackOpen: false,
    readbackPinned: false,
    sheetMode: false,
    tooltipStyle: '',
    scrollHandler: null,
    outsideHandler: null,
    escapeHandler: null,
    hoverCloseTimer: null,

    init() {
        this.sheetMode = window.matchMedia('(max-width: 767.98px)').matches;
        this._mq = window.matchMedia('(max-width: 767.98px)');
        this._mqListener = (event) => {
            this.sheetMode = event.matches;
            if (this.readbackOpen) {
                this.$nextTick(() => this.positionReadback());
            }
        };
        this._mq.addEventListener?.('change', this._mqListener);
    },

    destroy() {
        this.clearHoverClose();
        this.closeReadback();
        this._mq?.removeEventListener?.('change', this._mqListener);
    },

    isDesktopReadback() {
        return ! window.matchMedia('(max-width: 767.98px)').matches;
    },

    clearHoverClose() {
        if (this.hoverCloseTimer) {
            clearTimeout(this.hoverCloseTimer);
            this.hoverCloseTimer = null;
        }
    },

    onReadbackHoverEnter() {
        if (! this.isDesktopReadback()) {
            return;
        }

        this.clearHoverClose();
        if (! this.readbackOpen) {
            this.openReadback({ pinned: false });
        }
    },

    onReadbackHoverLeave() {
        if (! this.isDesktopReadback() || this.readbackPinned) {
            return;
        }

        this.clearHoverClose();
        // Gap between trigger and body-teleported panel — close only after leave settles.
        this.hoverCloseTimer = setTimeout(() => {
            this.hoverCloseTimer = null;
            if (! this.readbackPinned) {
                this.closeReadback();
            }
        }, 120);
    },

    get suffixLength() {
        return Math.min(8, this.vin.length);
    },

    get prefix() {
        return this.vin.length > this.suffixLength
            ? this.vin.slice(0, -this.suffixLength)
            : '';
    },

    get suffix() {
        return this.vin.slice(-this.suffixLength);
    },

    get suffixStart() {
        return this.vin.length - this.suffixLength;
    },

    get phoneticCells() {
        return this.vin.split('').map((char, index) => ({
            char,
            word: vinPhonetic(char),
            isSuffix: index >= this.suffixStart,
        }));
    },

    get phoneticWmiCells() {
        return this.phoneticCells.filter((_, index) => index < 3);
    },

    get phoneticVdsCells() {
        return this.phoneticCells.filter((_, index) => index >= 3 && index < 9);
    },

    get phoneticVisCells() {
        return this.phoneticCells.filter((_, index) => index >= 9);
    },

    openReadback({ pinned = true } = {}) {
        this.clearHoverClose();
        this.sheetMode = window.matchMedia('(max-width: 767.98px)').matches;
        this.readbackOpen = true;
        this.readbackPinned = pinned;
        this.$nextTick(() => {
            this.positionReadback();
            if (pinned) {
                this.$refs.panel?.focus?.({ preventScroll: true });
            }
        });
        this.attachDismiss();
        this.attachScroll();
    },

    toggleReadback() {
        if (this.readbackOpen && this.readbackPinned) {
            this.closeReadback();
            return;
        }

        // Click pins the card open even if hover already revealed it.
        this.openReadback({ pinned: true });
    },

    closeReadback() {
        this.clearHoverClose();
        this.readbackOpen = false;
        this.readbackPinned = false;
        this.detachDismiss();
        this.detachScroll();
    },

    positionReadback() {
        if (! this.readbackOpen || this.sheetMode) {
            this.tooltipStyle = '';
            return;
        }

        const trigger = this.$refs.readback || this.$refs.trigger;

        if (! trigger) {
            return;
        }

        const rect = trigger.getBoundingClientRect();
        const offset = 8;
        const panel = this.$refs.panel;
        const height = panel?.offsetHeight ?? 280;
        const width = panel?.offsetWidth ?? 320;
        let top = rect.bottom + offset;
        let left = rect.left;

        if (top + height > window.innerHeight - 8) {
            top = Math.max(8, rect.top - height - offset);
        }

        if (left + width > window.innerWidth - 8) {
            left = Math.max(8, window.innerWidth - width - 8);
        }

        // Above sticky RO tabs (1021) and orientation chrome (1022).
        this.tooltipStyle = `top:${Math.round(top)}px;left:${Math.round(left)}px;z-index:1085;`;
    },

    attachScroll() {
        if (this.scrollHandler) {
            return;
        }

        this.scrollHandler = () => {
            if (this.readbackOpen && ! this.sheetMode) {
                this.positionReadback();
            }
        };

        window.addEventListener('scroll', this.scrollHandler, true);
        window.addEventListener('resize', this.scrollHandler);
    },

    detachScroll() {
        if (! this.scrollHandler) {
            return;
        }

        window.removeEventListener('scroll', this.scrollHandler, true);
        window.removeEventListener('resize', this.scrollHandler);
        this.scrollHandler = null;
    },

    attachDismiss() {
        this.detachDismiss();

        this.escapeHandler = (event) => {
            if (event.key === 'Escape' && this.readbackOpen) {
                event.stopPropagation();
                this.closeReadback();
                this.$refs.readback?.focus?.({ preventScroll: true });
            }
        };

        this.outsideHandler = (event) => {
            if (! this.readbackOpen) {
                return;
            }

            const target = event.target;
            if (this.$el.contains(target) || this.$refs.panel?.contains(target) || this.$refs.backdrop?.contains(target)) {
                return;
            }

            this.closeReadback();
        };

        window.addEventListener('keydown', this.escapeHandler, true);
        // Capture so RO tab clicks still dismiss after the panel teleports to body.
        document.addEventListener('pointerdown', this.outsideHandler, true);
    },

    detachDismiss() {
        if (this.escapeHandler) {
            window.removeEventListener('keydown', this.escapeHandler, true);
            this.escapeHandler = null;
        }

        if (this.outsideHandler) {
            document.removeEventListener('pointerdown', this.outsideHandler, true);
            this.outsideHandler = null;
        }
    },

    async copy() {
        if (! this.vin) {
            return;
        }

        try {
            await navigator.clipboard.writeText(this.vin);
            this.copied = true;
            setTimeout(() => {
                this.copied = false;
            }, 1500);
        } catch {
            // Clipboard unavailable — VIN remains visible for manual copy.
        }
    },
});
