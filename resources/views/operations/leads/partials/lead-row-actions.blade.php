@php
    /** @var \App\Ark\Operations\Leads\Lead $lead */
    $hasThread = (bool) ($hasThread ?? false);
@endphp

<div class="ops-lead-row-actions">
    <a href="{{ route('operations.leads.intake', $lead) }}" class="ops-page-link ops-page-link--primary text-xs">Check In</a>

    <div
        class="ops-comms-menu ops-lead-row-actions__menu"
        x-data="arkFloatingCommsMenu({ align: 'right', minWidth: 176 })"
        x-ref="menuRoot"
    >
        <button
            type="button"
            x-ref="menuTrigger"
            class="ops-page-link text-xs ops-lead-row-actions__more"
            @click.stop="toggleMenu()"
            :aria-expanded="menuOpen"
            aria-haspopup="menu"
        >
            More
            <span class="ops-comms-menu__caret" aria-hidden="true">▾</span>
        </button>

        <template x-teleport="body">
            <div
                x-show="menuOpen"
                x-cloak
                x-ref="menuPanel"
                :style="menuStyle"
                class="ops-comms-menu__panel ops-comms-menu__panel--floating ops-lead-row-actions__panel"
                role="menu"
                @click.stop
            >
                @if ($hasThread)
                    <button
                        type="button"
                        role="menuitem"
                        class="ops-comms-menu__item"
                        @click="closeMenu(); openLeadId = {{ $lead->id }}"
                    >
                        Timeline
                    </button>
                @endif

                @if ($createContactUrl = \App\Ark\Operations\Leads\IngressCreateContactUrl::forLead($lead))
                    <a href="{{ $createContactUrl }}" role="menuitem" class="ops-comms-menu__item">Create contact</a>
                @endif

                @if ($lead->state !== \App\Ark\Operations\Leads\LeadState::Contacted)
                    <form method="POST" action="{{ route('operations.leads.state', $lead) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="state" value="contacted">
                        <button type="submit" role="menuitem" class="ops-comms-menu__item">Mark contacted</button>
                    </form>
                @endif

                <form method="POST" action="{{ route('operations.leads.state', $lead) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="state" value="waiting_customer">
                    <button type="submit" role="menuitem" class="ops-comms-menu__item">Waiting on customer</button>
                </form>

                <form method="POST" action="{{ route('operations.leads.state', $lead) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="state" value="scheduled">
                    <button type="submit" role="menuitem" class="ops-comms-menu__item">Scheduled</button>
                </form>

                <form method="POST" action="{{ route('operations.leads.state', $lead) }}" onsubmit="return confirm('Mark this lead as lost?');">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="state" value="lost">
                    <input type="hidden" name="lost_reason" value="Closed without RO">
                    <button type="submit" role="menuitem" class="ops-comms-menu__item ops-comms-menu__item--danger">Mark lost</button>
                </form>

                <form method="POST" action="{{ route('operations.leads.state', $lead) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="state" value="spam">
                    <button type="submit" role="menuitem" class="ops-comms-menu__item ops-comms-menu__item--muted">Mark spam</button>
                </form>
            </div>
        </template>
    </div>
</div>
