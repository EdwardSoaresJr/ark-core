@php
    use App\Ark\Operations\Leads\Public\PublicBookWizardConcerns;

    $closeUrl = \App\Ark\Customer\CustomerSurfaceUrls::publicHome();
    $home = $vehicleHome ?? [];
    $vehicle = $home['vehicle'] ?? null;
    $relationship = $home['relationship'] ?? [];
    $context = $home['context'] ?? [];
    $radar = $relationship['still_on_radar'] ?? [];
    $serviceLines = $relationship['last_service_lines'] ?? [];
    $firstName = trim((string) ($home['customer']['first_name'] ?? ''));
    $vehicleId = (int) ($vehicle['id'] ?? 0);
    $vehicleCount = count($recognition['vehicles'] ?? []);
    $intents = PublicBookWizardConcerns::conciergeIntents();
@endphp

<div
    class="public-book-wizard public-book-concierge"
    x-data="publicBookConcierge({
        somethingElse: @js(PublicBookWizardConcerns::SOMETHING_ELSE),
    })"
>
    <div class="public-book-wizard__chrome">
        <div class="public-book-wizard__toolbar">
            @if ($vehicleCount > 1)
                <a href="{{ route('public.book') }}" class="public-book-wizard__text-btn">Vehicles</a>
            @else
                <span class="public-book-wizard__toolbar-spacer"></span>
            @endif
            <a
                href="{{ $closeUrl }}"
                class="public-book-wizard__close"
                data-public-book-close
                aria-label="Close"
            >
                <span aria-hidden="true">×</span>
            </a>
        </div>
    </div>

    <p class="public-book-wizard__kicker">{{ $shopName }}</p>
    <h1 class="public-book-wizard__question">
        @if ($firstName !== '')
            Welcome back, {{ $firstName }}.
        @else
            Welcome back.
        @endif
    </h1>

    <div class="public-book-concierge__vehicle">
        <p class="public-book-concierge__vehicle-name">{{ $vehicle['label'] ?? 'Your vehicle' }}</p>
        @if (filled($relationship['last_here_label'] ?? null))
            <p class="public-book-concierge__last-here">
                <span class="public-book-concierge__check" aria-hidden="true">✓</span>
                {{ $relationship['last_here_label'] }}
            </p>
        @endif
    </div>

    @if ($context !== [])
        <ul class="public-book-vehicle-home__signals">
            @foreach ($context as $signal)
                <li>{{ $signal['label'] }}</li>
            @endforeach
        </ul>
    @endif

    @if ($radar !== [])
        <section class="public-book-concierge__block" aria-label="Still on our radar">
            <h2 class="public-book-concierge__heading">Still on our radar</h2>
            <ul class="public-book-concierge__bullets">
                @foreach ($radar as $item)
                    <li>{{ $item['summary'] }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($serviceLines !== [])
        <section class="public-book-concierge__block" aria-label="Last service">
            <h2 class="public-book-concierge__heading">Last service</h2>
            <ul class="public-book-concierge__bullets public-book-concierge__bullets--plain">
                @foreach ($serviceLines as $line)
                    <li>{{ $line }}</li>
                @endforeach
            </ul>
        </section>
    @elseif (filled($relationship['last_service_label'] ?? null))
        <section class="public-book-concierge__block" aria-label="Last service">
            <h2 class="public-book-concierge__heading">Last service</h2>
            <p class="public-book-concierge__plain">{{ $relationship['last_service_label'] }}</p>
        </section>
    @endif

    <form
        method="GET"
        action="{{ route('public.book') }}"
        class="public-book-concierge__form"
        x-ref="form"
        @submit="prepareContinue($event)"
    >
        <input type="hidden" name="schedule" value="1">
        <input type="hidden" name="vehicle" value="{{ $vehicleId }}">
        <input type="hidden" name="intent" x-model="intent">
        <input type="hidden" name="intent_details" x-model="intentDetails">

        @if ($radar !== [])
            <section class="public-book-concierge__block" aria-label="While it’s here">
                <h2 class="public-book-concierge__heading">While it’s here…</h2>
                <p class="public-book-wizard__hint">Optional — check anything you want us to look at this visit.</p>
                <div class="public-book-vehicle-home__radar-list">
                    @foreach ($radar as $item)
                        <label class="public-book-vehicle-home__radar-item">
                            <input
                                type="checkbox"
                                name="radar[]"
                                value="{{ $item['concern_id'] }}"
                            >
                            <span>{{ $item['summary'] }}</span>
                        </label>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="public-book-concierge__block" aria-label="What would you like to do">
            <h2 class="public-book-concierge__heading">What would you like to do?</h2>
            <div class="public-book-wizard__choices">
                @foreach ($intents as $intentOption)
                    <button
                        type="button"
                        class="public-book-wizard__choice"
                        :class="{ 'is-selected': intent === @js($intentOption) }"
                        @click="selectIntent(@js($intentOption))"
                    >
                        {{ $intentOption }}
                    </button>
                @endforeach
            </div>

            <div class="public-book-concierge__else" x-show="intent === somethingElse" x-cloak>
                <label class="sr-only" for="book_concierge_details">Tell us what’s going on</label>
                <textarea
                    id="book_concierge_details"
                    class="public-book-wizard__textarea"
                    rows="3"
                    maxlength="2000"
                    placeholder="What’s going on?"
                    x-model="intentDetails"
                ></textarea>
            </div>
        </section>

        <p class="public-book-wizard__error" x-show="errorMessage" x-text="errorMessage" x-cloak></p>

        {{-- Known intents one-tap through; Something Else needs Continue after details. --}}
        <div class="public-book-wizard__actions" x-show="intent === somethingElse" x-cloak>
            <button type="submit" class="public-book-wizard__primary">
                Continue
            </button>
        </div>
    </form>
</div>
