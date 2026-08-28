function formatClock(date, timeZone) {
    return new Intl.DateTimeFormat('en-US', {
        hour: 'numeric',
        minute: '2-digit',
        second: '2-digit',
        hour12: true,
        timeZone,
    }).format(date);
}

function formatAbbreviation(date, timeZone) {
    const part = new Intl.DateTimeFormat('en-US', {
        timeZone,
        timeZoneName: 'short',
    }).formatToParts(date).find((entry) => entry.type === 'timeZoneName');

    return part?.value ?? '';
}

export function arkOperationalClock(config = {}) {
    const serverAnchorMs = config.server_utc_iso ? Date.parse(config.server_utc_iso) : Date.now();
    const dbAnchorMs = config.db_utc_iso ? Date.parse(config.db_utc_iso) : null;
    const dbSessionAnchorMs = config.db_session_now_iso ? Date.parse(config.db_session_now_iso) : null;

    return {
        serverUtc: '',
        dbUtc: '',
        shopLocal: '',
        shopAbbr: config.shop_abbreviation ?? '',
        phpIsUtc: config.php_is_utc ?? true,
        dbMatchesUtc: config.db_matches_utc ?? true,
        dbAvailable: config.db_available ?? false,
        pageLoadMs: 0,
        init() {
            this.pageLoadMs = Date.now();
            this.tick();
            window.setInterval(() => this.tick(), 1000);
        },
        tick() {
            const elapsed = Date.now() - this.pageLoadMs;
            const serverDate = new Date(serverAnchorMs + elapsed);

            this.serverUtc = formatClock(serverDate, 'UTC');

            if (this.dbAvailable) {
                if (this.dbMatchesUtc) {
                    this.dbUtc = this.serverUtc;
                } else if (dbSessionAnchorMs !== null) {
                    this.dbUtc = formatClock(new Date(dbSessionAnchorMs + elapsed), 'UTC');
                } else if (dbAnchorMs !== null) {
                    this.dbUtc = formatClock(new Date(dbAnchorMs + elapsed), 'UTC');
                } else {
                    this.dbUtc = '—';
                }
            } else {
                this.dbUtc = 'n/a';
            }

            const shopTimezone = config.shop_timezone ?? 'UTC';

            this.shopLocal = formatClock(serverDate, shopTimezone);
            this.shopAbbr = formatAbbreviation(serverDate, shopTimezone) || config.shop_abbreviation || '';
        },
    };
}
