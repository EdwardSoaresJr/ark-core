@php
    /** @var array<string, mixed>|null $context */
    /** @var array<string, mixed>|null $selected */
    /** @var string $section */
    $sections = is_array($context['sections'] ?? null) ? $context['sections'] : null;
@endphp

<div class="ops-comms-workspace__panel ops-comms-workspace__panel--context">
    <div class="ops-comms-workspace__panel-header">
        <h3 class="ops-comms-workspace__panel-title">Shop Context</h3>
    </div>

    @if ($context === null)
        <div class="ops-comms-workspace__empty-state">
            <p class="ops-comms-workspace__empty">Shop context appears here when an item is selected.</p>
        </div>
    @else
        <div class="ops-comms-workspace__context-body">
        @if (filled($context['headline'] ?? null))
            <p class="ops-comms-workspace__context-headline">{{ $context['headline'] }}</p>
        @endif

        <p class="ops-comms-workspace__context-status">{{ $context['link_status'] ?? '' }}</p>

        @if ($sections !== null)
            @foreach ([
                'customer' => 'Customer',
                'repair' => 'Current repair',
                'next_move' => 'Next move',
                'money' => 'Money',
                'history' => 'History',
            ] as $sectionKey => $sectionLabel)
                @php $sectionFields = is_array($sections[$sectionKey] ?? null) ? array_filter($sections[$sectionKey]) : []; @endphp
                @if ($sectionFields !== [])
                    <div class="ops-comms-workspace__context-section">
                        <p class="ops-comms-workspace__context-section-title">{{ $sectionLabel }}</p>
                        <dl class="ops-comms-workspace__context-fields">
                            @foreach ($sectionFields as $label => $value)
                                <div class="ops-comms-workspace__context-row">
                                    <dt>{{ $label }}</dt>
                                    <dd>{{ $value }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>
                @endif
            @endforeach
        @endif

        @if (filled($context['primary_ro'] ?? null))
            @php $ro = $context['primary_ro']; @endphp
            <div class="ops-comms-workspace__context-ro">
                <div class="ops-comms-workspace__context-ro-head">
                    <div class="min-w-0">
                        <p class="ops-comms-workspace__context-ro-title">
                            @if (filled($ro['url'] ?? null))
                                <a href="{{ $ro['url'] }}" class="ops-page-link">{{ $ro['number'] ?? 'Repair order' }}</a>
                            @else
                                {{ $ro['number'] ?? 'Repair order' }}
                            @endif
                        </p>
                        @if (filled($ro['vehicle'] ?? null))
                            <p class="ops-comms-workspace__context-ro-vehicle">{{ $ro['vehicle'] }}</p>
                        @endif
                    </div>
                    @if (filled($ro['repair_order_id'] ?? null) && filled($ro['status'] ?? null))
                        <x-operations.lifecycle-status-menu
                            class="shrink-0"
                            :repair-order-id="$ro['repair_order_id']"
                            :label="$ro['status']"
                            :tone="$ro['status_tone'] ?? 'neutral'"
                            :status-moves="$ro['status_moves'] ?? []"
                            :confirm-base-url="$ro['url'] ?? null"
                        />
                    @elseif (filled($ro['status'] ?? null))
                        <p class="ops-comms-workspace__context-ro-status">{{ $ro['status'] }}</p>
                    @endif
                </div>
                @if (filled($ro['signal'] ?? null))
                    <p class="ops-comms-workspace__context-ro-signal">{{ $ro['signal'] }}</p>
                @endif
            </div>
        @endif

        @if (filled($context['attention']['reasons'] ?? null))
            @php $attention = $context['attention']; @endphp
            <div class="ops-comms-workspace__context-attention">
                <p class="ops-comms-workspace__context-section-title">Why this needs you</p>
                <ul class="ops-comms-workspace__context-attention-reasons">
                    @foreach ($attention['reasons'] as $reason)
                        <li>{{ $reason }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (filled($context['nudge'] ?? null))
            @include('operations.communications.workspace.partials.nudge-panel', [
                'nudge' => $context['nudge'],
                'section' => $section,
            ])
        @endif

        @if (filled($context['analysis_insight'] ?? null))
            @include('operations.communications.workspace.partials.analysis-insight-panel', [
                'insight' => $context['analysis_insight'],
                'entityKey' => $context['analysis_insight']['entity_key'] ?? ($selected['key'] ?? ''),
                'composerTarget' => ($selected['kind'] ?? '') === 'call' ? 'call-note' : 'sms',
            ])
        @endif

        @if ($sections === null && filled($context['fields'] ?? null))
            <dl class="ops-comms-workspace__context-fields">
                @foreach ($context['fields'] as $label => $value)
                    <div class="ops-comms-workspace__context-row">
                        <dt>{{ $label }}</dt>
                        <dd>{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        @endif

        @if (filled($context['actions'] ?? null))
            <div class="ops-comms-workspace__context-actions">
                <p class="ops-comms-workspace__context-actions-title">Actions</p>
                <ul class="ops-comms-workspace__context-action-list">
                    @foreach ($context['actions'] as $action)
                        <li>
                            @if (($action['type'] ?? '') === 'link')
                                <a
                                    href="{{ $action['url'] }}"
                                    @if (filled($action['target'] ?? null)) target="{{ $action['target'] }}" @endif
                                    class="ops-comms-workspace__action-link"
                                >{{ $action['label'] }}</a>
                            @elseif (($action['type'] ?? '') === 'form')
                                <form
                                    method="POST"
                                    action="{{ $action['url'] }}"
                                    class="ops-comms-workspace__inline-form"
                                    @if (filled($action['confirm'] ?? null))
                                        onsubmit="return confirm(@js($action['confirm']));"
                                    @endif
                                >
                                    @csrf
                                    <input type="hidden" name="section" value="{{ $section }}">
                                    @foreach ($action['fields'] ?? [] as $name => $value)
                                        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                                    @endforeach
                                    <button type="submit" class="ops-comms-workspace__action-button">{{ $action['label'] }}</button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (filled($context['assignable_advisors'] ?? null) && ($selected['kind'] ?? '') === 'conversation')
            <form
                method="POST"
                action="{{ route('operations.communications.conversations.assign', ['conversation' => $context['conversation_id']]) }}"
                class="ops-comms-workspace__assign-form"
            >
                @csrf
                <input type="hidden" name="section" value="{{ $section }}">
                <input type="hidden" name="assign_to" value="user">
                <label class="ops-comms-workspace__assign-label" for="comms-assign-advisor">Assign advisor</label>
                <div class="ops-comms-workspace__assign-row">
                    <select id="comms-assign-advisor" name="user_id" class="ops-comms-workspace__assign-select" required>
                        <option value="" disabled selected>Select advisor</option>
                        @foreach ($context['assignable_advisors'] as $advisor)
                            <option value="{{ $advisor['id'] }}">{{ $advisor['name'] }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="ops-comms-workspace__action-button">Assign</button>
                </div>
            </form>
        @endif
        </div>
    @endif
</div>
