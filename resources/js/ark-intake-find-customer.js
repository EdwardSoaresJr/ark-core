function formatPhoneFromUrl(search = window.location.search) {
    try {
        const raw = new URLSearchParams(search).get('phone')?.trim();

        if (!raw) {
            return '';
        }

        let digits = raw.replace(/\D/g, '');

        if (digits.length === 11 && digits.startsWith('1')) {
            digits = digits.slice(1);
        }

        if (digits.length === 10) {
            return `(${digits.slice(0, 3)}) ${digits.slice(3, 6)}-${digits.slice(6)}`;
        }

        return raw;
    } catch {
        return '';
    }
}

function hasIncomingCallPhoneParam(search = window.location.search) {
    try {
        return new URLSearchParams(search).has('phone');
    } catch {
        return false;
    }
}

function intakeDraftTabTitle(firstName, lastName, selectedCustomerName = '') {
    const chosen = String(selectedCustomerName || '').trim();

    if (chosen !== '') {
        return chosen;
    }

    const name = [firstName, lastName]
        .map((part) => String(part || '').trim())
        .filter(Boolean)
        .join(' ');

    return name.length >= 2 ? name : '';
}

function intakeRecognizeTabSubtitle({ query, selectedCustomerId, selectedCustomerName, initialPhoneFromCall }) {
    if (selectedCustomerId && selectedCustomerName) {
        return `${selectedCustomerName} · Review & continue`;
    }

    if (hasIncomingCallPhoneParam()) {
        return 'Incoming call';
    }

    const trimmedQuery = String(query || '').trim();

    if (trimmedQuery !== '') {
        return trimmedQuery.length > 28 ? `Search: ${trimmedQuery.slice(0, 25)}…` : `Search: ${trimmedQuery}`;
    }

    return 'Recognize customer';
}

export function arkIntakeFindCustomer(config = {}) {
    const incomingCallCapture = config.initialPhoneFromCall
        || hasIncomingCallPhoneParam();
    const resolvedInitialPhone = String(config.initialPhone ?? '').trim()
        || (incomingCallCapture ? formatPhoneFromUrl() : '');
    const leadPrefill = config.leadPrefill ?? {};
    const leadId = Number(config.leadId ?? 0) || null;

    const applyLeadPrefill = (state) => {
        if (leadId === null) {
            return;
        }

        if (! String(state.firstName ?? '').trim() && String(leadPrefill.firstName ?? '').trim()) {
            state.firstName = leadPrefill.firstName;
        }

        if (! String(state.lastName ?? '').trim() && String(leadPrefill.lastName ?? '').trim()) {
            state.lastName = leadPrefill.lastName;
        }

        if (! String(state.phone ?? '').trim() && String(leadPrefill.phone ?? '').trim()) {
            state.phone = leadPrefill.phone;
        }

        if (! String(state.email ?? '').trim() && String(leadPrefill.email ?? '').trim()) {
            state.email = leadPrefill.email;
        }

        if (! String(state.contactPreference ?? '').trim() && String(leadPrefill.contactPreference ?? '').trim()) {
            state.contactPreference = leadPrefill.contactPreference;
        }

        if (! String(state.referralSource ?? '').trim() && String(leadPrefill.referralSource ?? '').trim()) {
            state.referralSource = leadPrefill.referralSource;
        }
    };

    return {
        query: config.initialQuery ?? '',
        searchLoading: false,
        duplicateLoading: false,
        searchUrl: config.searchUrl,
        checkUrl: config.checkUrl,
        customerShowUrl: config.customerShowUrl,
        storeUrl: config.storeUrl,
        customerUpdateUrl: config.customerUpdateUrl,
        selectedCustomerId: config.initialSelectedCustomerId ?? null,
        selectedCustomerName: config.initialSelectedCustomerName ?? '',
        firstName: config.initialFirstName ?? '',
        lastName: config.initialLastName ?? '',
        phone: resolvedInitialPhone,
        initialPhoneFromCall: incomingCallCapture,
        email: config.initialEmail ?? '',
        contactPreference: config.initialContactPreference ?? '',
        addressLine1: config.initialAddressLine1 ?? '',
        addressLine2: config.initialAddressLine2 ?? '',
        city: config.initialCity ?? '',
        state: config.initialState ?? '',
        postalCode: config.initialPostalCode ?? '',
        referralSource: config.initialReferralSource ?? '',
        customerType: config.initialCustomerType ?? 'Retail',
        _searchDebounceTimer: null,
        _duplicateDebounceTimer: null,
        _searchAbort: null,
        _checkAbort: null,

        get isEditing() {
            return this.selectedCustomerId !== null;
        },

        get formAction() {
            return this.isEditing
                ? this.customerUpdateUrl.replace('__CUSTOMER__', String(this.selectedCustomerId))
                : this.storeUrl;
        },

        get submitLabel() {
            return this.isEditing
                ? 'Update & use this customer'
                : 'Add & continue';
        },

        get panelLabel() {
            return this.isEditing
                ? 'Update customer'
                : 'New customer';
        },

        init() {
            this.bootstrapIncomingCallCapture();

            document.addEventListener('ark:intake-memory-restore', (event) => {
                if (event.detail?.draft) {
                    this.applyIncomingCallDraft(event.detail.draft);
                }
            });

            const restoredDraft = window.ARK?.intakeWorkspaceMemory?.consumeDraftRestore?.();
            if (restoredDraft) {
                this.applyIncomingCallDraft(restoredDraft);
            }

            if (this.query.trim() !== '') {
                this.runSearch();
            }

            this.$watch('query', () => {
                clearTimeout(this._searchDebounceTimer);
                this._searchDebounceTimer = setTimeout(() => {
                    this.syncQueryToUrl();
                    this.runSearch();
                    this.notifyDraftChanged();
                }, 300);
            });

            ['firstName', 'lastName', 'phone', 'email'].forEach((field) => {
                this.$watch(field, () => {
                    this.scheduleDuplicateCheck();
                    this.notifyDraftChanged();
                    if (field === 'firstName' || field === 'lastName') {
                        this.syncIntakeWorkspaceTab();
                    }
                });
            });

            this.$watch('selectedCustomerName', () => {
                this.syncIntakeWorkspaceTab();
            });

            this.$refs.results?.addEventListener('click', (event) => this.handleResultClick(event));
            this.$refs.duplicates?.addEventListener('click', (event) => this.handleResultClick(event));
            this.scheduleDuplicateCheck();

            if (this.selectedCustomerId) {
                this.$nextTick(() => this.highlightSelectedCard(this.selectedCustomerId));
            }

            this.syncIntakeWorkspaceTab();
            applyLeadPrefill(this);
        },

        syncIntakeWorkspaceTab() {
            if (window.location.pathname !== '/app/intake/new') {
                return;
            }

            const params = new URLSearchParams(window.location.search);

            if (params.has('customer_id')) {
                return;
            }

            const workspaceId = params.get('ws');

            if (!workspaceId) {
                return;
            }

            const draftTitle = intakeDraftTabTitle(this.firstName, this.lastName, this.selectedCustomerName);
            const tabKey = `intake:${workspaceId}`;
            const tab = window.ARK?.workspace?.getTabs?.().find((entry) => entry.key === tabKey);
            const title = draftTitle || (tab?.title?.match(/^Check In \(\d+\)$/) ? tab.title : 'Check In');

            window.ARK?.workspace?.updateIntakeTab?.({
                workspaceId,
                title,
                subtitle: intakeRecognizeTabSubtitle({
                    query: this.query,
                    selectedCustomerId: this.selectedCustomerId,
                    selectedCustomerName: this.selectedCustomerName,
                    initialPhoneFromCall: this.initialPhoneFromCall,
                }),
                route: `${window.location.pathname}${window.location.search}${window.location.hash}`,
            });
        },

        bootstrapIncomingCallCapture() {
            if (!this.initialPhoneFromCall) {
                return;
            }

            window.ARK?.intakeWorkspaceMemory?.clearIncomingCallCapture?.();

            const phone = String(this.phone ?? '').trim() || formatPhoneFromUrl();

            if (phone) {
                this.phone = phone;
            }

            window.ARK?.workspace?.setDirty?.(false);
        },

        applyIncomingCallDraft(draft) {
            if (!draft || typeof draft !== 'object' || this.initialPhoneFromCall) {
                return;
            }

            this.applyDraft(draft);
        },

        applyDraft(draft) {
            if (!draft || typeof draft !== 'object') {
                return;
            }

            this.query = draft.query ?? this.query;
            this.selectedCustomerId = draft.selectedCustomerId ?? null;
            this.firstName = draft.firstName ?? '';
            this.lastName = draft.lastName ?? '';
            this.phone = draft.phone ?? '';
            this.email = draft.email ?? '';
            this.addressLine1 = draft.addressLine1 ?? '';
            this.addressLine2 = draft.addressLine2 ?? '';
            this.city = draft.city ?? '';
            this.state = draft.state ?? '';
            this.postalCode = draft.postalCode ?? '';
            this.referralSource = draft.referralSource ?? '';
            this.customerType = draft.customerType ?? 'Retail';
            applyLeadPrefill(this);

            if (this.query.trim() !== '') {
                this.$nextTick(() => this.runSearch());
            }

            if (this.selectedCustomerId) {
                this.$nextTick(() => this.highlightSelectedCard(this.selectedCustomerId));
            }

            this.syncIntakeWorkspaceTab();
        },

        syncQueryToUrl() {
            if (window.location.pathname !== '/app/intake/new') {
                return;
            }

            const params = new URLSearchParams(window.location.search);

            if (params.has('customer_id')) {
                return;
            }

            const trimmed = this.query.trim();
            const url = new URL(window.location.href);

            if (trimmed === '') {
                url.searchParams.delete('q');
            } else {
                url.searchParams.set('q', trimmed);
            }

            const next = `${url.pathname}${url.search}${url.hash}`;
            const current = `${window.location.pathname}${window.location.search}${window.location.hash}`;

            if (next !== current) {
                window.history.replaceState(window.history.state, '', next);
            }

            this.syncIntakeWorkspaceTab();
        },

        notifyDraftChanged() {
            document.dispatchEvent(new CustomEvent('ark:intake-draft-changed', { bubbles: true }));
        },

        handleResultClick(event) {
            if (event.target.closest('a')) {
                return;
            }

            const card = event.target.closest('[data-intake-customer-id]');

            if (! card) {
                return;
            }

            event.preventDefault();
            this.selectCustomer(Number(card.dataset.intakeCustomerId));
        },

        async selectCustomer(customerId) {
            const url = this.customerShowUrl.replace('__CUSTOMER__', String(customerId));

            const response = await fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
            });

            if (! response.ok) {
                return;
            }

            this.applyCustomer(await response.json());
            this.highlightSelectedCard(customerId);
            this.clearDuplicatePanel();
            this.notifyDraftChanged();
            this.syncIntakeWorkspaceTab();
        },

        applyCustomer(customer) {
            this.selectedCustomerId = customer.id;
            this.selectedCustomerName = customer.name;
            this.firstName = customer.first_name ?? '';
            this.lastName = customer.last_name ?? '';
            this.phone = customer.phone ?? '';
            this.email = customer.email ?? '';
            this.contactPreference = customer.contact_preference ?? '';
            this.addressLine1 = customer.address_line_1 ?? '';
            this.addressLine2 = customer.address_line_2 ?? '';
            this.city = customer.city ?? '';
            this.state = customer.state ?? '';
            this.postalCode = customer.postal_code ?? '';
            this.referralSource = customer.referral_source ?? '';
            this.customerType = customer.customer_type ?? 'Retail';
        },

        clearSelectedCustomer() {
            this.selectedCustomerId = null;
            this.selectedCustomerName = '';
            this.firstName = '';
            this.lastName = '';
            this.phone = '';
            this.email = '';
            this.contactPreference = '';
            this.addressLine1 = '';
            this.addressLine2 = '';
            this.city = '';
            this.state = '';
            this.postalCode = '';
            this.referralSource = '';
            this.customerType = 'Retail';
            this.highlightSelectedCard(null);
            this.scheduleDuplicateCheck();
            this.syncIntakeWorkspaceTab();
        },

        highlightSelectedCard(customerId) {
            this.$refs.results?.querySelectorAll('[data-intake-customer-id]').forEach((card) => {
                card.classList.toggle(
                    'ops-ro-card--selected',
                    customerId !== null && Number(card.dataset.intakeCustomerId) === customerId,
                );
            });
        },

        clearDuplicatePanel() {
            if (this.$refs.duplicates) {
                this.$refs.duplicates.innerHTML = '';
            }

            this.duplicateLoading = false;
        },

        scheduleDuplicateCheck() {
            if (this.isEditing) {
                this.clearDuplicatePanel();

                return;
            }

            clearTimeout(this._duplicateDebounceTimer);
            this._duplicateDebounceTimer = setTimeout(() => this.runDuplicateCheck(), 350);
        },

        hasCheckableInput() {
            return this.firstName.trim().length >= 2
                || this.lastName.trim().length >= 2
                || this.phone.replace(/\D/g, '').length >= 7
                || (this.email.includes('@') && this.email.trim().length >= 5);
        },

        async runDuplicateCheck() {
            if (this.isEditing) {
                this.clearDuplicatePanel();

                return;
            }

            if (! this.hasCheckableInput()) {
                this.clearDuplicatePanel();

                return;
            }

            this._checkAbort?.abort();
            this._checkAbort = new AbortController();
            this.duplicateLoading = true;

            try {
                const url = new URL(this.checkUrl, window.location.origin);
                url.searchParams.set('first_name', this.firstName.trim());
                url.searchParams.set('last_name', this.lastName.trim());
                url.searchParams.set('phone', this.phone.trim());
                url.searchParams.set('email', this.email.trim());

                if (this.selectedCustomerId) {
                    url.searchParams.set('exclude_customer_id', String(this.selectedCustomerId));
                }

                const response = await fetch(url, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'text/html',
                    },
                    signal: this._checkAbort.signal,
                });

                if (! response.ok) {
                    return;
                }

                this.$refs.duplicates.innerHTML = await response.text();
            } catch (error) {
                if (error?.name === 'AbortError') {
                    return;
                }

                throw error;
            } finally {
                this.duplicateLoading = false;
            }
        },

        async runSearch() {
            const trimmed = this.query.trim();

            if (trimmed === '') {
                this.$refs.results.innerHTML = '';
                this.searchLoading = false;

                return;
            }

            this._searchAbort?.abort();
            this._searchAbort = new AbortController();
            this.searchLoading = true;

            try {
                const url = new URL(this.searchUrl, window.location.origin);
                url.searchParams.set('q', trimmed);

                const response = await fetch(url, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'text/html',
                    },
                    signal: this._searchAbort.signal,
                });

                if (! response.ok) {
                    return;
                }

                this.$refs.results.innerHTML = await response.text();
                this.highlightSelectedCard(this.selectedCustomerId);
            } catch (error) {
                if (error?.name === 'AbortError') {
                    return;
                }

                throw error;
            } finally {
                this.searchLoading = false;
            }
        },
    };
}
