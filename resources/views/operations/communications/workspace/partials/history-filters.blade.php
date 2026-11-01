@php
    /** @var array<string, string> $filters */
@endphp

<form
    method="GET"
    action="{{ route('operations.communications.history') }}"
    class="p-2 border-bottom flex-shrink-0"
    data-comms-history-filters
>
    <div class="row g-2 align-items-end">
        <div class="col-12">
            <label class="visually-hidden" for="comms-history-q">Search</label>
            <div class="input-icon">
                <span class="input-icon-addon"><x-tabler.icon name="search" /></span>
                <input
                    id="comms-history-q"
                    type="search"
                    name="q"
                    value="{{ $filters['q'] ?? '' }}"
                    placeholder="Phone or customer name"
                    class="form-control form-control-sm"
                >
            </div>
        </div>
        <div class="col-6">
            <label class="form-label small mb-1" for="comms-history-from">From</label>
            <input
                id="comms-history-from"
                type="date"
                name="from"
                value="{{ $filters['from'] ?? '' }}"
                class="form-control form-control-sm"
            >
        </div>
        <div class="col-6">
            <label class="form-label small mb-1" for="comms-history-to">To</label>
            <input
                id="comms-history-to"
                type="date"
                name="to"
                value="{{ $filters['to'] ?? '' }}"
                class="form-control form-control-sm"
            >
        </div>
        <div class="col">
            <label class="visually-hidden" for="comms-history-media">Media</label>
            <select id="comms-history-media" name="media" class="form-select form-select-sm">
                <option value="" @selected(($filters['media'] ?? '') === '')>All calls</option>
                <option value="recorded" @selected(($filters['media'] ?? '') === 'recorded')>Recorded / voicemail</option>
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-sm btn-primary">Search</button>
        </div>
    </div>
</form>
