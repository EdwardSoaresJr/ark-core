export function tablerBadgeClasses(summary = {}) {
    if (summary.has_live_calls) {
        return ['bg-red', 'text-red-fg'];
    }

    if (Number(summary.since_last_shift_count ?? 0) > 0) {
        return ['bg-orange', 'text-orange-fg'];
    }

    return ['bg-red-lt'];
}
