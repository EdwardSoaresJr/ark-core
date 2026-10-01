export function arkImageLightbox() {
    return {
        open: false,
        src: '',
        alt: '',
        scale: 1,
        minScale: 1,
        maxScale: 5,
        translateX: 0,
        translateY: 0,
        dragging: false,
        dragStartX: 0,
        dragStartY: 0,
        dragOriginX: 0,
        dragOriginY: 0,
        naturalWidth: 0,
        naturalHeight: 0,

        init() {
            document.addEventListener('click', (event) => {
                const trigger = event.target.closest('[data-ops-lightbox]');

                if (! trigger) {
                    return;
                }

                event.preventDefault();
                this.show(
                    trigger.getAttribute('data-ops-lightbox') ?? '',
                    trigger.getAttribute('data-ops-lightbox-alt') ?? '',
                );
            });
        },

        show(src, alt = '') {
            if (src === '') {
                return;
            }

            this.src = src;
            this.alt = alt;
            this.open = true;
            this.resetTransform();
            document.body.classList.add('overflow-hidden');
        },

        close() {
            this.open = false;
            this.src = '';
            this.alt = '';
            this.resetTransform();
            document.body.classList.remove('overflow-hidden');
        },

        resetTransform() {
            this.scale = 1;
            this.minScale = 1;
            this.translateX = 0;
            this.translateY = 0;
            this.naturalWidth = 0;
            this.naturalHeight = 0;
            this.endPan();
        },

        onImageLoad(event) {
            const img = event.target;

            this.naturalWidth = img.naturalWidth;
            this.naturalHeight = img.naturalHeight;
            this.$nextTick(() => this.fitToView());
        },

        fitToView() {
            const viewport = this.$refs.viewport;

            if (! viewport || this.naturalWidth === 0 || this.naturalHeight === 0) {
                return;
            }

            const rect = viewport.getBoundingClientRect();
            const padding = 16;
            const availableWidth = Math.max(rect.width - padding, 1);
            const availableHeight = Math.max(rect.height - padding, 1);
            const fitScale = Math.min(
                availableWidth / this.naturalWidth,
                availableHeight / this.naturalHeight,
                1,
            );

            this.minScale = fitScale;
            this.scale = fitScale;
            this.translateX = 0;
            this.translateY = 0;
        },

        zoomIn() {
            this.setScale(this.scale * 1.25);
        },

        zoomOut() {
            this.setScale(this.scale / 1.25);
        },

        setScale(next) {
            this.scale = Math.min(this.maxScale, Math.max(this.minScale, next));

            if (this.scale <= this.minScale + 0.001) {
                this.scale = this.minScale;
                this.translateX = 0;
                this.translateY = 0;
            }
        },

        zoomPercentLabel() {
            if (this.minScale <= 0) {
                return '100%';
            }

            return `${Math.round((this.scale / this.minScale) * 100)}%`;
        },

        onWheel(event) {
            if (! this.open) {
                return;
            }

            event.preventDefault();

            const factor = event.deltaY > 0 ? 0.9 : 1.1;
            this.setScale(this.scale * factor);
        },

        canPan() {
            return this.scale > this.minScale + 0.001;
        },

        startPan(event) {
            if (! this.canPan() || event.button !== 0) {
                return;
            }

            this.dragging = true;
            this.dragStartX = event.clientX;
            this.dragStartY = event.clientY;
            this.dragOriginX = this.translateX;
            this.dragOriginY = this.translateY;
        },

        onPan(event) {
            if (! this.dragging) {
                return;
            }

            this.translateX = this.dragOriginX + (event.clientX - this.dragStartX);
            this.translateY = this.dragOriginY + (event.clientY - this.dragStartY);
        },

        endPan() {
            this.dragging = false;
        },

        imageTransform() {
            return {
                transform: `translate(${this.translateX}px, ${this.translateY}px) scale(${this.scale})`,
                transformOrigin: 'center center',
                cursor: this.canPan() ? (this.dragging ? 'grabbing' : 'grab') : 'default',
            };
        },
    };
}
