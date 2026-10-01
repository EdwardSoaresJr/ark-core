export function initWorkstationPresence() {
    document.querySelectorAll('[data-ark-workstation-change-pin]').forEach((button) => {
        if (button.dataset.bound === '1') {
            return;
        }

        button.dataset.bound = '1';

        button.addEventListener('click', () => {
            const presenceRoot = document.querySelector('[data-ark-workstation-presence]');

            if (presenceRoot?.dataset.initialized === '1') {
                presenceRoot.dispatchEvent(new CustomEvent('ark-workstation:change-pin'));

                return;
            }

            const profileUrl = button.dataset.profilePinUrl ?? '';

            if (profileUrl !== '') {
                window.location.href = profileUrl;
            }
        });
    });

    const root = document.querySelector('[data-ark-workstation-presence]');

    if (!root || root.dataset.initialized === '1') {
        return;
    }

    root.dataset.initialized = '1';

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const staffUrl = root.dataset.staffUrl ?? '';
    const unlockUrl = root.dataset.unlockUrl ?? '';
    const lockUrl = root.dataset.lockUrl ?? '';
    const bindUrl = root.dataset.bindUrl ?? '';
    const bindDismissUrl = root.dataset.bindDismissUrl ?? '';
    const pinStoreUrl = root.dataset.pinStoreUrl ?? '';
    const pinUpdateUrl = root.dataset.pinUpdateUrl ?? '';
    const currentUserId = root.dataset.currentUserId ?? '';

    const pinInput = () => root.querySelector('[data-ws-pin-input]');

    const defaultPresenceAccent = () => (
        getComputedStyle(document.documentElement).getPropertyValue('--ops-accent-500').trim()
        || '#0099cc'
    );

    const applyPresenceAccent = (hex) => {
        const color = typeof hex === 'string' && hex.trim() !== '' ? hex.trim() : defaultPresenceAccent();
        root.style.setProperty('--ws-presence-accent-500', color);
    };

    const resetPresenceAccent = () => {
        root.style.removeProperty('--ws-presence-accent-500');
    };

    const accentForStaffMember = (member) => member?.avatar_color ?? defaultPresenceAccent();

    const state = {
        open: false,
        overlay: null,
        selectedUserId: null,
        pin: '',
        error: '',
        loading: false,
        staff: [],
        staffSearch: '',
        staffLoaded: false,
        suggestedUserId: null,
    };

    const pinEligibleStaff = () => (
        Array.isArray(state.staff)
            ? state.staff.filter((member) => member.has_pin)
            : []
    );

    const pinEligibleFilteredStaff = () => {
        const query = state.staffSearch.trim().toLowerCase();

        if (query === '') {
            return pinEligibleStaff();
        }

        return pinEligibleStaff().filter((member) => (
            String(member.name).toLowerCase().includes(query)
        ));
    };

    const resolveOperatorSelection = () => {
        if (state.selectedUserId) {
            return state.selectedUserId;
        }

        const filtered = pinEligibleFilteredStaff();

        if (filtered.length === 1) {
            return filtered[0].id;
        }

        const query = state.staffSearch.trim().toLowerCase();

        if (query !== '') {
            const exactMatches = pinEligibleStaff().filter((member) => (
                String(member.name).toLowerCase() === query
            ));

            if (exactMatches.length === 1) {
                return exactMatches[0].id;
            }
        }

        if (currentUserId) {
            const self = pinEligibleStaff().find(
                (member) => String(member.id) === String(currentUserId),
            );

            if (self) {
                return self.id;
            }
        }

        if (state.suggestedUserId) {
            const suggested = pinEligibleStaff().find(
                (member) => String(member.id) === String(state.suggestedUserId),
            );

            if (suggested) {
                return suggested.id;
            }
        }

        return null;
    };

    const ensureOperatorSelected = () => {
        const resolved = resolveOperatorSelection();

        if (!resolved) {
            return false;
        }

        if (String(state.selectedUserId) !== String(resolved)) {
            state.selectedUserId = resolved;
            const member = state.staff.find(
                (entry) => String(entry.id) === String(resolved),
            );

            if (member) {
                applyPresenceAccent(accentForStaffMember(member));
            }

            renderStaffList();
        }

        return true;
    };

    const setPinPadEnabled = (enabled) => {
        pinInput()?.toggleAttribute('disabled', !enabled);

        root.querySelectorAll('[data-ws-digit]').forEach((button) => {
            button.toggleAttribute('disabled', !enabled);
        });

        root.querySelectorAll('[data-ws-action="clear-pin"], [data-ws-action="back-pin"]').forEach((button) => {
            button.toggleAttribute('disabled', !enabled);
        });
    };

    const operatorSelectionError = () => {
        if (!state.staffLoaded) {
            return 'Loading advisors…';
        }

        if (pinEligibleStaff().length === 0) {
            return 'Sign into ARK on this computer once, then return here to unlock.';
        }

        if (state.staffSearch.trim() !== '' && pinEligibleFilteredStaff().length === 0) {
            return 'No advisor matches that name.';
        }

        if (pinEligibleFilteredStaff().length > 1) {
            return 'Choose an advisor.';
        }

        return 'Choose an advisor.';
    };

    const overlays = () => root.querySelectorAll('[data-ws-overlay]');

    const dispatchPresenceGate = (active) => {
        document.dispatchEvent(new CustomEvent('ark:workstation-presence-gate', {
            detail: { active },
            bubbles: true,
        }));
    };

    const presenceGateActive = () => (
        root.dataset.needsBinding === '1'
        || root.dataset.locked === '1'
        || root.dataset.needsPinSetup === '1'
        || root.classList.contains('ws-presence--open')
    );

    const renderPinDots = () => {
        const dots = root.querySelector('[data-ws-pin-dots]');
        const input = pinInput();

        if (dots) {
            dots.textContent = '•'.repeat(state.pin.length).padEnd(4, '○');
        }

        if (input && input.value !== state.pin) {
            input.value = state.pin;
        }
    };

    const focusPinCapture = () => {
        const input = pinInput();

        if (input) {
            input.focus({ preventScroll: true });
        }
    };

    const addPinDigit = (digit) => {
        if (state.loading || !state.staffLoaded) {
            if (!state.staffLoaded) {
                setError(operatorSelectionError());
            }

            return;
        }

        if (!ensureOperatorSelected()) {
            setError(operatorSelectionError());

            return;
        }

        if (state.pin.length >= 4) {
            return;
        }

        state.pin += digit;
        renderPinDots();

        if (state.pin.length === 4) {
            submitUnlock();
        }
    };

    const clearPin = () => {
        state.pin = '';
        renderPinDots();
        setError('');
        focusPinCapture();
    };

    const backPinDigit = () => {
        if (state.pin.length === 0) {
            return;
        }

        state.pin = state.pin.slice(0, -1);
        renderPinDots();
        setError('');
    };

    const selectDefaultOperator = () => {
        if (!ensureOperatorSelected()) {
            resetPresenceAccent();

            return;
        }

        focusPinCapture();
    };

    const setError = (message) => {
        state.error = message;
        const errorEl = root.querySelector('[data-ws-error]');

        if (errorEl) {
            errorEl.textContent = message;
            errorEl.hidden = message === '';
        }
    };

    const loadStaff = async () => {
        if (staffUrl === '') {
            state.staffLoaded = true;

            return;
        }

        state.staffLoaded = false;
        setPinPadEnabled(false);

        const response = await fetch(staffUrl, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            state.staff = [];
            state.staffLoaded = true;
            renderStaffList();
            setPinPadEnabled(true);

            return;
        }

        const payload = await response.json();
        state.staff = Array.isArray(payload.staff) ? payload.staff : [];
        state.suggestedUserId = payload.suggested_user_id ?? null;
        state.staffLoaded = true;
        renderStaffList();
        selectDefaultOperator();
        setPinPadEnabled(true);
    };

    const filteredStaff = () => {
        const query = state.staffSearch.trim().toLowerCase();

        if (query === '') {
            return state.staff;
        }

        return state.staff.filter((member) => String(member.name).toLowerCase().includes(query));
    };

    const renderStaffEmptyMessage = () => {
        const emptyEl = root.querySelector('[data-ws-staff-empty]');

        if (!emptyEl) {
            return;
        }

        if (!state.staffLoaded) {
            emptyEl.textContent = 'Loading advisors…';
            emptyEl.hidden = false;

            return;
        }

        if (pinEligibleStaff().length === 0) {
            emptyEl.textContent = 'Sign into ARK on this computer once, then return here to unlock.';
            emptyEl.hidden = false;

            return;
        }

        if (state.staffSearch.trim() !== '' && filteredStaff().length === 0) {
            emptyEl.textContent = 'No advisors match.';
            emptyEl.hidden = false;

            return;
        }

        emptyEl.hidden = true;
    };

    const renderStaffList = () => {
        const list = root.querySelector('[data-ws-staff-list]');

        if (!list) {
            return;
        }

        const members = filteredStaff();

        list.innerHTML = '';

        renderStaffEmptyMessage();

        members.forEach((member) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'ws-presence-staff-tile';
            button.dataset.userId = String(member.id);

            const avatar = document.createElement('span');
            avatar.className = 'ws-presence-staff-tile__avatar';
            avatar.textContent = member.initials ?? member.name?.charAt(0) ?? '?';
            avatar.style.backgroundColor = member.avatar_color ?? defaultPresenceAccent();

            const name = document.createElement('span');
            name.className = 'ws-presence-staff-tile__name';
            name.textContent = member.name;

            button.appendChild(avatar);
            button.appendChild(name);

            if (String(state.selectedUserId) === String(member.id)) {
                button.classList.add('ws-presence-staff-tile--selected');
            }

            if (!member.has_pin) {
                button.classList.add('ws-presence-staff-tile--no-pin');
                button.title = 'No station PIN configured';
            }

            button.addEventListener('click', () => {
                state.selectedUserId = member.id;
                applyPresenceAccent(accentForStaffMember(member));
                state.pin = '';
                renderPinDots();
                setError('');
                renderStaffList();
                focusPinCapture();
            });

            list.appendChild(button);
        });
    };

    const showOverlay = (name) => {
        state.overlay = name;
        state.open = true;
        overlays().forEach((overlay) => {
            overlay.hidden = overlay.dataset.wsOverlay !== name;
        });
        root.classList.add('ws-presence--open');
        dispatchPresenceGate(true);

        if (name === 'unlock') {
            state.selectedUserId = null;
            resetPresenceAccent();
            state.pin = '';
            state.staffSearch = '';
            state.staffLoaded = false;
            const searchInput = root.querySelector('[data-ws-staff-search]');

            if (searchInput instanceof HTMLInputElement) {
                searchInput.value = '';
            }

            setError('');
            setPinPadEnabled(false);
            renderPinDots();
            renderStaffList();
            loadStaff().then(() => {
                if (!ensureOperatorSelected()) {
                    setError('');
                }

                focusPinCapture();
            });
        }
    };

    const closeOverlay = () => {
        if (root.dataset.locked === '1' && state.overlay === 'unlock') {
            return;
        }

        if (root.dataset.needsPinSetup === '1' && state.overlay === 'create-pin') {
            return;
        }

        state.open = false;
        state.overlay = null;
        overlays().forEach((overlay) => {
            overlay.hidden = true;
        });
        root.classList.remove('ws-presence--open');

        if (! presenceGateActive()) {
            dispatchPresenceGate(false);
        }
    };

    const submitUnlock = async () => {
        if (state.pin.length !== 4 || unlockUrl === '') {
            setError('Enter a 4-digit PIN.');

            return;
        }

        if (!state.staffLoaded) {
            await loadStaff();
        }

        if (!ensureOperatorSelected()) {
            setError(operatorSelectionError());

            return;
        }

        state.loading = true;
        setError('');

        try {
            const response = await fetch(unlockUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    user_id: state.selectedUserId,
                    pin: state.pin,
                }),
            });

            if (!response.ok) {
                const payload = await response.json().catch(() => ({}));
                setError(payload?.message ?? payload?.errors?.pin?.[0] ?? 'Could not sign in to this station.');

                return;
            }

            window.location.reload();
        } catch {
            setError('Could not sign in to this station.');
        } finally {
            state.loading = false;
        }
    };

    const submitLock = async () => {
        if (lockUrl === '') {
            return;
        }

        await fetch(lockUrl, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            credentials: 'same-origin',
        });

        window.location.reload();
    };

    document.querySelectorAll('[data-ark-workstation-switch]').forEach((button) => {
        button.addEventListener('click', () => showOverlay('switch-station'));
    });

    root.querySelector('[data-ws-staff-search]')?.addEventListener('input', (event) => {
        if (event.currentTarget instanceof HTMLInputElement) {
            state.staffSearch = event.currentTarget.value;
            renderStaffList();

            if (ensureOperatorSelected()) {
                setError('');
                focusPinCapture();
            }
        }
    });

    root.querySelectorAll('[data-ws-digit]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            addPinDigit(button.dataset.wsDigit ?? '');
            focusPinCapture();
        });
    });

    root.querySelector('[data-ws-pin-dots]')?.addEventListener('click', () => {
        focusPinCapture();
    });

    root.querySelector('.ws-presence-pin__entry')?.addEventListener('click', () => {
        focusPinCapture();
    });

    pinInput()?.addEventListener('input', (event) => {
        if (state.overlay !== 'unlock') {
            return;
        }

        const input = event.currentTarget;
        const digits = String(input.value).replace(/\D/g, '').slice(0, 4);
        input.value = digits;
        state.pin = digits;
        renderPinDots();

        if (!state.staffLoaded) {
            setError(operatorSelectionError());

            return;
        }

        if (!ensureOperatorSelected()) {
            setError(operatorSelectionError());

            return;
        }

        setError('');

        if (digits.length === 4) {
            submitUnlock();
        }
    });

    root.addEventListener('keydown', (event) => {
        if (state.overlay !== 'unlock' || state.loading) {
            return;
        }

        const target = event.target;

        if (target instanceof HTMLInputElement && target.matches('[data-ws-pin-input]')) {
            return;
        }

        if (
            target instanceof HTMLInputElement
            || target instanceof HTMLTextAreaElement
            || target instanceof HTMLSelectElement
        ) {
            return;
        }

        if (event.key === 'Backspace') {
            if (state.pin.length === 0) {
                return;
            }

            event.preventDefault();
            state.pin = state.pin.slice(0, -1);
            renderPinDots();

            return;
        }

        if (/^\d$/.test(event.key)) {
            event.preventDefault();
            addPinDigit(event.key);
        }
    });

    root.querySelector('[data-ws-action="clear-pin"]')?.addEventListener('click', clearPin);

    root.querySelector('[data-ws-action="back-pin"]')?.addEventListener('click', (event) => {
        event.preventDefault();
        backPinDigit();
        focusPinCapture();
    });

    root.querySelectorAll('[data-ws-action="close-overlay"]').forEach((button) => {
        button.addEventListener('click', closeOverlay);
    });

    root.querySelector('[data-ws-bind-form]')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        const workstationId = form.querySelector('[name="workstation_id"]')?.value;

        if (!workstationId || bindUrl === '') {
            return;
        }

        const body = new FormData();
        body.append('workstation_id', workstationId);
        body.append('_token', csrf);

        await fetch(bindUrl, {
            method: 'POST',
            body,
            credentials: 'same-origin',
        });

        window.location.reload();
    });

    root.querySelector('[data-ws-switch-station-form]')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        const workstationId = form.querySelector('[name="workstation_id"]:checked')?.value;

        if (!workstationId || bindUrl === '') {
            return;
        }

        const body = new FormData();
        body.append('workstation_id', workstationId);
        body.append('_token', csrf);

        await fetch(bindUrl, {
            method: 'POST',
            body,
            credentials: 'same-origin',
        });

        window.location.reload();
    });

    root.querySelector('[data-ws-action="dismiss-bind"]')?.addEventListener('click', async () => {
        if (bindDismissUrl === '') {
            root.classList.remove('ws-presence--bind');
            root.querySelector('[data-ws-bind-panel]')?.remove();

            return;
        }

        const body = new FormData();
        body.append('_token', csrf);

        await fetch(bindDismissUrl, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body,
            credentials: 'same-origin',
        });

        root.classList.remove('ws-presence--bind');
        root.querySelector('[data-ws-bind-panel]')?.remove();
    });

    root.querySelectorAll('[data-ws-pin-field]').forEach((field) => {
        field.addEventListener('input', (event) => {
            const input = event.currentTarget;
            input.value = String(input.value).replace(/\D/g, '').slice(0, 4);
        });
    });

    root.querySelector('[data-ws-create-pin-form]')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        const errorEl = root.querySelector('[data-ws-pin-setup-error]');

        if (pinStoreUrl === '') {
            return;
        }

        const password = form.querySelector('[name="password"]')?.value ?? '';
        const pin = form.querySelector('[name="pin"]')?.value ?? '';
        const pinConfirmation = form.querySelector('[name="pin_confirmation"]')?.value ?? '';

        if (errorEl) {
            errorEl.hidden = true;
            errorEl.textContent = '';
        }

        try {
            const response = await fetch(pinStoreUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    password,
                    pin,
                    pin_confirmation: pinConfirmation,
                }),
            });

            if (!response.ok) {
                const payload = await response.json().catch(() => ({}));
                const message = payload?.message
                    ?? payload?.errors?.password?.[0]
                    ?? payload?.errors?.pin?.[0]
                    ?? payload?.errors?.pin_confirmation?.[0]
                    ?? 'Could not create your workstation PIN.';

                if (errorEl) {
                    errorEl.textContent = message;
                    errorEl.hidden = false;
                }

                return;
            }

            window.location.reload();
        } catch {
            if (errorEl) {
                errorEl.textContent = 'Could not create your workstation PIN.';
                errorEl.hidden = false;
            }
        }
    });

    root.querySelector('[data-ws-change-pin-form]')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        const errorEl = root.querySelector('[data-ws-change-pin-error]');

        if (pinUpdateUrl === '') {
            return;
        }

        const password = form.querySelector('[name="password"]')?.value ?? '';
        const pin = form.querySelector('[name="pin"]')?.value ?? '';
        const pinConfirmation = form.querySelector('[name="pin_confirmation"]')?.value ?? '';

        if (errorEl) {
            errorEl.hidden = true;
            errorEl.textContent = '';
        }

        try {
            const response = await fetch(pinUpdateUrl, {
                method: 'PATCH',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    password,
                    pin,
                    pin_confirmation: pinConfirmation,
                }),
            });

            if (!response.ok) {
                const payload = await response.json().catch(() => ({}));
                const message = payload?.message
                    ?? payload?.errors?.password?.[0]
                    ?? payload?.errors?.pin?.[0]
                    ?? payload?.errors?.pin_confirmation?.[0]
                    ?? 'Could not update your workstation PIN.';

                if (errorEl) {
                    errorEl.textContent = message;
                    errorEl.hidden = false;
                }

                return;
            }

            closeOverlay();
            window.location.reload();
        } catch {
            if (errorEl) {
                errorEl.textContent = 'Could not update your workstation PIN.';
                errorEl.hidden = false;
            }
        }
    });

    root.addEventListener('ark-workstation:change-pin', () => showOverlay('change-pin'));

    if (root.dataset.needsBinding === '1') {
        root.classList.add('ws-presence--bind');
    }
}
