function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

async function postJson(url, payload) {
    await fetch(url, {
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
}

export function arkLearnVideo(config = {}) {
    return {
        articleKey: config.articleKey ?? '',
        videoKey: config.videoKey ?? 'main',
        videoUrl: config.videoUrl ?? '',
        hasVideo: Boolean(config.hasVideo),
        lastSavedAt: 0,
        statusMessage: '',

        init() {
            // Video elements initialize when media is present.
        },

        onTimeUpdate() {
            if (! this.hasVideo) {
                return;
            }

            const player = this.$refs.player;

            if (! player || player.duration <= 0) {
                return;
            }

            const now = Date.now();

            if (now - this.lastSavedAt < 5000) {
                return;
            }

            this.lastSavedAt = now;
            this.saveProgress();
        },

        async saveProgress() {
            if (! this.hasVideo || this.videoUrl === '') {
                return;
            }

            const player = this.$refs.player;

            if (! player || player.duration <= 0) {
                return;
            }

            const percent = Math.min(100, Math.floor((player.currentTime / player.duration) * 100));

            try {
                await postJson(this.videoUrl, {
                    article_key: this.articleKey,
                    video_key: this.videoKey,
                    percent_watched: percent,
                    watched_seconds: Math.floor(player.currentTime),
                    last_position_seconds: Math.floor(player.currentTime),
                    completed: player.ended || percent >= 95,
                });
            } catch {
                // Progress saves retry on next tick.
            }
        },

        async markComplete() {
            this.statusMessage = 'Video complete.';
            await this.saveProgress();
        },
    };
}
