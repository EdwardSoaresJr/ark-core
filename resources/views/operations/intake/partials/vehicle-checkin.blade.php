<section
    class="ops-intake-checkin ops-intake-checkin--column"
    x-data="arkIntakeVehicleCheckin({
        lookupUrl: @js(route('operations.intake.vehicles.lookup')),
        intakeUrl: @js(route('operations.intake.create', $intakeWorkspaceParams ?? [])),
    })"
>
    <div class="ops-intake-find-search">
        <label for="intake-vehicle-checkin" class="ops-index-field-label">Quick check-in</label>
        <p class="ops-intake-checkin-lead">VIN or plate on file — open RO directly.</p>
        <div class="ops-intake-checkin-row">
            <input
                id="intake-vehicle-checkin"
                x-model="lookup"
                type="search"
                inputmode="text"
                autocapitalize="characters"
                autocomplete="off"
                enterkeyhint="go"
                placeholder="VIN or plate"
                class="ops-intake-control ops-intake-checkin-input"
                @keydown.enter.prevent="submit()"
            >
            <button
                type="button"
                class="ops-index-btn ops-index-btn--primary ops-intake-checkin-btn"
                :disabled="checking"
                @click="submit()"
            >
                <span x-show="! checking">Check In</span>
                <span x-show="checking" x-cloak>Looking…</span>
            </button>
        </div>
        <p
            x-show="message"
            x-text="message"
            x-cloak
            class="ops-intake-checkin-message"
            aria-live="polite"
        ></p>
    </div>
</section>
