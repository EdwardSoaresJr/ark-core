export function arkFeaturedMediaGallery(items = [], startIndex = 0) {
    return {
        items: Array.isArray(items) ? items : [],
        open: false,
        index: startIndex,
        touchStartX: null,

        init() {
            this.index = this.clampIndex(startIndex);
        },

        clampIndex(value) {
            if (this.items.length === 0) {
                return 0;
            }

            return Math.max(0, Math.min(value, this.items.length - 1));
        },

        current() {
            return this.items[this.index] ?? null;
        },

        hasMultiple() {
            return this.items.length > 1;
        },

        openAt(index) {
            if (this.items.length === 0) {
                return;
            }

            this.index = this.clampIndex(index);
            this.open = true;
            document.body.classList.add('overflow-hidden');
        },

        close() {
            this.open = false;
            this.touchStartX = null;
            document.body.classList.remove('overflow-hidden');
        },

        previous() {
            if (! this.hasMultiple()) {
                return;
            }

            this.index = this.index === 0 ? this.items.length - 1 : this.index - 1;
        },

        next() {
            if (! this.hasMultiple()) {
                return;
            }

            this.index = this.index === this.items.length - 1 ? 0 : this.index + 1;
        },

        onKeydown(event) {
            if (! this.open) {
                return;
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                this.close();

                return;
            }

            if (event.key === 'ArrowLeft') {
                event.preventDefault();
                this.previous();

                return;
            }

            if (event.key === 'ArrowRight') {
                event.preventDefault();
                this.next();
            }
        },

        onTouchStart(event) {
            if (! this.open || event.touches.length !== 1) {
                return;
            }

            this.touchStartX = event.touches[0].clientX;
        },

        onTouchEnd(event) {
            if (this.touchStartX === null || event.changedTouches.length !== 1) {
                return;
            }

            const delta = event.changedTouches[0].clientX - this.touchStartX;
            this.touchStartX = null;

            if (Math.abs(delta) < 48) {
                return;
            }

            if (delta > 0) {
                this.previous();
            } else {
                this.next();
            }
        },

        counterLabel() {
            if (! this.hasMultiple()) {
                return '';
            }

            return `${this.index + 1} / ${this.items.length}`;
        },
    };
}
