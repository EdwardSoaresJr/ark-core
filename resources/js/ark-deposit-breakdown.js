let activeOverlay = null;

function formatDepositCents(cents) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(cents / 100);
}

function syncDepositAmountFields(repairOrderId, cents) {
    document.querySelectorAll(`[data-suggested-deposit-amount="${repairOrderId}"]`).forEach((element) => {
        element.textContent = formatDepositCents(cents);
    });
}

function initDepositBreakdownDialog(mount) {
    const dialog = mount.querySelector('.ops-deposit-breakdown-modal__dialog');

    if (! dialog) {
        return;
    }

    const repairOrderId = dialog.dataset.repairOrderId;
    const totalElement = dialog.querySelector('[data-deposit-breakdown-total]');
    const checkboxes = dialog.querySelectorAll('[data-deposit-line-checkbox]');

    const recalculate = () => {
        let totalCents = 0;

        checkboxes.forEach((checkbox) => {
            if (! checkbox.checked) {
                checkbox.closest('tr')?.classList.add('ops-deposit-breakdown-modal__line--excluded');

                return;
            }

            checkbox.closest('tr')?.classList.remove('ops-deposit-breakdown-modal__line--excluded');
            totalCents += Number.parseInt(checkbox.dataset.amountCents || '0', 10);
        });

        if (totalElement) {
            totalElement.textContent = formatDepositCents(totalCents);
        }

        if (repairOrderId) {
            syncDepositAmountFields(repairOrderId, totalCents);
        }
    };

    checkboxes.forEach((checkbox) => {
        checkbox.addEventListener('change', recalculate);
    });

    recalculate();
}

function depositOverlay() {
    let overlay = document.getElementById('ops-deposit-breakdown-overlay');

    if (! overlay) {
        overlay = document.createElement('div');
        overlay.id = 'ops-deposit-breakdown-overlay';
        overlay.className = 'ops-deposit-breakdown-modal';
        overlay.setAttribute('aria-hidden', 'true');
        overlay.innerHTML = `
            <button type="button" class="ops-deposit-breakdown-modal__backdrop" data-ops-deposit-breakdown-close aria-label="Close deposit breakdown"></button>
            <div data-deposit-breakdown-mount></div>
        `;
        document.body.appendChild(overlay);
    }

    return overlay;
}

function closeDepositBreakdown() {
    const overlay = document.getElementById('ops-deposit-breakdown-overlay');

    if (! overlay) {
        return;
    }

    overlay.classList.remove('is-open');
    overlay.setAttribute('aria-hidden', 'true');
    overlay.querySelector('[data-deposit-breakdown-mount]')?.replaceChildren();
    document.body.classList.remove('ops-deposit-modal-open');
    activeOverlay = null;
}

function openDepositBreakdown(templateId) {
    const template = document.getElementById(templateId);

    if (! template || ! (template instanceof HTMLTemplateElement)) {
        return;
    }

    const overlay = depositOverlay();
    const mount = overlay.querySelector('[data-deposit-breakdown-mount]');

    if (! mount) {
        return;
    }

    mount.replaceChildren(template.content.cloneNode(true));
    initDepositBreakdownDialog(mount);
    overlay.classList.add('is-open');
    overlay.setAttribute('aria-hidden', 'false');
    document.body.classList.add('ops-deposit-modal-open');
    activeOverlay = overlay;

    mount.querySelector('.ops-deposit-breakdown-modal__close')?.focus();
}

export function initDepositBreakdowns() {
    document.addEventListener('click', (event) => {
        const openTrigger = event.target.closest('[data-ops-deposit-breakdown-open]');

        if (openTrigger) {
            event.preventDefault();
            openDepositBreakdown(openTrigger.getAttribute('data-ops-deposit-breakdown-open'));

            return;
        }

        if (event.target.closest('[data-ops-deposit-breakdown-close]')) {
            event.preventDefault();
            closeDepositBreakdown();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && activeOverlay?.classList.contains('is-open')) {
            closeDepositBreakdown();
        }
    });
}
