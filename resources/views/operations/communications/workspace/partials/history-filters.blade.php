@php
    /** @var array<string, string> $filters */
@endphp

<form
    method="GET"
    action="{{ route('operations.communications.history') }}"
    class="ops-comms-history-filters"
>
    <label class="ops-comms-history-filters__field ops-comms-history-filters__field--search">
        <span class="ops-comms-history-filters__label">Search</span>
        <input
            type="search"
            name="q"
            value="{{ $filters['q'] ?? '' }}"
            placeholder="Phone or customer name"
            class="ops-comms-history-filters__input"
        >
    </label>

    <label class="ops-comms-history-filters__field">
        <span class="ops-comms-history-filters__label">From</span>
        <input
            type="date"
            name="from"
            value="{{ $filters['from'] ?? '' }}"
            class="ops-comms-history-filters__input"
        >
    </label>

    <label class="ops-comms-history-filters__field">
        <span class="ops-comms-history-filters__label">To</span>
        <input
            type="date"
            name="to"
            value="{{ $filters['to'] ?? '' }}"
            class="ops-comms-history-filters__input"
        >
    </label>

    <label class="ops-comms-history-filters__field">
        <span class="ops-comms-history-filters__label">Media</span>
        <select name="media" class="ops-comms-history-filters__input">
            <option value="" @selected(($filters['media'] ?? '') === '')>All calls</option>
            <option value="recorded" @selected(($filters['media'] ?? '') === 'recorded')>Recorded / voicemail</option>
        </select>
    </label>

    <div class="ops-comms-history-filters__actions">
        <button type="submit" class="ops-comms-history-filters__submit">Search</button>
    </div>
</form>
