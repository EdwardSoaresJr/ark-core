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
    tooltipOpen: false,
    tooltipStyle: '',
    scrollHandler: null,

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

    showTooltip() {
        this.tooltipOpen = true;
        this.$nextTick(() => this.positionTooltip());
        this.attachScroll();
    },

    hideTooltip(event) {
        if (event?.relatedTarget && this.$el.contains(event.relatedTarget)) {
            return;
        }

        this.tooltipOpen = false;
        this.detachScroll();
    },

    positionTooltip() {
        const trigger = this.$refs.trigger;

        if (! trigger) {
            return;
        }

        const rect = trigger.getBoundingClientRect();
        const offset = 7;
        const tooltip = this.$refs.tooltip;
        const height = tooltip?.offsetHeight ?? 280;
        const width = tooltip?.offsetWidth ?? 320;
        let top = rect.bottom + offset;
        let left = rect.left;

        if (top + height > window.innerHeight - 8) {
            top = Math.max(8, rect.top - height - offset);
        }

        if (left + width > window.innerWidth - 8) {
            left = Math.max(8, window.innerWidth - width - 8);
        }

        this.tooltipStyle = `top:${Math.round(top)}px;left:${Math.round(left)}px;`;
    },

    attachScroll() {
        if (this.scrollHandler) {
            return;
        }

        this.scrollHandler = () => {
            if (this.tooltipOpen) {
                this.positionTooltip();
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
            // Clipboard unavailable — hover still exposes the full VIN.
        }
    },
});
