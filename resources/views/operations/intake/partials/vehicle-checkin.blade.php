<section
    data-intake-vehicle-checkin
    x-data="arkIntakeVehicleCheckin({
        lookupUrl: @js(route('operations.intake.vehicles.lookup')),
        intakeUrl: @js(route('operations.intake.create', $intakeWorkspaceParams ?? [])),
    })"
>
    <label for="intake-vehicle-checkin" class="form-label mb-0">Quick check-in</label>
    <div class="form-hint mt-0 mb-2">VIN or plate on file - open RO directly.</div>
    <div class="input-group">
        <input
            id="intake-vehicle-checkin"
            x-model="lookup"
            type="search"
            inputmode="text"
            autocapitalize="characters"
            autocomplete="off"
            enterkeyhint="go"
            placeholder="VIN or plate"
            class="form-control"
            @keydown.enter.prevent="submit()"
        >
        <button type="button" class="btn btn-primary" :disabled="checking" @click="submit()">
            <span x-show="! checking">Check In</span>
            <span x-show="checking" x-cloak>Looking…</span>
        </button>
    </div>
    <div x-show="message" x-text="message" x-cloak class="small text-secondary mt-1" aria-live="polite"></div>
</section>
