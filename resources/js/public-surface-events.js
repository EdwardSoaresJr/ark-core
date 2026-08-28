const ALLOWED_CLIENT_EVENTS = new Set([
    'surface_viewed',
    'lead_started',
    'call_clicked',
    'text_clicked',
    'common_problem_link_clicked',
]);

function publicSurfaceContextFromForm() {
    const form = document.querySelector('#tell-the-shop form[data-public-surface-page]');

    if (!form) {
        return null;
    }

    return {
        page: form.dataset.publicSurfacePage || '',
        variant: form.dataset.publicSurfaceVariant || '',
        placement: form.dataset.publicSurfacePlacement || '',
    };
}

function publicSurfaceContextFromLink(element) {
    return {
        page: element.dataset.publicSurfacePage || '',
        source: element.dataset.publicSurfaceSource || '',
        target: element.dataset.publicSurfaceTarget || '',
    };
}

function appendPublicSurfaceContext(formData, context) {
    if (!context?.page) {
        return;
    }

    formData.append('page', context.page);

    if (context.variant) {
        formData.append('variant', context.variant);
    }

    if (context.placement) {
        formData.append('placement', context.placement);
    }

    if (context.source) {
        formData.append('source', context.source);
    }

    if (context.target) {
        formData.append('target', context.target);
    }
}

function sendPublicSurfaceEvent(formData) {
    const config = window.__publicSurfaceEvents;

    if (!config?.endpoint) {
        return;
    }

    if (typeof navigator.sendBeacon === 'function' && navigator.sendBeacon(config.endpoint, formData)) {
        return;
    }

    fetch(config.endpoint, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': config.csrf,
            Accept: 'application/json',
        },
        body: formData,
        credentials: 'same-origin',
        keepalive: true,
    }).catch(() => {});
}

function recordPublicSurfaceEvent(event, context = null) {
    const config = window.__publicSurfaceEvents;

    if (!config?.endpoint || !ALLOWED_CLIENT_EVENTS.has(event)) {
        return;
    }

    const formData = new FormData();
    formData.append('event', event);
    formData.append('_token', config.csrf);
    appendPublicSurfaceContext(formData, context ?? publicSurfaceContextFromForm());

    sendPublicSurfaceEvent(formData);
}

function recordCommonProblemLinkClick(element) {
    recordPublicSurfaceEvent('common_problem_link_clicked', publicSurfaceContextFromLink(element));
}

function initPublicSurfaceEvents() {
    const config = window.__publicSurfaceEvents;

    if (!config?.endpoint) {
        return;
    }

    recordPublicSurfaceEvent('surface_viewed', { page: document.body.dataset.publicSurfacePage || 'homepage' });

    document.querySelectorAll('[data-public-surface-call]').forEach((element) => {
        element.addEventListener('click', () => recordPublicSurfaceEvent('call_clicked'));
    });

    document.querySelectorAll('[data-public-surface-text]').forEach((element) => {
        element.addEventListener('click', () => recordPublicSurfaceEvent('text_clicked'));
    });

    document.querySelectorAll('[data-public-surface-common-problem]').forEach((element) => {
        element.addEventListener('click', () => recordCommonProblemLinkClick(element));
    });

    const concern = document.getElementById('concern');

    if (concern) {
        concern.addEventListener(
            'focus',
            () => recordPublicSurfaceEvent('lead_started'),
            { once: true },
        );
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPublicSurfaceEvents);
} else {
    initPublicSurfaceEvents();
}
