/**
 * Public Book Appointment wizard — presentation only.
 * Posts to existing public.leads.store; drafts live in localStorage.
 */

const DRAFT_KEY = 'ark.public.book.draft.v1';
/** Shared-computer privacy: drafts expire; submit / Start over also clear. */
const DRAFT_TTL_MS = 4 * 60 * 60 * 1000;
const GUEST_STEPS = ['concern', 'vehicle', 'when', 'details', 'contact'];
/** Recognized: intent already chosen on Vehicle Home — only when + confirm. */
const RECOGNIZED_STEPS = ['when', 'contact'];

function usPhoneDigits(value) {
    let digits = String(value ?? '').replace(/\D/g, '');

    if (digits.length === 11 && digits.startsWith('1')) {
        digits = digits.slice(1);
    }

    return digits.slice(0, 10);
}

function formatUsPhoneDisplay(value) {
    const digits = usPhoneDigits(value);

    if (digits.length === 0) {
        return '';
    }

    if (digits.length <= 3) {
        return `(${digits}`;
    }

    if (digits.length <= 6) {
        return `(${digits.slice(0, 3)}) ${digits.slice(3)}`;
    }

    return `(${digits.slice(0, 3)}) ${digits.slice(3, 6)}-${digits.slice(6)}`;
}

function splitName(fullName) {
    const trimmed = String(fullName ?? '').trim();

    if (trimmed === '') {
        return { first_name: '', last_name: '' };
    }

    const parts = trimmed.split(/\s+/);
    const first = parts.shift() ?? '';
    const last = parts.join(' ').trim();

    return {
        first_name: first,
        // Empty surname — never send a display placeholder; server omits it from contact_name.
        last_name: last,
    };
}

function readDraft() {
    try {
        const raw = window.localStorage.getItem(DRAFT_KEY);

        if (!raw) {
            return null;
        }

        const parsed = JSON.parse(raw);

        if (!parsed || typeof parsed !== 'object') {
            return null;
        }

        const savedAt = Number(parsed.savedAt ?? 0);

        if (!Number.isFinite(savedAt) || savedAt <= 0 || Date.now() - savedAt > DRAFT_TTL_MS) {
            clearBookWizardDraft();

            return null;
        }

        return parsed;
    } catch {
        return null;
    }
}

export function clearBookWizardDraft() {
    try {
        window.localStorage.removeItem(DRAFT_KEY);
    } catch {
        // ignore
    }
}

/**
 * Close Book modal: prefer history.back() so the customer returns to the page
 * they were on. Fall back to the homepage when /book was opened directly.
 */
export function closePublicBookOverlay(fallbackUrl = '/') {
    const overlay = document.querySelector('[data-public-book-overlay]');
    const closeUrl = overlay?.getAttribute('data-close-url') || fallbackUrl;

    if (window.history.length > 1) {
        window.history.back();

        return;
    }

    window.location.assign(closeUrl);
}

/**
 * Re-parent the book dialog onto <body> so position:fixed is always viewport-relative,
 * then wire dismiss (X / backdrop / Escape). Runs for every book mode, including
 * “temporarily unavailable.”
 */
function bindPublicBookOverlay() {
    const overlay = document.querySelector('[data-public-book-overlay]');

    if (!overlay) {
        return;
    }

    if (overlay.parentElement !== document.body) {
        document.body.appendChild(overlay);
    }

    document.body.classList.add('public-book-overlay-open');

    const underlay = document.querySelector('[data-public-book-underlay]');
    underlay?.setAttribute('aria-hidden', 'true');

    if (overlay.dataset.bound === '1') {
        return;
    }

    overlay.dataset.bound = '1';

    const onCloseClick = (event) => {
        const target = event.target instanceof Element
            ? event.target.closest('[data-public-book-close]')
            : null;

        if (!target) {
            return;
        }

        event.preventDefault();
        closePublicBookOverlay(target.getAttribute('href') || undefined);
    };

    overlay.addEventListener('click', onCloseClick);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            closePublicBookOverlay();
        }
    });
}

// Module scripts are deferred — DOM is ready. Mount before Alpine boots from app.js.
bindPublicBookOverlay();

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bindPublicBookOverlay);
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('publicBookIdentity', (config = {}) => ({
        channel: 'sms',
        phone: '',
        email: '',
        code: '',
        codeSent: false,
        verified: false,
        sending: false,
        checking: false,
        completing: false,
        error: '',
        notice: '',
        concern: String(config.concern ?? ''),

        formatPhone() {
            this.phone = formatUsPhoneDisplay(this.phone);
        },

        phoneDigitCount() {
            return usPhoneDigits(this.phone).length;
        },

        canSendCode() {
            return this.phoneDigitCount() >= 10;
        },

        canCheckCode() {
            return this.canSendCode() && String(this.code ?? '').trim().length >= 4;
        },

        canSendEmailCode() {
            const value = String(this.email ?? '').trim();

            return value.includes('@') && value.includes('.');
        },

        canCheckEmailCode() {
            return this.canSendEmailCode() && String(this.code ?? '').trim().length >= 4;
        },

        useEmailInstead() {
            this.channel = 'email';
            this.error = '';
            this.notice = '';
            this.code = '';
            this.codeSent = false;
            this.verified = false;
        },

        useSmsInstead() {
            this.channel = 'sms';
            this.error = '';
            this.notice = '';
            this.code = '';
            this.codeSent = false;
            this.verified = false;
        },

        async sendCode() {
            this.error = '';
            this.sending = true;

            try {
                const response = await fetch(config.sendUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': config.csrf,
                    },
                    body: JSON.stringify({ phone: this.phone, book_identity: true }),
                    credentials: 'same-origin',
                });
                const data = await response.json().catch(() => ({}));

                if (! response.ok) {
                    throw new Error(data.message || 'Could not send code.');
                }

                this.codeSent = true;
            } catch (exception) {
                this.error = exception.message || 'Could not send code.';
            } finally {
                this.sending = false;
            }
        },

        async sendEmailCode() {
            this.error = '';
            this.notice = '';
            this.sending = true;

            try {
                const response = await fetch(config.emailSendUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': config.csrf,
                    },
                    body: JSON.stringify({
                        email: String(this.email ?? '').trim(),
                    }),
                    credentials: 'same-origin',
                });
                const data = await response.json().catch(() => ({}));

                if (! response.ok) {
                    throw new Error(data.message || 'Could not send code.');
                }

                this.codeSent = true;
                this.notice = data.notice || '';
            } catch (exception) {
                this.error = exception.message || 'Could not send code.';
            } finally {
                this.sending = false;
            }
        },

        async checkEmailCode() {
            this.error = '';
            this.checking = true;

            try {
                const response = await fetch(config.emailCheckUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': config.csrf,
                    },
                    body: JSON.stringify({
                        email: String(this.email ?? '').trim(),
                        code: this.code,
                        concern: this.concern || undefined,
                    }),
                    credentials: 'same-origin',
                });
                const data = await response.json().catch(() => ({}));

                if (! response.ok || ! data.ok) {
                    throw new Error(data.message || 'Invalid code.');
                }

                this.verified = true;
                window.location.assign(data.redirect || '/book');
            } catch (exception) {
                this.verified = false;
                this.error = exception.message || 'Invalid code.';
            } finally {
                this.checking = false;
            }
        },

        async checkCode() {
            this.error = '';
            this.checking = true;

            try {
                const response = await fetch(config.checkUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': config.csrf,
                    },
                    body: JSON.stringify({
                        phone: this.phone,
                        code: this.code,
                        book_identity: true,
                    }),
                    credentials: 'same-origin',
                });
                const data = await response.json().catch(() => ({}));

                if (! response.ok || ! data.verified) {
                    throw new Error(data.message || 'Invalid code.');
                }

                this.verified = true;
                this.error = '';
                this.completing = true;
                this.$nextTick(() => {
                    this.$refs.completeForm?.submit();
                });
            } catch (exception) {
                this.verified = false;
                this.error = exception.message || 'Invalid code.';
            } finally {
                this.checking = false;
            }
        },
    }));

    window.Alpine.data('publicBookConcierge', (config = {}) => ({
        somethingElse: config.somethingElse || 'Something Else',
        intent: '',
        intentDetails: '',
        errorMessage: '',

        selectIntent(value) {
            this.intent = String(value ?? '');
            this.errorMessage = '';

            if (this.intent === this.somethingElse) {
                return;
            }

            this.intentDetails = '';
            // Fast path: known intent continues in one tap (radar checkboxes already above).
            this.$nextTick(() => {
                this.$refs.form?.requestSubmit();
            });
        },

        prepareContinue(event) {
            if (this.intent === '') {
                event.preventDefault();
                this.errorMessage = 'Pick what you’d like us to do today.';

                return;
            }

            if (this.intent === this.somethingElse && String(this.intentDetails ?? '').trim() === '') {
                event.preventDefault();
                this.errorMessage = 'Tell us what’s going on.';
            }
        },
    }));

    window.Alpine.data('publicBookWizard', (config = {}) => ({
        recognizedSchedule: Boolean(config.recognizedSchedule),
        steps: Boolean(config.recognizedSchedule) ? RECOGNIZED_STEPS : GUEST_STEPS,
        step: config.startStep && (Boolean(config.recognizedSchedule) ? RECOGNIZED_STEPS : GUEST_STEPS).includes(config.startStep)
            ? config.startStep
            : (Boolean(config.recognizedSchedule) ? 'when' : 'concern'),
        whenPhase: 'period',
        concernCategory: '',
        concernDetails: '',
        vehicleSelection: '',
        vehicleYear: '',
        vehicleMake: '',
        vehicleModel: '',
        preferredDate: '',
        preferredPeriod: '',
        timeChoice: '',
        fullName: '',
        phone: '',
        email: '',
        contactPreference: 'text',
        submitting: false,
        draftNotice: false,
        errorMessage: '',
        phoneVerificationRequired: Boolean(config.phoneVerificationRequired),
        phoneVerified: !config.phoneVerificationRequired,
        dates: Array.isArray(config.dates) ? config.dates : [],
        vehicles: Array.isArray(config.vehicles) ? config.vehicles : [],
        somethingElse: config.somethingElse || 'Something Else',
        closeUrl: config.closeUrl || '/',
        hasServerErrors: Boolean(config.hasServerErrors),
        radarItems: Array.isArray(config.radarItems) ? config.radarItems : [],

        init() {
            this.hydrateFromConfig(config);
            if (! this.recognizedSchedule) {
                this.restoreDraft(config);
            }
            this.syncNameParts();
            this.formatPhone();

            if (this.hasServerErrors) {
                this.step = 'contact';
            }

            this.$watch(
                () => this.draftPayload(),
                () => this.persistDraft(),
                { deep: true },
            );
        },

        hydrateFromConfig(config) {
            if (config.concernCategory) {
                this.concernCategory = String(config.concernCategory);
            }

            if (config.concernDetails) {
                this.concernDetails = String(config.concernDetails);
            }

            if (config.vehicleSelection !== undefined && config.vehicleSelection !== null) {
                this.vehicleSelection = String(config.vehicleSelection);
            } else if (this.vehicles.length > 0) {
                this.vehicleSelection = String(this.vehicles[0].id);
            } else {
                this.vehicleSelection = 'other';
            }

            this.vehicleYear = String(config.vehicleYear ?? '');
            this.vehicleMake = String(config.vehicleMake ?? '');
            this.vehicleModel = String(config.vehicleModel ?? '');
            this.preferredDate = String(config.preferredDate ?? '');
            this.preferredPeriod = String(config.preferredPeriod ?? '');
            this.fullName = String(config.fullName ?? '');
            this.phone = formatUsPhoneDisplay(config.phone ?? '');
            this.email = String(config.email ?? '');
            this.contactPreference = String(config.contactPreference ?? 'text');

            if (this.preferredPeriod === 'morning') {
                this.timeChoice = 'morning';
            } else if (this.preferredPeriod === 'afternoon') {
                this.timeChoice = 'afternoon';
            } else if (this.preferredDate && this.preferredPeriod === 'any') {
                this.timeChoice = 'any';
            }
        },

        restoreDraft(config) {
            if (this.hasServerErrors) {
                return;
            }

            const draft = readDraft();

            if (!draft) {
                return;
            }

            // Query-string concern wins over a stale draft category.
            if (config.concernFromQuery) {
                return;
            }

            this.step = this.steps.includes(draft.step) ? draft.step : 'concern';
            this.whenPhase = draft.whenPhase === 'day' ? 'day' : 'period';
            this.concernCategory = String(draft.concernCategory ?? this.concernCategory);
            this.concernDetails = String(draft.concernDetails ?? this.concernDetails);
            this.vehicleSelection = String(draft.vehicleSelection ?? this.vehicleSelection);
            this.vehicleYear = String(draft.vehicleYear ?? this.vehicleYear);
            this.vehicleMake = String(draft.vehicleMake ?? this.vehicleMake);
            this.vehicleModel = String(draft.vehicleModel ?? this.vehicleModel);
            this.preferredDate = String(draft.preferredDate ?? this.preferredDate);
            this.preferredPeriod = String(draft.preferredPeriod ?? this.preferredPeriod);
            this.timeChoice = String(draft.timeChoice ?? this.timeChoice);
            this.fullName = String(draft.fullName ?? this.fullName);
            this.phone = formatUsPhoneDisplay(draft.phone ?? this.phone);
            this.email = String(draft.email ?? this.email);
            this.contactPreference = String(draft.contactPreference ?? this.contactPreference);
            this.draftNotice = true;
        },

        draftPayload() {
            return {
                step: this.step,
                whenPhase: this.whenPhase,
                concernCategory: this.concernCategory,
                concernDetails: this.concernDetails,
                vehicleSelection: this.vehicleSelection,
                vehicleYear: this.vehicleYear,
                vehicleMake: this.vehicleMake,
                vehicleModel: this.vehicleModel,
                preferredDate: this.preferredDate,
                preferredPeriod: this.preferredPeriod,
                timeChoice: this.timeChoice,
                fullName: this.fullName,
                phone: this.phone,
                email: this.email,
                contactPreference: this.contactPreference,
            };
        },

        persistDraft() {
            if (this.recognizedSchedule) {
                return;
            }

            try {
                window.localStorage.setItem(
                    DRAFT_KEY,
                    JSON.stringify({
                        ...this.draftPayload(),
                        savedAt: Date.now(),
                    }),
                );
            } catch {
                // ignore quota / private mode
            }
        },

        discardDraft() {
            clearBookWizardDraft();
            this.draftNotice = false;
            this.step = 'concern';
            this.whenPhase = 'period';
            this.concernCategory = '';
            this.concernDetails = '';
            this.vehicleSelection = this.vehicles.length > 0 ? String(this.vehicles[0].id) : 'other';
            this.vehicleYear = '';
            this.vehicleMake = '';
            this.vehicleModel = '';
            this.preferredDate = '';
            this.preferredPeriod = '';
            this.timeChoice = '';
            this.errorMessage = '';
        },

        stepIndex() {
            return Math.max(0, this.steps.indexOf(this.step));
        },

        progressFraction() {
            return (this.stepIndex() + 1) / this.steps.length;
        },

        composedConcern() {
            const category = String(this.concernCategory ?? '').trim();
            const details = String(this.concernDetails ?? '').trim();

            if (category === '' || category === this.somethingElse) {
                return details;
            }

            if (details === '') {
                return category;
            }

            return `${category}\n\n${details}`;
        },

        needsVehicleEntry() {
            return this.vehicleSelection === 'other' || this.vehicles.length === 0;
        },

        selectConcern(category) {
            this.concernCategory = category;
            this.errorMessage = '';
            this.goTo(this.recognizedSchedule ? 'when' : 'vehicle');
            if (this.recognizedSchedule) {
                this.whenPhase = 'period';
            }
        },

        continueVehicle() {
            this.errorMessage = '';

            if (this.needsVehicleEntry()) {
                const year = String(this.vehicleYear ?? '').trim();
                const make = String(this.vehicleMake ?? '').trim();
                const model = String(this.vehicleModel ?? '').trim();
                const any = year !== '' || make !== '' || model !== '';

                if (any && (year === '' || make === '' || model === '')) {
                    this.errorMessage = 'Add the year, make, and model — or leave them blank.';

                    return;
                }
            }

            this.goTo('when');
            this.whenPhase = 'period';
        },

        skipVehicle() {
            this.vehicleSelection = '';
            this.vehicleYear = '';
            this.vehicleMake = '';
            this.vehicleModel = '';
            this.goTo('when');
            this.whenPhase = 'period';
        },

        selectTimeChoice(choice) {
            this.timeChoice = choice;
            this.errorMessage = '';

            if (choice === 'first_available') {
                const first = this.dates[0];

                if (!first) {
                    this.errorMessage = 'No days are available right now. Call or text us instead.';

                    return;
                }

                this.preferredDate = first.date;
                this.preferredPeriod = 'any';
                this.goTo(this.recognizedSchedule ? 'contact' : 'details');

                return;
            }

            if (choice === 'morning') {
                this.preferredPeriod = 'morning';
            } else if (choice === 'afternoon') {
                this.preferredPeriod = 'afternoon';
            } else {
                this.preferredPeriod = 'any';
            }

            this.whenPhase = 'day';
        },

        selectDay(date) {
            this.preferredDate = date;
            this.errorMessage = '';
            this.goTo(this.recognizedSchedule ? 'contact' : 'details');
        },

        continueDetails() {
            this.errorMessage = '';

            if (this.concernCategory === this.somethingElse && this.composedConcern() === '') {
                this.errorMessage = 'Tell us a little about what’s going on.';

                return;
            }

            if (this.composedConcern() === '') {
                this.errorMessage = 'Pick what we can help with, or tell us a little more.';

                return;
            }

            this.goTo('contact');
        },

        goTo(step) {
            if (! this.steps.includes(step)) {
                return;
            }

            this.step = step;
            this.errorMessage = '';
            this.draftNotice = false;
        },

        back() {
            this.errorMessage = '';

            if (this.step === 'when' && this.whenPhase === 'day') {
                this.whenPhase = 'period';

                return;
            }

            const index = this.stepIndex();

            if (index <= 0) {
                return;
            }

            this.step = this.steps[index - 1];
        },

        formatPhone() {
            this.phone = formatUsPhoneDisplay(this.phone);
        },

        syncNameParts() {
            const parts = splitName(this.fullName);
            const first = this.$refs.firstName;
            const last = this.$refs.lastName;

            if (first) {
                first.value = parts.first_name;
            }

            if (last) {
                last.value = parts.last_name;
            }
        },

        prepareSubmit(event) {
            this.syncNameParts();
            this.formatPhone();

            const concern = this.composedConcern();
            const concernField = this.$refs.concern;

            if (concernField) {
                concernField.value = concern;
            }

            if (concern === '') {
                event.preventDefault();
                this.errorMessage = 'Pick what we can help with first.';
                this.goTo('concern');

                return;
            }

            if (!this.preferredDate || !this.preferredPeriod) {
                event.preventDefault();
                this.errorMessage = 'Choose a time that works for you.';
                this.goTo('when');
                this.whenPhase = 'period';

                return;
            }

            if (usPhoneDigits(this.phone).length < 10) {
                event.preventDefault();
                this.errorMessage = 'Enter a phone number so we can reach you.';
                this.goTo('contact');

                return;
            }

            if (String(this.fullName ?? '').trim() === '') {
                event.preventDefault();
                this.errorMessage = 'Enter your name.';
                this.goTo('contact');

                return;
            }

            if (this.phoneVerificationRequired && !this.phoneVerified) {
                event.preventDefault();
                this.errorMessage = 'Verify your phone number before sending.';
                this.goTo('contact');

                return;
            }

            this.submitting = true;
            clearBookWizardDraft();
        },
    }));
});

window.clearBookWizardDraft = clearBookWizardDraft;
