@php
    use App\Support\Branding\Branding;
@endphp

<div
    x-data="arkLearnGuideModal()"
    x-cloak
    x-show="open"
    x-transition.opacity.duration.150ms
    @keydown.escape.window="close()"
    class="ops-learn-guide-modal"
    role="dialog"
    aria-modal="true"
    :aria-label="title || '{{ Branding::learnName() }} guide'"
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
                <h2 class="ops-learn-guide-modal__title" x-text="title || '{{ Branding::learnName() }}'"></h2>
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
            <p class="ops-learn-guide-modal__footer-note">Preview from ARK — open {{ Branding::learnName() }} for search, print, and full navigation.</p>
            <div class="ops-learn-guide-modal__footer-actions">
                <button type="button" class="ops-learn-guide-modal__secondary" @click="close()">Close</button>
                <a
                    :href="arkademyUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="ops-learn-guide-modal__primary"
                    x-show="arkademyUrl"
                >
                    Open in {{ Branding::learnName() }}
                </a>
            </div>
        </footer>
    </div>
</div>
