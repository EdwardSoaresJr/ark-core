<x-cloud.shell title="Welcome" :nav="false" :footer="false">
    <div class="min-h-screen flex items-center justify-center px-5 py-16">
        <div class="w-full max-w-lg cloud-stage">
            <div class="text-center">
                <img
                    src="{{ asset('assets/ARK_SMS_FINAL_DROP_IN_PACK/android/ark-96x96.png') }}"
                    alt="ARK"
                    class="mx-auto h-12 w-12 rounded-xl mb-10"
                    width="48"
                    height="48"
                >

                <p class="inline-flex items-center rounded-full border border-[var(--cloud-cerulean)]/30 bg-[var(--cloud-mist)] px-3 py-1 text-xs font-semibold uppercase tracking-[0.12em] text-[var(--cloud-cerulean-deep)]">
                    Free trial
                </p>

                <p class="cloud-display mt-6 text-sm font-semibold uppercase tracking-[0.2em] text-[var(--cloud-cerulean)]">
                    Welcome to ARK{{ filled($ownerName ?? null) && $ownerName !== 'there' ? ', '.$ownerName : '' }}
                </p>

                <h1 class="cloud-display mt-5 text-4xl sm:text-5xl font-semibold leading-tight text-[var(--cloud-ink)]">
                    Let’s get {{ $shopName }} ready.
                </h1>

                <p class="mt-6 text-xl text-[var(--cloud-ink-soft)] leading-relaxed">
                    <span class="cloud-display font-semibold text-[var(--cloud-ink)]">Your workspace is waiting.</span>
                </p>
            </div>

            {{-- Day-one mission — answers “What do I do now?” before Billing/Domains --}}
            <div class="mt-12 rounded-2xl border border-[var(--cloud-line)] bg-white/90 p-6 sm:p-7 text-left shadow-[0_24px_60px_-48px_rgba(0,0,0,0.35)]">
                <div class="flex items-baseline justify-between gap-3">
                    <p class="text-sm font-semibold uppercase tracking-[0.14em] text-[var(--cloud-muted)]">Next</p>
                    <p class="text-sm font-semibold text-[var(--cloud-ink)] tabular-nums">
                        {{ $missionComplete }} / {{ $missionTotal }} Complete
                    </p>
                </div>

                <ul class="mt-5 space-y-3.5">
                    @foreach ($missionSteps as $step)
                        <li class="flex items-start gap-3 text-[var(--cloud-ink)]">
                            <span
                                class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded border {{ $step['done'] ? 'border-[var(--cloud-cerulean)] bg-[var(--cloud-cerulean)] text-white' : 'border-[var(--cloud-line)] bg-white' }}"
                                aria-hidden="true"
                            >
                                @if ($step['done'])
                                    <svg class="h-3 w-3" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2"><path d="M2.5 6.5 4.8 9 9.5 3.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                @endif
                            </span>
                            <span class="text-base sm:text-lg leading-snug {{ $step['done'] ? 'text-[var(--cloud-muted)] line-through' : '' }}">
                                {{ $step['label'] }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="mt-10 text-center">
                <a href="{{ \App\Ark\Platform\Cloud\CloudUrls::route('dashboard') }}" class="cloud-btn-primary text-base px-8 !py-3.5">
                    Continue →
                </a>
            </div>

            <p class="mt-8 text-center text-sm text-[var(--cloud-muted)]">
                Trial workspace · {{ ($slug ?? 'shop') }}.arksms.com
            </p>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            window.arkCloudFunnel?.track('cloud_funnel_completed');
        });
    </script>
</x-cloud.shell>
