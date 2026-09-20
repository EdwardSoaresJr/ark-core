function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

async function postJson(url, payload) {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
        },
        credentials: 'same-origin',
        body: JSON.stringify(payload),
    });

    const data = await response.json().catch(() => ({}));

    if (! response.ok) {
        throw new Error(data.message ?? 'Request failed');
    }

    return data;
}

export function arkLearnProgress(config = {}) {
    return {
        articleKey: config.articleKey ?? '',
        minActiveSeconds: Number(config.minActiveSeconds ?? 90),
        checkpointMinActiveSeconds: Number(config.checkpointMinActiveSeconds ?? 20),
        idleSeconds: Number(config.idleSeconds ?? 120),
        completed: Boolean(config.completed),
        activeSeconds: Number(config.activeSeconds ?? 0),
        reachedCheckpointKeys: Array.isArray(config.reachedCheckpointKeys)
            ? [...config.reachedCheckpointKeys]
            : [],
        headings: [],
        sectionActiveSeconds: {},
        sectionVisibleSince: {},
        heartbeatTimer: null,
        interactionTimer: null,
        lastInteractionAt: Date.now(),
        isVisible: true,
        isInteracting: false,
        completing: false,
        statusMessage: '',
        errorMessage: '',

        init() {
            if (this.completed || this.articleKey === '') {
                return;
            }

            this.bindInteractionTracking();
            this.scanHeadings();
            this.startHeartbeat();
            this.observeHeadings();
        },

        destroy() {
            if (this.heartbeatTimer) {
                window.clearInterval(this.heartbeatTimer);
            }

            if (this.interactionTimer) {
                window.clearInterval(this.interactionTimer);
            }
        },

        bindInteractionTracking() {
            const markInteraction = () => {
                this.lastInteractionAt = Date.now();
                this.isInteracting = true;
            };

            ['scroll', 'click', 'keydown', 'touchstart', 'mousemove'].forEach((eventName) => {
                document.addEventListener(eventName, markInteraction, { passive: true });
            });

            document.addEventListener('visibilitychange', () => {
                this.isVisible = document.visibilityState === 'visible';
            });

            this.interactionTimer = window.setInterval(() => {
                const idleMs = this.idleSeconds * 1000;

                this.isInteracting = Date.now() - this.lastInteractionAt <= idleMs;
            }, 1000);
        },

        scanHeadings() {
            const prose = document.querySelector('.ops-learn-prose');

            if (! prose) {
                this.headings = [{
                    key: 'article',
                    index: 0,
                    label: 'Article',
                    element: null,
                    reached: this.reachedCheckpointKeys.includes('article'),
                }];

                return;
            }

            const nodes = prose.querySelectorAll('h2, h3');

            if (nodes.length === 0) {
                this.headings = [{
                    key: 'article',
                    index: 0,
                    label: 'Full guide',
                    element: prose,
                    reached: this.reachedCheckpointKeys.includes('article'),
                }];

                return;
            }

            this.headings = Array.from(nodes).map((element, index) => {
                const key = `h-${index}`;

                return {
                    key,
                    index,
                    label: element.textContent?.trim() || `Section ${index + 1}`,
                    element,
                    reached: this.reachedCheckpointKeys.includes(key),
                };
            });
        },

        observeHeadings() {
            if (typeof IntersectionObserver === 'undefined') {
                return;
            }

            const observer = new IntersectionObserver((entries) => {
                for (const entry of entries) {
                    const heading = this.headings.find((row) => row.element === entry.target);

                    if (! heading || heading.reached) {
                        continue;
                    }

                    if (entry.isIntersecting) {
                        if (! this.sectionVisibleSince[heading.key]) {
                            this.sectionVisibleSince[heading.key] = Date.now();
                            this.sectionActiveSeconds[heading.key] = 0;
                        }

                        this.tickSectionActive(heading.key);
                    }
                }
            }, { threshold: 0.45 });

            for (const heading of this.headings) {
                if (heading.element) {
                    observer.observe(heading.element);
                }
            }

            window.setInterval(() => {
                for (const heading of this.headings) {
                    if (! heading.reached && this.sectionVisibleSince[heading.key]) {
                        this.tickSectionActive(heading.key);
                        this.tryReachCheckpoint(heading);
                    }
                }
            }, 1000);
        },

        tickSectionActive(headingKey) {
            if (! this.isVisible || ! this.isInteracting) {
                return;
            }

            this.sectionActiveSeconds[headingKey] = Number(this.sectionActiveSeconds[headingKey] ?? 0) + 1;
        },

        startHeartbeat() {
            const beat = async () => {
                try {
                    const result = await postJson(config.heartbeatUrl, {
                        article_key: this.articleKey,
                        visible: this.isVisible,
                        interacting: this.isInteracting,
                    });

                    this.activeSeconds = Number(result.active_seconds ?? this.activeSeconds);
                    this.errorMessage = '';
                } catch {
                    // Heartbeat retries on the next interval.
                }
            };

            beat();
            this.heartbeatTimer = window.setInterval(beat, 15000);
        },

        async tryReachCheckpoint(heading) {
            if (heading.reached) {
                return;
            }

            const sectionSeconds = Number(this.sectionActiveSeconds[heading.key] ?? 0);

            if (sectionSeconds < this.checkpointMinActiveSeconds) {
                return;
            }

            if (! this.isVisible || ! this.isInteracting) {
                return;
            }

            try {
                await postJson(config.checkpointUrl, {
                    article_key: this.articleKey,
                    checkpoint_key: heading.key,
                    checkpoint_index: heading.index,
                    section_active_seconds: sectionSeconds,
                });

                heading.reached = true;
                this.reachedCheckpointKeys.push(heading.key);
                this.statusMessage = `Section recorded: ${heading.label}`;
                this.errorMessage = '';
            } catch (error) {
                this.errorMessage = error.message ?? 'Could not record section.';
            }
        },

        allCheckpointsReached() {
            return this.headings.length > 0
                && this.headings.every((heading) => heading.reached);
        },

        remainingActiveSeconds() {
            return Math.max(0, this.minActiveSeconds - this.activeSeconds);
        },

        canComplete() {
            return this.allCheckpointsReached()
                && this.remainingActiveSeconds() === 0
                && ! this.completed
                && ! this.completing;
        },

        async completeArticle() {
            if (! this.canComplete()) {
                return;
            }

            this.completing = true;
            this.errorMessage = '';

            try {
                await postJson(config.completeUrl, {
                    article_key: this.articleKey,
                    checkpoint_keys: this.headings.map((heading) => heading.key),
                });

                this.completed = true;
                this.statusMessage = 'Guide complete. You can return to the workboard.';
                window.location.assign(config.resumeUrl ?? '/app');
            } catch (error) {
                this.errorMessage = error.message ?? 'Could not complete this guide.';
            } finally {
                this.completing = false;
            }
        },
    };
}
