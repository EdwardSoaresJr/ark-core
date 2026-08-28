<x-cloud.shell title="{{ $shopName }}" :footer="false">
    <div class="mx-auto max-w-5xl px-5 sm:px-8 py-12 sm:py-16 cloud-stage">
        <p class="inline-flex items-center rounded-full border border-[var(--cloud-cerulean)]/30 bg-[var(--cloud-mist)] px-3 py-1 text-xs font-semibold uppercase tracking-[0.12em] text-[var(--cloud-cerulean-deep)]">
            Free trial
        </p>
        <p class="mt-4 text-[var(--cloud-muted)] text-lg">{{ $greeting }}, {{ $ownerName }}</p>
        <h1 class="cloud-display mt-2 text-4xl sm:text-5xl font-semibold">{{ $shopName }}</h1>
        <p class="mt-3 text-xl text-[var(--cloud-ink-soft)]">
            Your workspace is waiting.
        </p>

        <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {{-- Mission first — not Billing / Domains on day one --}}
            <div class="sm:col-span-2 lg:col-span-2 rounded-2xl border border-[var(--cloud-cerulean)] bg-white p-6 sm:p-7 shadow-[0_24px_60px_-40px_rgba(0,153,204,0.55)]">
                <div class="flex items-baseline justify-between gap-3">
                    <p class="text-sm font-semibold uppercase tracking-[0.14em] text-[var(--cloud-muted)]">Your first jobs</p>
                    <p class="text-sm font-semibold text-[var(--cloud-ink)] tabular-nums">
                        {{ $missionComplete }} / {{ $missionTotal }} Complete
                    </p>
                </div>
                <p class="mt-2 text-[var(--cloud-ink-soft)]">
                    Open the workspace and run the floor — start here.
                </p>
                <ul class="mt-5 space-y-3">
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
                            <span class="text-base leading-snug {{ $step['done'] ? 'text-[var(--cloud-muted)] line-through' : '' }}">
                                {{ $step['label'] }}
                            </span>
                        </li>
                    @endforeach
                </ul>
                <a
                    href="{{ \App\Ark\Platform\Cloud\CloudUrls::route('workspace.open') }}"
                    data-cloud-event="cloud_funnel_open_workspace"
                    class="cloud-btn-primary mt-6 !px-6 !py-3"
                >
                    Open Workspace →
                </a>
            </div>

            <div class="rounded-2xl border border-[var(--cloud-line)] bg-white/85 p-6">
                <p class="text-sm font-semibold uppercase tracking-[0.14em] text-[var(--cloud-muted)]">Status</p>
                <p class="mt-3 flex items-center gap-2 text-xl font-semibold text-[var(--cloud-ink)]">
                    <span class="inline-block h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                    Trial ready
                </p>
                <p class="mt-3 text-sm text-[var(--cloud-muted)]">{{ $workspaceHost }}</p>
            </div>

            <div class="rounded-2xl border border-[var(--cloud-line)] bg-white/85 p-6 sm:col-span-2 lg:col-span-1">
                <p class="text-sm font-semibold uppercase tracking-[0.14em] text-[var(--cloud-muted)]">Your team</p>
                <p class="mt-3 text-lg font-medium">1 Owner</p>
                <p class="mt-1 text-sm text-[var(--cloud-muted)]">{{ $ownerName }}</p>
            </div>
        </div>
    </div>
</x-cloud.shell>
