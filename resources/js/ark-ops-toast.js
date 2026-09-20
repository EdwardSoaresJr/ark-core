const MODE_TOAST_KEY = 'ark_mode_toast';

export function arkOpsToast(message, duration = 2400) {
    if (!message) {
        return;
    }

    let host = document.getElementById('ark-ops-toast-host');

    if (!host) {
        host = document.createElement('div');
        host.id = 'ark-ops-toast-host';
        host.className = 'ark-ops-toast-host';
        host.setAttribute('aria-live', 'polite');
        document.body.appendChild(host);
    }

    const toast = document.createElement('div');
    toast.className = 'ark-ops-toast';
    toast.textContent = message;
    host.appendChild(toast);

    requestAnimationFrame(() => toast.classList.add('is-visible'));

    window.setTimeout(() => {
        toast.classList.remove('is-visible');
        window.setTimeout(() => toast.remove(), 220);
    }, duration);
}

export function queueModeSwitchToast(message) {
    sessionStorage.setItem(MODE_TOAST_KEY, message);
}

export function showModeSwitchToastIfNeeded() {
    const message = sessionStorage.getItem(MODE_TOAST_KEY);

    if (!message) {
        return;
    }

    sessionStorage.removeItem(MODE_TOAST_KEY);
    arkOpsToast(message);
}
