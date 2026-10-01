/**
 * Cloud Funnel client events → gtag when present, always dataLayer-safe.
 */
window.arkCloudFunnel = {
    track(event, params = {}) {
        const payload = Object.assign({ event_category: 'cloud_funnel' }, params);
        try {
            window.dataLayer = window.dataLayer || [];
            window.dataLayer.push(Object.assign({ event }, payload));
        } catch (_) {}
        if (typeof window.gtag === 'function') {
            window.gtag('event', event, payload);
        }
    },
};

document.addEventListener('click', (e) => {
    const el = e.target.closest('[data-cloud-event]');
    if (!el) return;
    const name = el.getAttribute('data-cloud-event');
    if (!name) return;
    window.arkCloudFunnel.track(name, {
        link_text: (el.textContent || '').trim().slice(0, 80),
        href: el.getAttribute('href') || '',
    });
});
