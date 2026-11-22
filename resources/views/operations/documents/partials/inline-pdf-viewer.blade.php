{{-- One in-page PDF viewer, driven by [data-ark-pdf-preview] triggers (resources/js/ark-inline-pdf-viewer.js). Same iframe pattern as the uploaded-document viewer. --}}
<div class="modal modal-blur fade" id="ark-inline-pdf-viewer" tabindex="-1" aria-labelledby="ark-inline-pdf-viewer-title" aria-hidden="true" data-ark-inline-pdf-viewer>
    <div class="modal-dialog modal-xl modal-dialog-centered modal-fullscreen-md-down" role="document">
        <div class="modal-content" style="height: min(92vh, 60rem)">
            <div class="modal-header d-print-none">
                <h2 class="modal-title h3" id="ark-inline-pdf-viewer-title" data-pdf-viewer-title>Document</h2>
                <div class="btn-list ms-auto me-3 flex-nowrap" role="group" aria-label="Document commands">
                    <button type="button" class="btn btn-sm" data-pdf-viewer-print>
                        <x-tabler.icon name="printer" />
                        <span class="d-none d-sm-inline">Print</span>
                    </button>
                    <a href="#" class="btn btn-sm" data-pdf-viewer-download>
                        <x-tabler.icon name="arrow-down" />
                        <span class="d-none d-sm-inline">Download</span>
                    </a>
                    <a href="#" class="btn btn-sm" target="_blank" rel="noopener" data-pdf-viewer-new-tab>
                        <x-tabler.icon name="external-link" />
                        <span class="d-none d-sm-inline">Open in new tab</span>
                    </a>
                </div>
                <button type="button" class="btn-close ms-0" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0 d-flex flex-column">
                <div class="alert alert-warning m-3 mb-0" role="alert" data-pdf-viewer-fallback hidden>
                    This browser can't show PDFs inside the page.
                    <a href="#" target="_blank" rel="noopener" data-pdf-viewer-fallback-link>Open the PDF in a new tab</a>
                    or use Download.
                </div>
                <iframe src="about:blank" title="Document preview" class="d-block w-100 border-0 flex-grow-1 bg-white" data-pdf-viewer-frame></iframe>
            </div>
            <div class="modal-footer d-print-none justify-content-between py-2">
                <span class="small text-secondary">Preview only. Nothing is sent or changed.</span>
                <button type="button" class="btn btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
