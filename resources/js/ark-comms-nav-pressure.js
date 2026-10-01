function communicationsNavLink() {
    return document.querySelector('[data-ops-comms-nav-link]');
}

function navUrgencyClass(summary = {}) {
    if (summary.has_live_calls) {
        return 'ops-rail-link--pressure-live';
    }

    if (Number(summary.since_last_shift_count ?? 0) > 0) {
        return 'ops-rail-link--pressure-shift';
    }

    return 'ops-rail-link--pressure';
}

function playNavChime() {
    try {
        const context = new (window.AudioContext || window.webkitAudioContext)();
        const oscillator = context.createOscillator();
        const gain = context.createGain();

        oscillator.type = 'sine';
        oscillator.frequency.value = 880;
        gain.gain.value = 0.04;
        oscillator.connect(gain);
        gain.connect(context.destination);
        oscillator.start();

        window.setTimeout(() => {
            oscillator.stop();
            context.close();
        }, 180);
    } catch {
        // Audio is optional when the browser blocks autoplay.
    }
}

function syncCommunicationsNav(payload = {}) {
    const link = communicationsNavLink();

    if (! link) {
        return;
    }

    const count = Number(payload.nav_pressure_count ?? payload.count ?? 0);
    const summary = payload.summary ?? {};

    link.classList.remove(
        'ops-rail-link--pressure',
        'ops-rail-link--pressure-live',
        'ops-rail-link--pressure-shift',
    );

    let countEl = link.querySelector('[data-ops-comms-nav-count]');

    if (count > 0) {
        if (! countEl) {
            countEl = document.createElement('span');
            countEl.className = 'ops-rail-link__count';
            countEl.dataset.opsCommsNavCount = '';
            link.querySelector('.ops-rail-link__label')?.insertAdjacentElement('afterend', countEl);
        }

        countEl.textContent = `(${count})`;
        countEl.setAttribute('aria-label', `${count} need attention`);
        countEl.hidden = false;
        link.classList.add(navUrgencyClass(summary));
    } else if (countEl) {
        countEl.remove();
    }
}

export function initCommsNavPressure() {
    const link = communicationsNavLink();

    if (! link) {
        return;
    }

    let lastCount = Number(link.dataset.initialCount ?? 0);

    const onQueueChanged = (event) => {
        const payload = event.detail ?? {};
        const count = Number(payload.nav_pressure_count ?? payload.count ?? 0);

        if (count > lastCount) {
            playNavChime();
        }

        lastCount = count;
        syncCommunicationsNav(payload);
    };

    document.addEventListener('ark:call-queue-changed', onQueueChanged);
}
