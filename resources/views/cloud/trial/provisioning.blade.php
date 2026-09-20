<x-cloud.shell title="Creating your shop" :nav="false" :footer="false">
    <div
        class="min-h-screen flex items-center justify-center px-5 py-16"
        x-data="cloudProvisioning(@js(\App\Ark\Platform\Cloud\CloudUrls::route('welcome')))"
        x-init="start()"
    >
        <div class="w-full max-w-md cloud-stage">
            <p class="text-center">
                <span class="inline-flex items-center rounded-full border border-[var(--cloud-cerulean)]/30 bg-[var(--cloud-mist)] px-3 py-1 text-xs font-semibold uppercase tracking-[0.12em] text-[var(--cloud-cerulean-deep)]">
                    Free trial
                </span>
            </p>
            <p class="mt-6 text-sm font-semibold uppercase tracking-[0.16em] text-[var(--cloud-cerulean)] text-center">
                Let’s get your shop ready
            </p>
            <h1 class="cloud-display mt-3 text-center text-3xl font-semibold">
                {{ $shopName }}
            </h1>

            <div class="mt-10 rounded-2xl border border-[var(--cloud-line)] bg-white/90 p-8 shadow-[0_40px_100px_-50px_rgba(11,18,32,0.5)]">
                <ul class="space-y-5">
                    <template x-for="(item, index) in items" :key="item.key">
                        <li class="flex items-center gap-3">
                            <span
                                class="inline-flex h-7 w-7 items-center justify-center rounded-full text-sm font-bold"
                                :class="item.state === 'done'
                                    ? 'bg-[var(--cloud-mist)] text-[var(--cloud-cerulean)]'
                                    : (item.state === 'active' ? 'bg-[var(--cloud-cerulean)] text-white cloud-pulse' : 'bg-[var(--cloud-paper)] text-[var(--cloud-muted)]')"
                                x-text="item.state === 'done' ? '✓' : (item.state === 'active' ? '⟳' : '○')"
                            ></span>
                            <span
                                class="text-lg"
                                :class="item.state === 'pending' ? 'text-[var(--cloud-muted)]' : 'text-[var(--cloud-ink)] font-medium'"
                                x-text="item.label + (item.state === 'active' ? '...' : '')"
                            ></span>
                        </li>
                    </template>
                </ul>

                <p class="mt-8 text-center text-[var(--cloud-muted)] leading-relaxed">
                    Setting up your trial workspace.<br>
                    <span class="font-semibold text-[var(--cloud-ink-soft)]">Usually less than a minute.</span>
                </p>
            </div>
        </div>
    </div>

    <script>
        function cloudProvisioning(welcomeUrl) {
            return {
                welcomeUrl,
                items: [
                    { key: 'account', label: 'Preparing your account', state: 'pending' },
                    { key: 'shop', label: 'Preparing your shop profile', state: 'pending' },
                    { key: 'workspace', label: 'Preparing your workspace', state: 'pending' },
                    { key: 'ready', label: 'Almost there', state: 'pending' },
                ],
                start() {
                    const delays = [400, 900, 1400, 2200];
                    this.items.forEach((item, index) => {
                        setTimeout(() => {
                            this.items.forEach((row, i) => {
                                if (i < index) row.state = 'done';
                                else if (i === index) row.state = 'active';
                                else row.state = 'pending';
                            });
                        }, delays[index]);
                    });

                    setTimeout(() => {
                        this.items.forEach((row) => { row.state = 'done'; });
                    }, 3200);

                    setTimeout(() => {
                        window.location.href = this.welcomeUrl;
                    }, 3900);
                },
            };
        }
    </script>
</x-cloud.shell>
