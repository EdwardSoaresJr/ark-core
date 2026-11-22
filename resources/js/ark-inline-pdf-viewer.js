/**
 * In-page PDF viewer for the RO workspace. Triggers are plain new-tab links ([data-ark-pdf-preview])
 * that this upgrades to open the shared modal; with no viewer on the page they keep working as links.
 */
export function initInlinePdfViewer() {
    if (window.__arkInlinePdfViewerReady) {
        return;
    }

    window.__arkInlinePdfViewerReady = true;

    const viewer = () => document.querySelector('[data-ark-inline-pdf-viewer]');
    const part = (root, name) => root.querySelector(`[data-pdf-viewer-${name}]`);

    const withBust = (url) => `${url}${url.includes('?') ? '&' : '?'}t=${Date.now()}`;

    const open = (trigger) => {
        const root = viewer();

        if (! root) {
            return false;
        }

        const data = trigger.dataset;
        const bust = data.pdfCacheBust !== undefined;
        const viewUrl = bust ? withBust(data.pdfViewUrl) : data.pdfViewUrl;
        const newTabUrl = bust ? withBust(data.pdfNewTabUrl) : data.pdfNewTabUrl;
        const frame = part(root, 'frame');
        const nativePdf = navigator.pdfViewerEnabled !== false;

        part(root, 'title').textContent = data.pdfTitle || 'Document';
        part(root, 'download').href = data.pdfDownloadUrl;
        part(root, 'new-tab').href = newTabUrl;
        part(root, 'fallback-link').href = newTabUrl;
        part(root, 'fallback').hidden = nativePdf;
        frame.hidden = ! nativePdf;
        frame.title = `${data.pdfTitle || 'Document'} preview`;
        frame.src = nativePdf ? viewUrl : 'about:blank';
        root.dataset.pdfNewTabUrl = newTabUrl;
        root.__arkReturnFocus = trigger.isConnected ? trigger : null;

        // Tabler's Bootstrap data API opens the modal; the bundle does not expose window.bootstrap.
        const opener = document.createElement('button');
        opener.type = 'button';
        opener.hidden = true;
        opener.dataset.bsToggle = 'modal';
        opener.dataset.bsTarget = `#${root.id}`;
        document.body.append(opener);
        opener.click();
        opener.remove();

        return true;
    };

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest?.('[data-ark-pdf-preview]');

        if (! trigger || event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) {
            return;
        }

        if (open(trigger)) {
            event.preventDefault();
        }
    });

    document.addEventListener('click', (event) => {
        const button = event.target.closest?.('[data-pdf-viewer-print]');
        const root = viewer();

        if (! button || ! root) {
            return;
        }

        const frame = part(root, 'frame');

        try {
            frame.contentWindow.focus();
            frame.contentWindow.print();
        } catch (error) {
            window.open(root.dataset.pdfNewTabUrl, '_blank', 'noopener');
        }
    });

    document.addEventListener('hidden.bs.modal', (event) => {
        const root = event.target;

        if (! root.matches?.('[data-ark-inline-pdf-viewer]')) {
            return;
        }

        part(root, 'frame').src = 'about:blank';
        root.__arkReturnFocus?.focus?.();
    });

    // Historical deep links land here as ?view_document=<id>. Wait for load so Tabler's modal handler is registered.
    const wanted = new URLSearchParams(window.location.search).get('view_document');

    if (wanted) {
        const openWanted = () => {
            const trigger = document.querySelector(`[data-ark-pdf-preview][data-document-id="${CSS.escape(wanted)}"]`);

            if (trigger) {
                open(trigger);
            }
        };

        if (document.readyState === 'complete') {
            openWanted();
        } else {
            window.addEventListener('load', openWanted, { once: true });
        }
    }
}
