import { getArkEcho, arkEchoEnabled } from './ark-echo';

const LIVE = 'connected';

/**
 * Keeps one view in step with the server. Reverb events call trigger(); bursts of related events
 * become one refresh. While the socket is connected a slow poll covers anything without an event;
 * while it is down the original fast poll runs. Reconnecting, coming back online, and showing the
 * tab again resync at once. Hidden tabs do not poll.
 *
 * @param {{ refresh: () => (Promise<unknown>|unknown), fastMs: number, slowMs: number, coalesceMs?: number, pauseWhenHidden?: boolean }} options
 */
export function arkRealtimeSync({ refresh, fastMs, slowMs, coalesceMs = 300, pauseWhenHidden = true }) {
    let timer = null;
    let coalesceTimer = null;
    let running = false;
    let rerun = false;
    let socketLive = false;
    let wasLive = false;

    const run = async () => {
        if (pauseWhenHidden && document.hidden) {
            return;
        }

        if (running) {
            rerun = true;

            return;
        }

        running = true;

        try {
            await refresh();
        } catch {
            // The next event or poll recovers.
        } finally {
            running = false;

            if (rerun) {
                rerun = false;
                trigger();
            }
        }
    };

    const trigger = () => {
        if (coalesceTimer !== null) {
            return;
        }

        coalesceTimer = window.setTimeout(() => {
            coalesceTimer = null;
            run();
        }, coalesceMs);
    };

    const schedule = () => {
        if (timer !== null) {
            window.clearInterval(timer);
            timer = null;
        }

        if (pauseWhenHidden && document.hidden) {
            return;
        }

        timer = window.setInterval(run, socketLive ? slowMs : fastMs);
    };

    if (arkEchoEnabled()) {
        const connection = getArkEcho()?.connector?.pusher?.connection;

        if (connection) {
            socketLive = connection.state === LIVE;
            wasLive = socketLive;
            connection.bind('state_change', ({ current }) => {
                const live = current === LIVE;

                if (live === socketLive) {
                    return;
                }

                socketLive = live;
                schedule();

                if (live && wasLive) {
                    trigger();
                }

                wasLive = wasLive || live;
            });
        }
    }

    document.addEventListener('visibilitychange', () => {
        schedule();

        if (! document.hidden) {
            trigger();
        }
    });

    window.addEventListener('online', trigger);

    schedule();

    return {
        trigger,
        refreshNow: run,
    };
}
