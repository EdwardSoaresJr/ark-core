/**
 * Insert @RO1677 into visit-reason / concern fields from this customer's prior visits.
 */

export function mentionFragmentAtCaret(el) {
    if (! el || typeof el.value !== 'string') {
        return '';
    }

    const pos = typeof el.selectionStart === 'number' ? el.selectionStart : el.value.length;
    const before = el.value.slice(0, pos);
    const match = before.match(/@RO#?\d*$/i) || before.match(/@$/);

    return match ? match[0] : '';
}

export function filterRoMentions(visits, fragment) {
    if (! fragment || ! Array.isArray(visits) || visits.length === 0) {
        return [];
    }

    const needle = fragment.replace(/^@RO#?/i, '').replace(/^@/, '').toLowerCase();

    return visits.filter((row) => {
        if (needle === '') {
            return true;
        }

        const number = String(row.number ?? '');
        const token = String(row.token ?? '').toLowerCase();
        const label = String(row.label ?? '').toLowerCase();
        const detail = String(row.detail ?? '').toLowerCase();

        return number.startsWith(needle)
            || token.includes(needle)
            || label.includes(needle)
            || detail.includes(needle);
    });
}

export function insertRoMentionToken(el, token) {
    if (! el || typeof el.value !== 'string') {
        return;
    }

    const value = el.value;
    const pos = typeof el.selectionStart === 'number' ? el.selectionStart : value.length;
    const before = value.slice(0, pos);
    const after = value.slice(pos);
    const fragment = mentionFragmentAtCaret(el);
    const start = fragment ? pos - fragment.length : pos;
    const prefix = start > 0 && ! /\s$/.test(value.slice(0, start)) ? ' ' : '';
    const next = value.slice(0, start) + prefix + token + ' ' + after;
    const caret = start + prefix.length + token.length + 1;

    el.value = next;
    el.dispatchEvent(new Event('input', { bubbles: true }));

    if (typeof el.setSelectionRange === 'function') {
        el.focus();
        el.setSelectionRange(caret, caret);
    }
}

export function arkRoMention(visits = []) {
    return {
        visits: Array.isArray(visits) ? visits : [],
        matches: [],
        open: false,
        activeIndex: -1,

        field() {
            return this.$refs.field ?? this.$el?.querySelector?.('textarea, input[type="text"]') ?? null;
        },

        refresh() {
            const fragment = mentionFragmentAtCaret(this.field());
            this.matches = filterRoMentions(this.visits, fragment);
            this.open = this.matches.length > 0 && fragment !== '';
            if (! this.open) {
                this.activeIndex = -1;
            } else if (this.activeIndex >= this.matches.length) {
                this.activeIndex = 0;
            }
        },

        onInput() {
            this.refresh();
        },

        onKeydown(event) {
            if (! this.open || this.matches.length === 0) {
                return;
            }

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                this.activeIndex = Math.min(this.activeIndex + 1, this.matches.length - 1);
                if (this.activeIndex < 0) {
                    this.activeIndex = 0;
                }

                return;
            }

            if (event.key === 'ArrowUp') {
                event.preventDefault();
                this.activeIndex = Math.max(this.activeIndex - 1, 0);

                return;
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                this.open = false;
                this.activeIndex = -1;

                return;
            }

            if (event.key === 'Enter' && this.activeIndex >= 0) {
                event.preventDefault();
                this.choose(this.matches[this.activeIndex]);
            }
        },

        choose(row) {
            const el = this.field();
            insertRoMentionToken(el, row.token);
            this.open = false;
            this.activeIndex = -1;
            this.$nextTick(() => this.refresh());
        },

        insertChip(row) {
            const el = this.field();

            if (! el) {
                return;
            }

            const needsSpace = el.value !== '' && ! /\s$/.test(el.value);
            el.value = (el.value || '') + (needsSpace ? ' ' : '') + row.token + ' ';
            el.dispatchEvent(new Event('input', { bubbles: true }));
            el.focus();
            this.open = false;
        },
    };
}
