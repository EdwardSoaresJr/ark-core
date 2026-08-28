@php
    $control = $inspectionControl ?? null;
    $coverage = $control['coverage'] ?? null;
    $actions = $control['actions'] ?? [];
    $attentionItems = $control['attention_items'] ?? [];
    $canRecord = (bool) ($control['can_record'] ?? $canRecordFindings ?? false);
    $canReset = (bool) ($control['can_reset'] ?? false);
    $recipients = $actions['recipients'] ?? [];
@endphp

<div id="inspect-rail" class="ops-review-rail-tab-panel ops-inspection ops-inspection--control">
    @if ($coverage === null)
        <p class="px-3 py-4 text-sm text-slate-600">Inspection is not available for this repair order.</p>
    @else
        <section class="ops-inspection-control px-3 py-3 sm:px-4" data-inspection-entry data-inspection-entry-builder>
            <p class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500">Inspection</p>
            <div class="mt-1" data-inspection-coverage data-inspection-posture="{{ $coverage['posture_key'] ?? '' }}">
                <p class="text-lg font-black text-slate-950">{{ $coverage['posture_headline'] ?? $coverage['posture_label'] }}</p>
                @if (filled($coverage['posture_detail'] ?? null))
                    <p class="text-sm font-semibold text-slate-600">{{ $coverage['posture_detail'] }}</p>
                @endif
            </div>
            @if (! empty($coverage['template_name']))
                <p class="mt-1 text-xs font-semibold text-slate-700" data-inspection-template-name>{{ $coverage['template_name'] }}</p>
            @endif
            @if ($control['assigned_technician_name'] ?? null)
                <p class="mt-1 text-xs text-slate-600">Assigned: {{ $control['assigned_technician_name'] }}</p>
            @endif

            @if ($canRecord && ! ($repairOrder->isTerminal() ?? false))
                <div class="mt-2">
                    @include('operations.repair-orders.inspection.partials.builder-template-select', [
                        'repairOrder' => $repairOrder,
                        'inspectionCoverage' => $coverage,
                    ])
                </div>
            @endif

            <div class="mt-3 flex flex-wrap gap-2">
                <a
                    href="{{ $coverage['capture_url'] ?? $actions['open_inspection_url'] }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex rounded-sm bg-slate-950 px-3 py-2 text-sm font-bold text-white hover:bg-slate-800"
                    data-inspection-open
                    data-inspection-cta
                    data-inspection-cta-capture
                    data-inspection-capture-cta
                    data-capture-surface="{{ $coverage['capture_surface'] ?? 'desktop_walk' }}"
                    data-desktop-walk-url="{{ $coverage['walk_url'] ?? $actions['open_inspection_url'] }}"
                    data-tablet-url="{{ $coverage['tablet_url'] ?? $actions['tablet_view_url'] }}"
                >Open Inspection</a>

                <a
                    href="{{ $actions['tablet_view_url'] }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex rounded-sm border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-800 hover:border-slate-400"
                    data-inspection-tablet
                    title="Force bay layout — for handing a tablet to the technician"
                >Bay layout</a>
            </div>

            <div
                class="ops-inspection-handoff mt-4 border-t border-slate-200 pt-3"
                data-inspection-handoff
                x-data="{
                    recipients: @js($recipients),
                    selectedId: @js($actions['default_recipient_id'] ?? null),
                    sendUrl: @js($actions['send_url'] ?? ''),
                    sending: null,
                    status: null,
                    error: null,
                    get selected() {
                        return this.recipients.find((row) => row.id === this.selectedId) || null;
                    },
                    get canSms() {
                        return Boolean(this.selected?.phone);
                    },
                    get canEmail() {
                        return Boolean(this.selected?.email);
                    },
                    get smsTitle() {
                        if (! this.selected) {
                            return 'Choose who to text';
                        }

                        return this.selected.phone
                            ? ('Text ' + this.selected.name + ' via shop SMS')
                            : (this.selected.name + ' has no phone on file');
                    },
                    get emailTitle() {
                        if (! this.selected) {
                            return 'Choose who to email';
                        }

                        return this.selected.email
                            ? ('Email ' + this.selected.name)
                            : (this.selected.name + ' has no email on file');
                    },
                    async send(channel) {
                        if (this.sending !== null || ! this.selectedId) {
                            return;
                        }

                        if (channel === 'sms' && ! this.canSms) {
                            return;
                        }

                        if (channel === 'email' && ! this.canEmail) {
                            return;
                        }

                        this.sending = channel;
                        this.status = null;
                        this.error = null;

                        try {
                            const response = await fetch(this.sendUrl, {
                                method: 'POST',
                                headers: {
                                    'Accept': 'application/json',
                                    'Content-Type': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                                },
                                body: JSON.stringify({
                                    user_id: this.selectedId,
                                    delivery: channel,
                                }),
                            });

                            const payload = await response.json().catch(() => ({}));

                            if (! response.ok) {
                                this.error = payload.message
                                    || payload.errors?.walk_link?.[0]
                                    || payload.errors?.user_id?.[0]
                                    || 'Could not send walk link.';
                                return;
                            }

                            this.status = payload.status || 'Walk link sent.';
                        } catch (e) {
                            this.error = 'Could not send walk link.';
                        } finally {
                            this.sending = null;
                        }
                    }
                }"
            >
                <p class="text-[11px] font-bold uppercase tracking-[0.1em] text-slate-500">Send walk link</p>

                @if (count($recipients) === 0)
                    <p class="mt-2 text-xs text-slate-500">No active staff to send to.</p>
                @else
                    <div class="mt-2">
                        <label class="block">
                            <span class="sr-only">Send to</span>
                            <select
                                class="w-full max-w-sm rounded-sm border border-slate-300 bg-white px-2 py-2 text-sm font-semibold text-slate-900"
                                x-model.number="selectedId"
                            >
                                @foreach ($recipients as $recipient)
                                    <option value="{{ $recipient['id'] }}">
                                        {{ $recipient['name'] }}
                                        · {{ $recipient['role_label'] }}
                                        @if ($recipient['is_assigned'])
                                            · Assigned
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <div class="mt-2 flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="inline-flex rounded-sm border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-800 hover:border-slate-400 disabled:pointer-events-none disabled:opacity-40"
                                x-bind:disabled="! canSms || sending !== null"
                                x-bind:title="smsTitle"
                                x-on:click="send('sms')"
                                data-inspection-handoff-sms
                            >
                                <span x-text="sending === 'sms' ? 'Sending…' : 'SMS'"></span>
                            </button>

                            <button
                                type="button"
                                class="inline-flex rounded-sm border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-800 hover:border-slate-400 disabled:pointer-events-none disabled:opacity-40"
                                x-bind:disabled="! canEmail || sending !== null"
                                x-bind:title="emailTitle"
                                x-on:click="send('email')"
                                data-inspection-handoff-email
                            >
                                <span x-text="sending === 'email' ? 'Sending…' : 'Email'"></span>
                            </button>
                        </div>

                        <p class="mt-2 text-xs font-semibold text-emerald-700" x-show="status" x-text="status" x-cloak></p>
                        <p class="mt-2 text-xs font-semibold text-rose-700" x-show="error" x-text="error" x-cloak></p>
                    </div>
                @endif
            </div>

            <div
                class="mt-3"
                x-data="{
                    copied: false,
                    url: @js($actions['copy_url'] ?? ''),
                    async copyLink() {
                        try {
                            await navigator.clipboard.writeText(this.url);
                            this.copied = true;
                            setTimeout(() => this.copied = false, 1600);
                        } catch (e) {}
                    }
                }"
            >
                <button
                    type="button"
                    class="text-xs font-semibold text-slate-500 underline-offset-2 hover:text-slate-800 hover:underline"
                    x-on:click="copyLink()"
                    x-text="copied ? 'Link copied' : 'Copy link'"
                ></button>
            </div>

            @if ($canReset)
                <details class="ops-inspection-reset mt-4 border-t border-slate-200 pt-3">
                    <summary class="cursor-pointer text-xs font-bold text-rose-800">Reset inspection walk</summary>
                    <p class="mt-2 text-xs leading-5 text-slate-600">
                        Clears checklist conditions, measurements, and walk photos so the shop can redo DVI.
                        Other Findings stay. Admin and Advisor only.
                    </p>
                    <form
                        method="post"
                        action="{{ $actions['reset_url'] }}"
                        class="mt-3 space-y-2"
                        onsubmit="return confirm('Reset this inspection walk? Conditions, measurements, and walk photos will be cleared.');"
                    >
                        @csrf
                        <label class="flex items-start gap-2 text-xs font-semibold text-slate-700">
                            <input type="checkbox" name="confirm" value="1" required class="mt-0.5">
                            <span>I understand this clears the walk and cannot be undone.</span>
                        </label>
                        <button
                            type="submit"
                            class="inline-flex rounded-sm border border-rose-300 bg-rose-50 px-3 py-1.5 text-xs font-bold text-rose-900 hover:border-rose-400"
                        >Reset walk</button>
                    </form>
                </details>
            @endif

            @if (count($attentionItems) > 0)
                <div class="mt-5 border-t border-slate-200 pt-4">
                    <p class="text-[11px] font-bold uppercase tracking-[0.1em] text-slate-500">Findings & evidence</p>
                    <ul class="mt-2 divide-y divide-slate-100">
                        @foreach ($attentionItems as $item)
                            <li class="py-2">
                                <a href="{{ $item['walk_url'] }}" target="_blank" rel="noopener noreferrer" class="block hover:bg-slate-50">
                                    <p class="text-sm font-bold text-slate-950">{{ $item['label'] }}</p>
                                    <p class="text-xs text-slate-600">
                                        {{ $item['condition'] }}
                                        @if ($item['category'])
                                            · {{ $item['category'] }}
                                        @endif
                                        @if ($item['measurement'])
                                            · {{ $item['measurement'] }}
                                        @endif
                                        @if ($item['photo_count'] > 0)
                                            · {{ $item['photo_count'] }} photo{{ $item['photo_count'] === 1 ? '' : 's' }}
                                        @endif
                                    </p>
                                    @if (filled($item['note']))
                                        <p class="mt-0.5 text-xs text-slate-500">{{ \Illuminate\Support\Str::limit($item['note'], 120) }}</p>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @else
                <p class="mt-5 text-sm text-slate-500">
                    @if (($coverage['checked'] ?? 0) === 0)
                        No inspection points checked yet. Open Inspection to start the walk.
                    @else
                        No monitor / needs-attention / failed items yet. Open Inspection to continue.
                    @endif
                </p>
            @endif

            @unless ($canRecord)
                <p class="mt-4 border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950">
                    Recording is limited to the assigned technician or an advisor.
                </p>
            @endunless
        </section>
    @endif
</div>
