@php
    $control = $inspectionControl ?? null;
    $coverage = $control['coverage'] ?? null;
    $actions = $control['actions'] ?? [];
    $attentionItems = $control['attention_items'] ?? [];
    $canRecord = (bool) ($control['can_record'] ?? $canRecordFindings ?? false);
    $canReset = (bool) ($control['can_reset'] ?? false);
    $recipients = $actions['recipients'] ?? [];
@endphp

<div id="inspect-rail" data-inspection-control>
    @if ($coverage === null)
        <p class="text-secondary mb-0">Inspection is not available for this repair order.</p>
    @else
        <section class="d-flex flex-column gap-3" data-inspection-entry data-inspection-entry-builder>
            <div>
                <div class="subheader">Inspection</div>
                <div data-inspection-coverage data-inspection-posture="{{ $coverage['posture_key'] ?? '' }}">
                    <div class="h2 mb-0">{{ $coverage['posture_headline'] ?? $coverage['posture_label'] }}</div>
                    @if (filled($coverage['posture_detail'] ?? null))
                        <div class="fw-medium text-secondary">{{ $coverage['posture_detail'] }}</div>
                    @endif
                </div>
                @if (! empty($coverage['template_name']))
                    <div class="small fw-medium mt-1" data-inspection-template-name>{{ $coverage['template_name'] }}</div>
                @endif
                @if ($control['assigned_technician_name'] ?? null)
                    <div class="small text-secondary mt-1">Assigned: {{ $control['assigned_technician_name'] }}</div>
                @endif

                @if (($recommendationAwareness['headline'] ?? null))
                    <button
                        type="button"
                        @class([
                            'btn btn-link btn-sm px-0 mt-1',
                            'text-danger' => $recommendationAwareness['strong'] ?? false,
                        ])
                        @click="window.arkSelectRepairOrderWorkspaceTab && window.arkSelectRepairOrderWorkspaceTab('recommendations')"
                    >{{ $recommendationAwareness['headline'] }}</button>
                @endif
            </div>

            @if ($canRecord && ! ($repairOrder->isTerminal() ?? false))
                {{-- The template selector is shared with the inspection views and moves with them. --}}
                <x-operations.worksheet-island>
                    @include('operations.repair-orders.inspection.partials.builder-template-select', [
                        'repairOrder' => $repairOrder,
                        'inspectionCoverage' => $coverage,
                    ])
                </x-operations.worksheet-island>
            @endif

            <div class="btn-list">
                <a
                    href="{{ $coverage['capture_url'] ?? $actions['open_inspection_url'] }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="btn btn-primary"
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
                    class="btn"
                    data-inspection-tablet
                    title="Force bay layout - for handing a tablet to the technician"
                >Bay layout</a>
            </div>

            @can(App\Ark\Runtime\Authorization\ArkCapability::RepairOrdersManage->value)
                @unless ($isTerminal ?? false)
                    <div class="card card-sm">
                        @include('operations.repair-orders.partials.inspection-portal-link', [
                            'repairOrder' => $repairOrder,
                            'isTerminal' => $isTerminal ?? false,
                        ])
                    </div>
                @endunless
            @endcan

            <div
                class="card card-sm"
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
                <div class="card-body">
                    <div class="subheader mb-2">Send walk link</div>

                    @if (count($recipients) === 0)
                        <p class="small text-secondary mb-0">No active staff to send to.</p>
                    @else
                        <div>
                            <label class="visually-hidden" for="inspection-walk-recipient-{{ $repairOrder->repair_order_id }}">Send to</label>
                            <select
                                id="inspection-walk-recipient-{{ $repairOrder->repair_order_id }}"
                                class="form-select"
                                style="max-width: 24rem"
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

                            <div class="btn-list mt-2">
                                <button
                                    type="button"
                                    class="btn"
                                    x-bind:disabled="! canSms || sending !== null"
                                    x-bind:title="smsTitle"
                                    x-on:click="send('sms')"
                                    data-inspection-handoff-sms
                                >
                                    <span x-text="sending === 'sms' ? 'Sending…' : 'SMS'"></span>
                                </button>

                                <button
                                    type="button"
                                    class="btn"
                                    x-bind:disabled="! canEmail || sending !== null"
                                    x-bind:title="emailTitle"
                                    x-on:click="send('email')"
                                    data-inspection-handoff-email
                                >
                                    <span x-text="sending === 'email' ? 'Sending…' : 'Email'"></span>
                                </button>
                            </div>

                            <div class="small fw-bold text-success mt-2" x-show="status" x-text="status" x-cloak></div>
                            <div class="small fw-bold text-danger mt-2" x-show="error" x-text="error" x-cloak></div>
                        </div>
                    @endif
                </div>
            </div>

            <div
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
                    class="btn btn-link btn-sm px-0"
                    x-on:click="copyLink()"
                    x-text="copied ? 'Link copied' : 'Copy link'"
                ></button>
            </div>

            @if ($canReset)
                <details class="card card-sm" data-inspection-reset>
                    <summary class="card-body small fw-bold text-danger">Reset inspection walk</summary>
                    <div class="card-body border-top">
                        <p class="small text-secondary">
                            Clears checklist conditions, measurements, and walk photos so the shop can redo DVI.
                            Other Findings stay. Admin and Advisor only.
                        </p>
                        <form
                            method="post"
                            action="{{ $actions['reset_url'] }}"
                            onsubmit="return confirm('Reset this inspection walk? Conditions, measurements, and walk photos will be cleared.');"
                        >
                            @csrf
                            <label class="form-check">
                                <input type="checkbox" name="confirm" value="1" required class="form-check-input">
                                <span class="form-check-label">I understand this clears the walk and cannot be undone.</span>
                            </label>
                            <button
                                type="submit"
                                class="btn btn-sm btn-outline-danger"
                            >Reset walk</button>
                        </form>
                    </div>
                </details>
            @endif

            @if (count($attentionItems) > 0)
                <div class="card card-sm" data-inspection-findings>
                    <div class="card-header">
                        <h3 class="card-title">Findings &amp; evidence</h3>
                    </div>
                    <div class="list-group list-group-flush">
                        @foreach ($attentionItems as $item)
                            <a href="{{ $item['walk_url'] }}" target="_blank" rel="noopener noreferrer" class="list-group-item list-group-item-action">
                                    <div class="fw-bold">{{ $item['label'] }}</div>
                                    <div class="small text-secondary">
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
                                    </div>
                                    @if (filled($item['note']))
                                        <div class="small text-secondary">{{ \Illuminate\Support\Str::limit($item['note'], 120) }}</div>
                                    @endif
                                </a>
                        @endforeach
                    </div>
                </div>
            @else
                <p class="text-secondary mb-0">
                    @if (($coverage['checked'] ?? 0) === 0)
                        No inspection points checked yet. Open Inspection to start the walk.
                    @else
                        No monitor / needs-attention / failed items yet. Open Inspection to continue.
                    @endif
                </p>
            @endif

            @unless ($canRecord)
                <div class="alert alert-warning mb-0">
                    Recording is limited to the assigned technician or an advisor.
                </div>
            @endunless
        </section>
    @endif
</div>
