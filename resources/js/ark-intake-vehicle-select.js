export function arkIntakeVehicleSelect(config = {}) {
    const blobs = Array.isArray(config.blobs) ? config.blobs : [];

    return {
        query: '',
        total: Number(config.total ?? blobs.length),
        blobs,

        matches(searchBlob) {
            const normalized = String(searchBlob ?? '').trim();

            if (normalized === '') {
                return true;
            }

            const q = this.query.trim().toLowerCase();

            if (q === '') {
                return true;
            }

            const terms = q.split(/\s+/u).filter((term) => term !== '');

            if (terms.length === 0) {
                return true;
            }

            return terms.every((term) => normalized.includes(term));
        },

        get filtering() {
            return this.query.trim() !== '';
        },

        get visibleCount() {
            return this.blobs.filter((blob) => this.matches(blob)).length;
        },

        get compact() {
            return this.total >= 8;
        },
    };
}
