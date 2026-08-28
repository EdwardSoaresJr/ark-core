export function arkPortalSignature(config = {}) {
    return {
        canvas: null,
        drawing: false,
        hasStroke: false,
        lastX: 0,
        lastY: 0,
        layoutWidth: 0,
        resizeFrame: null,

        init() {
            this.$nextTick(() => {
                this.canvas = this.$refs.signatureCanvas;

                if (! this.canvas) {
                    return;
                }

                this.resizeCanvas();
                window.addEventListener('resize', () => this.scheduleResize());
                window.visualViewport?.addEventListener('resize', () => this.scheduleResize());
            });
        },

        scheduleResize() {
            if (this.resizeFrame !== null) {
                cancelAnimationFrame(this.resizeFrame);
            }

            this.resizeFrame = requestAnimationFrame(() => {
                this.resizeFrame = null;
                this.resizeCanvas();
            });
        },

        resizeCanvas() {
            if (! this.canvas) {
                return;
            }

            const rect = this.canvas.getBoundingClientRect();
            const ratio = window.devicePixelRatio || 1;
            const width = Math.floor(rect.width * ratio);
            const height = Math.floor(rect.height * ratio);

            if (width < 1 || height < 1) {
                return;
            }

            const widthChanged = Math.abs(this.layoutWidth - rect.width) > 1;

            if (! widthChanged && this.canvas.width > 0) {
                return;
            }

            this.layoutWidth = rect.width;

            const snapshot = this.hasStroke ? this.canvas.toDataURL() : null;

            this.canvas.width = width;
            this.canvas.height = height;

            const context = this.canvas.getContext('2d');

            if (! context) {
                return;
            }

            context.setTransform(ratio, 0, 0, ratio, 0, 0);
            context.lineWidth = 2;
            context.lineCap = 'round';
            context.strokeStyle = '#0f172a';

            if (! snapshot) {
                return;
            }

            const image = new Image();
            image.onload = () => {
                context.drawImage(image, 0, 0, rect.width, rect.height);
                this.syncHiddenInput();
            };
            image.src = snapshot;
        },

        pointerPosition(event) {
            const rect = this.canvas.getBoundingClientRect();

            return {
                x: event.clientX - rect.left,
                y: event.clientY - rect.top,
            };
        },

        startDrawing(event) {
            if (! this.canvas) {
                return;
            }

            this.drawing = true;
            const point = this.pointerPosition(event);
            this.lastX = point.x;
            this.lastY = point.y;
        },

        draw(event) {
            if (! this.drawing || ! this.canvas) {
                return;
            }

            const context = this.canvas.getContext('2d');

            if (! context) {
                return;
            }

            const point = this.pointerPosition(event);

            context.beginPath();
            context.moveTo(this.lastX, this.lastY);
            context.lineTo(point.x, point.y);
            context.stroke();

            this.lastX = point.x;
            this.lastY = point.y;
            this.hasStroke = true;
            this.syncHiddenInput();
        },

        stopDrawing() {
            this.drawing = false;
        },

        clear() {
            if (! this.canvas) {
                return;
            }

            const context = this.canvas.getContext('2d');

            if (! context) {
                return;
            }

            context.clearRect(0, 0, this.canvas.width, this.canvas.height);
            this.hasStroke = false;
            this.syncHiddenInput();
        },

        syncHiddenInput() {
            const input = this.$refs.signatureInput;

            if (! input || ! this.canvas || ! this.hasStroke) {
                if (input) {
                    input.value = '';
                }

                return;
            }

            input.value = this.canvas.toDataURL('image/png');
        },

        validateBeforeSubmit(event) {
            if (! config.required) {
                return true;
            }

            if (this.hasStroke) {
                this.syncHiddenInput();

                return true;
            }

            event.preventDefault();
            alert('Sign in the box to confirm your choices.');

            return false;
        },
    };
}
