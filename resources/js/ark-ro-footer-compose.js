function worksheetData() {
    const root = document.querySelector('[data-worksheet-root]');

    if (! root || ! window.Alpine) {
        return null;
    }

    return window.Alpine.$data(root);
}

function visibleConcernId() {
    const data = worksheetData();

    if (! data) {
        return null;
    }

    data.focusLaborGuideConcern?.();

    const id = Number(data.rteLabor?.concernId);

    return Number.isFinite(id) && id > 0 ? id : null;
}

function openCompose(button) {
    const concernId = visibleConcernId();
    const lineType = button.dataset.lineType || null;
    const context = {};

    if (lineType) {
        context.lineType = lineType;
    }

    if (concernId) {
        context.concernId = concernId;
    }

    window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', {
        detail: {
            task: button.dataset.composeTask,
            context,
            invokeEl: button,
        },
    }));
}

function openLaborGuide(button) {
    const data = worksheetData();

    if (! data) {
        return;
    }

    data.focusLaborGuideConcern?.();

    const item = (data.laborGuideItems || []).find((row) => row.key === button.dataset.guideKey);

    if (! item) {
        return;
    }

    data.runLaborGuideItem(item);
}

function openPartsCatalog(button) {
    const data = worksheetData();

    if (! data?.openPartsCatalog) {
        return;
    }

    data.focusLaborGuideConcern?.();
    data.openPartsCatalog(button.dataset.catalogKey, false, visibleConcernId());
}

function runComposeAction(button) {
    if (button.disabled) {
        return;
    }

    if (button.dataset.composeAction === 'labor-guide') {
        openLaborGuide(button);

        return;
    }

    if (button.dataset.composeAction === 'parts-catalog') {
        openPartsCatalog(button);

        return;
    }

    openCompose(button);
}

function closeMenus(except = null) {
    document.querySelectorAll('[data-ro-footer-compose-menu]').forEach((menu) => {
        if (menu === except) {
            return;
        }

        menu.hidden = true;
        menu.closest('[data-ro-footer-compose-more]')
            ?.querySelector('[data-ro-footer-compose-more-toggle]')
            ?.setAttribute('aria-expanded', 'false');
    });
}

function rowWidth(track, items, more) {
    const styles = getComputedStyle(track);
    const gap = Number.parseFloat(styles.columnGap || styles.gap || '0') || 0;
    let width = 0;
    let visible = 0;

    items.forEach((item) => {
        if (item.hidden) {
            return;
        }

        width += item.offsetWidth;
        visible += 1;
    });

    if (! more.hidden) {
        width += more.offsetWidth;
        visible += 1;
    }

    if (visible > 1) {
        width += gap * (visible - 1);
    }

    return width;
}

function layoutCompose(root) {
    const track = root.querySelector('[data-ro-footer-compose-track]');
    const more = root.querySelector('[data-ro-footer-compose-more]');
    const menu = root.querySelector('[data-ro-footer-compose-menu]');
    const items = [...root.querySelectorAll('[data-ro-footer-compose-item]')];

    if (! track || ! more || ! menu) {
        return;
    }

    items.forEach((item) => {
        item.hidden = false;
    });
    more.hidden = true;
    menu.hidden = true;
    menu.replaceChildren();

    if (rowWidth(track, items, more) <= track.clientWidth + 1) {
        return;
    }

    more.hidden = false;

    const collapsed = [];

    for (let index = items.length - 1; index >= 0 && rowWidth(track, items, more) > track.clientWidth + 1; index -= 1) {
        items[index].hidden = true;
        collapsed.unshift(items[index]);
    }

    if (items.length > 0 && items.every((item) => item.hidden)) {
        items[0].hidden = false;
        collapsed.shift();
    }

    collapsed.forEach((source) => {
        const item = document.createElement('button');
        item.type = 'button';
        item.className = 'ops-ro-footer__menu-item';
        item.setAttribute('role', 'menuitem');
        item.textContent = source.dataset.label || source.textContent.trim();
        item.disabled = source.disabled;
        item.title = source.title || '';
        item.addEventListener('click', (event) => {
            event.stopPropagation();
            source.click();
            closeMenus();
        });
        menu.append(item);
    });
}

function syncDockReserve() {
    const dock = document.querySelector('[data-ro-orientation-header]');

    if (! dock) {
        return;
    }

    const height = Math.ceil(dock.getBoundingClientRect().height);
    document.documentElement.style.setProperty('--ops-ro-dock-reserve', `${height}px`);
}

const boundComposeRows = new WeakSet();

function bindCompose(root) {
    if (boundComposeRows.has(root)) {
        layoutCompose(root);
        syncDockReserve();

        return;
    }

    boundComposeRows.add(root);

    const toggle = root.querySelector('[data-ro-footer-compose-more-toggle]');
    const menu = root.querySelector('[data-ro-footer-compose-menu]');

    toggle?.addEventListener('click', (event) => {
        event.stopPropagation();
        const open = menu?.hidden === true;
        closeMenus();

        if (menu && open) {
            menu.hidden = false;
            toggle.setAttribute('aria-expanded', 'true');
        }
    });

    const observer = new ResizeObserver(() => {
        layoutCompose(root);
        syncDockReserve();
    });
    observer.observe(root);
    layoutCompose(root);
    syncDockReserve();
}

function scanCompose(nodes) {
    nodes.forEach((node) => {
        if (! (node instanceof Element)) {
            return;
        }

        if (node.matches('[data-ro-footer-compose]')) {
            bindCompose(node);
        }

        node.querySelectorAll('[data-ro-footer-compose]').forEach(bindCompose);
    });
}

export function initRoFooterCompose() {
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-compose-action]');

        if (button && button.closest('[data-ro-footer-compose]')) {
            runComposeAction(button);
            closeMenus();

            return;
        }

        if (! event.target.closest('[data-ro-footer-compose-more]')) {
            closeMenus();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeMenus();
        }
    });

    document.querySelectorAll('[data-ro-footer-compose]').forEach(bindCompose);
    syncDockReserve();

    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            scanCompose(mutation.addedNodes);
        });
    });
    observer.observe(document.body, { childList: true, subtree: true });
    window.addEventListener('resize', syncDockReserve);
}
