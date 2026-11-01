@props(['ui' => 'legacy'])

@if ($ui === 'tabler')
{{-- fitToView() measures the image at its natural size, so the image keeps max-width: none. --}}
<div
    x-data="arkImageLightbox()"
    x-cloak
    x-show="open"
    x-transition.opacity.duration.150ms
    @keydown.escape.window="close()"
    data-ops-image-lightbox
>
    <div class="modal d-block" role="dialog" aria-modal="true" :aria-label="alt || 'Image preview'" tabindex="-1">
        <button
            type="button"
            class="modal-backdrop show z-0 border-0 p-0"
            @click="close()"
            aria-label="Close image preview"
        ></button>

        <div class="modal-dialog modal-dialog-centered modal-xl" data-bs-theme="dark">
            <div class="modal-content">
                <div class="modal-header flex-wrap gap-2 py-2">
                    <div class="small fw-bold text-truncate flex-fill" x-text="alt || 'Attachment preview'"></div>
                    <div class="btn-list flex-nowrap">
                        <button
                            type="button"
                            class="btn btn-sm btn-icon"
                            @click="zoomOut()"
                            :disabled="scale <= minScale + 0.001"
                            aria-label="Zoom out"
                        >
                            −
                        </button>
                        <button
                            type="button"
                            class="btn btn-sm"
                            @click="fitToView()"
                            x-text="zoomPercentLabel()"
                            aria-label="Reset zoom to fit"
                        ></button>
                        <button
                            type="button"
                            class="btn btn-sm btn-icon"
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
                            class="btn btn-sm"
                            x-show="src"
                        >
                            Open original
                        </a>
                        <button type="button" class="btn btn-sm" @click="close()">Close</button>
                    </div>
                </div>

                <div
                    x-ref="viewport"
                    class="modal-body p-0 d-flex align-items-center justify-content-center overflow-hidden bg-black"
                    style="height: 80vh"
                    @wheel.prevent="onWheel($event)"
                    @mousemove.window="onPan($event)"
                    @mouseup.window="endPan()"
                >
                    <img
                        :src="src"
                        :alt="alt || 'Attachment preview'"
                        class="user-select-none"
                        style="max-width: none; max-height: none; touch-action: none"
                        :style="imageTransform()"
                        @load="onImageLoad($event)"
                        @mousedown.prevent="startPan($event)"
                        @click.stop
                        draggable="false"
                    >
                </div>
            </div>
        </div>
    </div>
</div>
@elseif (! request()->attributes->get('operations.tabler_shell_overlays'))
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
@endif
