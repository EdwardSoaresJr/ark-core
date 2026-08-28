/**
 * ⌘K / Ctrl+K — command palette + entity search.
 * Commands filter locally; entity search runs at ≥2 characters.
 */
export function initOpsGlobalSearch() {
    const root = document.querySelector('[data-ops-global-search]');

    if (!root || root.dataset.bound === '1') {
        return;
    }

    root.dataset.bound = '1';

    const input = root.querySelector('[data-ops-global-search-input]');
    const results = root.querySelector('[data-ops-global-search-results]');
    const searchUrl = root.dataset.searchUrl;
    const composeUrl = root.dataset.composeUrl;
    const csrf = root.dataset.csrf;
    const commands = parseCommands(
        root.querySelector('[data-ops-global-search-commands]')?.textContent ?? '[]',
    );
    const groupOrder = ['Navigate', 'Create', 'Search', 'Operations', 'Results'];
    let timer = null;
    let activeIndex = -1;
    let preferCompose = false;
    let searchGeneration = 0;

    const open = () => {
        root.hidden = false;
        root.setAttribute('aria-hidden', 'false');
        input.value = '';
        activeIndex = -1;
        renderPalette(filterCommands(commands, ''), []);
        input.focus();
    };

    const close = () => {
        root.hidden = true;
        root.setAttribute('aria-hidden', 'true');
        if (timer) {
            clearTimeout(timer);
        }
    };

    const filterCommands = (list, query) => {
        const needle = query.trim().toLowerCase();

        if (needle === '') {
            return list;
        }

        return list.filter((command) => {
            const haystack = [
                command.title,
                command.group,
                ...(Array.isArray(command.keywords) ? command.keywords : []),
            ]
                .join(' ')
                .toLowerCase();

            return haystack.includes(needle);
        });
    };

    const renderPalette = (matchedCommands, entityRows) => {
        const chunks = [];
        let index = 0;

        for (const group of groupOrder) {
            if (group === 'Results') {
                if (!Array.isArray(entityRows) || entityRows.length === 0) {
                    continue;
                }

                chunks.push(`<div class="ops-global-search__group">${escapeHtml(group)}</div>`);

                for (const row of entityRows) {
                    const composeAttr = row.compose_customer_id
                        ? ` data-compose-customer-id="${row.compose_customer_id}"`
                        : '';
                    chunks.push(`<button type="button" class="ops-global-search__row" data-index="${index}" data-url="${escapeAttr(row.url)}"${composeAttr}>
                <span class="ops-global-search__row-type">${escapeHtml(row.type)}</span>
                <span class="ops-global-search__row-label">${escapeHtml(row.label)}</span>
                <span class="ops-global-search__row-detail">${escapeHtml(row.detail || '')}</span>
                ${row.compose_customer_id ? '<span class="ops-global-search__row-compose">Compose</span>' : ''}
            </button>`);
                    index += 1;
                }

                continue;
            }

            const inGroup = matchedCommands.filter((command) => command.group === group);

            if (inGroup.length === 0) {
                continue;
            }

            chunks.push(`<div class="ops-global-search__group">${escapeHtml(group)}</div>`);

            for (const command of inGroup) {
                const disabled = !command.url;
                const reason = command.disabled_reason || '';

                if (disabled) {
                    chunks.push(`<div class="ops-global-search__row ops-global-search__row--command ops-global-search__row--disabled" aria-disabled="true">
                <span class="ops-global-search__row-label">${escapeHtml(command.title)}</span>
                <span class="ops-global-search__row-detail">${escapeHtml(reason)}</span>
            </div>`);
                    continue;
                }

                chunks.push(`<button type="button" class="ops-global-search__row ops-global-search__row--command" data-index="${index}" data-url="${escapeAttr(command.url)}">
                <span class="ops-global-search__row-label">${escapeHtml(command.title)}</span>
            </button>`);
                index += 1;
            }
        }

        if (chunks.length === 0) {
            results.innerHTML = '<p class="ops-global-search__hint">No matches.</p>';
            activeIndex = -1;
            return;
        }

        results.innerHTML = chunks.join('');
        activeIndex = 0;
        highlight();
    };

    const highlight = () => {
        results.querySelectorAll('.ops-global-search__row[data-index]').forEach((row) => {
            row.classList.toggle('ops-global-search__row--active', Number(row.dataset.index) === activeIndex);
        });
    };

    const activate = (row) => {
        if (!row || !row.dataset.url) {
            return;
        }

        const composeId = row.dataset.composeCustomerId;

        if (composeId && (row.querySelector('.ops-global-search__row-compose')?.matches(':hover') || preferCompose)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = composeUrl;
            form.innerHTML = `<input type="hidden" name="_token" value="${csrf}"><input type="hidden" name="customer_id" value="${composeId}">`;
            document.body.appendChild(form);
            form.submit();
            return;
        }

        window.location.href = row.dataset.url;
    };

    const search = (q) => {
        const matched = filterCommands(commands, q);
        const trimmed = q.trim();

        if (trimmed.length < 2) {
            renderPalette(matched, []);
            return;
        }

        const generation = ++searchGeneration;

        renderPalette(matched, []);

        fetch(`${searchUrl}?q=${encodeURIComponent(q)}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then((response) => response.json())
            .then((payload) => {
                if (generation !== searchGeneration) {
                    return;
                }

                const rows = Array.isArray(payload.results) ? payload.results : [];
                renderPalette(filterCommands(commands, input.value), rows);
            })
            .catch(() => {
                if (generation !== searchGeneration) {
                    return;
                }

                renderPalette(filterCommands(commands, input.value), []);
            });
    };

    document.addEventListener('keydown', (event) => {
        const isPalette = (event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k';

        if (isPalette) {
            event.preventDefault();
            if (root.hidden) {
                open();
            } else {
                close();
            }
            return;
        }

        if (root.hidden) {
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            close();
            return;
        }

        const rows = [...results.querySelectorAll('.ops-global-search__row[data-index]')];

        if (event.key === 'ArrowDown' && rows.length > 0) {
            event.preventDefault();
            activeIndex = Math.min(rows.length - 1, activeIndex + 1);
            highlight();
            return;
        }

        if (event.key === 'ArrowUp' && rows.length > 0) {
            event.preventDefault();
            activeIndex = Math.max(0, activeIndex - 1);
            highlight();
            return;
        }

        if (event.key === 'Enter' && rows.length > 0 && activeIndex >= 0) {
            event.preventDefault();
            preferCompose = event.shiftKey;
            activate(rows[activeIndex]);
            preferCompose = false;
        }
    });

    document.querySelectorAll('[data-ops-global-search-open]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            open();
        });
    });

    root.querySelector('[data-ops-global-search-backdrop]')?.addEventListener('click', close);

    input.addEventListener('input', () => {
        if (timer) {
            clearTimeout(timer);
        }

        const q = input.value;
        const matched = filterCommands(commands, q);

        if (q.trim().length < 2) {
            renderPalette(matched, []);
            return;
        }

        renderPalette(matched, []);
        timer = setTimeout(() => search(q), 180);
    });

    results.addEventListener('click', (event) => {
        const row = event.target.closest('.ops-global-search__row[data-index]');
        if (!row) {
            return;
        }
        preferCompose = event.target.closest('.ops-global-search__row-compose') !== null || event.shiftKey;
        activate(row);
        preferCompose = false;
    });
}

function parseCommands(raw) {
    if (!raw) {
        return [];
    }

    try {
        const parsed = JSON.parse(raw);
        return Array.isArray(parsed) ? parsed : [];
    } catch {
        return [];
    }
}

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');
}

function escapeAttr(value) {
    return escapeHtml(value).replaceAll("'", '&#39;');
}
