import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

let echoInstance = null;

function readMeta(name) {
    return document.querySelector(`meta[name="${name}"]`)?.content?.trim() ?? '';
}

function reverbAppKey() {
    return readMeta('ark-reverb-app-key') || import.meta.env.VITE_REVERB_APP_KEY || '';
}

function resolveReverbHost() {
    const metaHost = readMeta('ark-reverb-host');

    if (metaHost) {
        return metaHost;
    }

    if (typeof window !== 'undefined' && window.location?.hostname) {
        return window.location.hostname;
    }

    return import.meta.env.VITE_REVERB_HOST || 'localhost';
}

function resolveReverbScheme() {
    const metaScheme = readMeta('ark-reverb-scheme');

    if (metaScheme) {
        return metaScheme;
    }

    if (typeof window !== 'undefined' && window.location?.protocol) {
        return window.location.protocol === 'https:' ? 'https' : 'http';
    }

    return import.meta.env.VITE_REVERB_SCHEME ?? 'https';
}

function resolveReverbPort(scheme) {
    const metaPort = readMeta('ark-reverb-port');

    if (metaPort) {
        return Number(metaPort);
    }

    if (import.meta.env.VITE_REVERB_PORT) {
        return Number(import.meta.env.VITE_REVERB_PORT);
    }

    return scheme === 'https' ? 443 : 80;
}

export function arkEchoEnabled() {
    return Boolean(reverbAppKey());
}

export function getArkEcho() {
    if (! arkEchoEnabled()) {
        return null;
    }

    if (echoInstance) {
        return echoInstance;
    }

    const scheme = resolveReverbScheme();
    const port = resolveReverbPort(scheme);

    echoInstance = new Echo({
        broadcaster: 'reverb',
        key: reverbAppKey(),
        wsHost: resolveReverbHost(),
        wsPort: port,
        wssPort: port,
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/broadcasting/auth',
        auth: {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
        },
    });

    return echoInstance;
}
