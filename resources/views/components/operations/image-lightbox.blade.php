<div
    x-data="arkImageLightbox()"
    x-cloak
    x-show="open"
    x-transition.opacity.duration.150ms
    @keydown.escape.window="close()"
    class="ops-image-lightbox"
    role="dialog"
    aria-modal="true"
    :aria-label="alt || 'Image preview'"
>
    <button
        type="button"
        class="ops-image-lightbox__backdrop"
        @click="close()"
        aria-label="Close image preview"
    ></button>

    <div class="ops-image-lightbox__frame">
        <div class="ops-image-lightbox__toolbar">
            <p class="ops-image-lightbox__label" x-text="alt || 'Attachment preview'"></p>
            <div class="flex flex-wrap items-center gap-1.5">
                <button
                    type="button"
                    class="ops-image-lightbox__control"
                    @click="zoomOut()"
                    :disabled="scale <= minScale + 0.001"
                    aria-label="Zoom out"
                >
                    −
                </button>
                <button
                    type="button"
                    class="ops-image-lightbox__control ops-image-lightbox__control--wide"
                    @click="fitToView()"
                    x-text="zoomPercentLabel()"
                    aria-label="Reset zoom to fit"
                ></button>
                <button
                    type="button"
                    class="ops-image-lightbox__control"
                    @click="zoomIn()"
                    :disabled="scale >= maxScale - 0.001"
                    aria-label="Zoom in"
                >
                    +
                </button>
                <a
                    :href="src"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="ops-image-lightbox__link"
                    x-show="src"
                >
                    Open original
                </a>
                <button type="button" class="ops-image-lightbox__close" @click="close()">Close</button>
            </div>
        </div>

        <div
            x-ref="viewport"
            class="ops-image-lightbox__viewport"
            @wheel.prevent="onWheel($event)"
            @mousemove.window="onPan($event)"
            @mouseup.window="endPan()"
        >
            <img
                :src="src"
                :alt="alt || 'Attachment preview'"
                class="ops-image-lightbox__image"
                :style="imageTransform()"
                @load="onImageLoad($event)"
                @mousedown.prevent="startPan($event)"
                @click.stop
                draggable="false"
            >
        </div>
    </div>
</div>
