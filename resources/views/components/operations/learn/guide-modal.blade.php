@props(['ui' => 'legacy'])

@php
    use App\Ark\Runtime\DemoInstall;
    use App\Support\Branding\Branding;

    $guideProductName = DemoInstall::isDemo() ? 'Guide' : Branding::learnName();
@endphp

@if ($ui === 'tabler')
<div
    x-data="arkLearnGuideModal()"
    x-cloak
    x-show="open"
    x-transition.opacity.duration.150ms
    @keydown.escape.window="close()"
    data-ark-learn-guide-modal
>
    <div class="modal d-block" role="dialog" aria-modal="true" :aria-label="title || '{{ $guideProductName }} guide'" tabindex="-1">
        <button
            type="button"
            class="modal-backdrop show z-0 border-0 p-0"
            @click="close()"
            aria-label="Close guide preview"
        ></button>

        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
            <div class="modal-content">
                <div class="modal-header align-items-start">
                    <div class="overflow-hidden">
                        <div class="subheader" x-show="sectionLabel" x-text="sectionLabel"></div>
                        <h2 class="modal-title" x-text="title || '{{ $guideProductName }}'"></h2>
                        <div class="small text-secondary" x-show="summary" x-text="summary"></div>
                    </div>
                    <button type="button" class="btn-close" @click="close()" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="text-secondary" x-show="loading">
                        <span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Loading guide…
                    </div>
                    <div class="alert alert-danger mb-0" role="alert" x-show="error" x-text="error"></div>
                    {{-- Guide previews are ARKademy article markup, which stays on app.css. --}}
                    <div
                        class="ark-legacy bg-white text-dark rounded p-3 ops-learn-prose ops-learn-guide-modal__prose"
                        x-show="! loading && ! error && html"
                        x-html="html"
                    ></div>
                </div>

                <div class="modal-footer">
                    @unless (DemoInstall::isDemo())
                        <div class="small text-secondary me-auto">Preview from ARK - open {{ Branding::learnName() }} for search, print, and full navigation.</div>
                    @endunless
                    <button type="button" class="btn" @click="close()">Close</button>
                    @unless (DemoInstall::isDemo())
                        <a
                            :href="arkademyUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="btn btn-primary"
                            x-show="arkademyUrl"
                        >
                            Open in {{ Branding::learnName() }}
                        </a>
                    @endunless
                </div>
            </div>
        </div>
    </div>
</div>
@else
<div
    x-data="arkLearnGuideModal()"
    x-cloak
    x-show="open"
    x-transition.opacity.duration.150ms
    @keydown.escape.window="close()"
    class="ops-learn-guide-modal"
    role="dialog"
    aria-modal="true"
    :aria-label="title || '{{ $guideProductName }} guide'"
>
    <button
        type="button"
        class="ops-learn-guide-modal__backdrop"
        @click="close()"
        aria-label="Close guide preview"
    ></button>

    <div class="ops-learn-guide-modal__dialog">
        <header class="ops-learn-guide-modal__header">
            <div class="ops-learn-guide-modal__heading min-w-0">
                <p class="ops-learn-guide-modal__eyebrow" x-show="sectionLabel" x-text="sectionLabel"></p>
                <h2 class="ops-learn-guide-modal__title" x-text="title || '{{ $guideProductName }}'"></h2>
                <p class="ops-learn-guide-modal__summary" x-show="summary" x-text="summary"></p>
            </div>
            <button type="button" class="ops-learn-guide-modal__close" @click="close()">Close</button>
        </header>

        <div class="ops-learn-guide-modal__body">
            <div class="ops-learn-guide-modal__loading" x-show="loading">Loading guide…</div>
            <div class="ops-learn-guide-modal__error" x-show="error" x-text="error"></div>
            <div
                class="ops-learn-prose ops-learn-guide-modal__prose"
                x-show="! loading && ! error && html"
                x-html="html"
            ></div>
        </div>

        <footer class="ops-learn-guide-modal__footer">
            @unless (DemoInstall::isDemo())
                <p class="ops-learn-guide-modal__footer-note">Preview from ARK - open {{ Branding::learnName() }} for search, print, and full navigation.</p>
            @endunless
            <div class="ops-learn-guide-modal__footer-actions">
                <button type="button" class="ops-learn-guide-modal__secondary" @click="close()">Close</button>
                @unless (DemoInstall::isDemo())
                    <a
                        :href="arkademyUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="ops-learn-guide-modal__primary"
                        x-show="arkademyUrl"
                    >
                        Open in {{ Branding::learnName() }}
                    </a>
                @endunless
            </div>
        </footer>
    </div>
</div>
@endif
