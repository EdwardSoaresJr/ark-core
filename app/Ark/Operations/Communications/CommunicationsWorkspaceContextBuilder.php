<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationStatus;
use App\Ark\Operations\Conversations\CustomerCallContextOpenRepairOrder;
use App\Ark\Operations\Conversations\CustomerCallContextResolver;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Intake\IntakeEntryQuery;
use App\Ark\Operations\Leads\ConversationLeadResolver;
use App\Ark\Operations\Leads\IngressCreateContactUrl;
use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\Leads\LeadConfirmationAuditConversation;
use App\Ark\Operations\Leads\LeadState;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderLifecycleSelectProjection;
use App\Ark\Operations\Telephony\CallRecordingPlayback;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\InboundCallerDisplayPhone;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Operational context for the Communications workspace right pane.
 *
 * Read-only projection from Customer, Vehicle, RepairOrder, Lead, and CallSession truth.
 */
final class CommunicationsWorkspaceContextBuilder
{
    public function __construct(
        private readonly CustomerCallContextResolver $callContextResolver,
        private readonly InboundCallerDisplayPhone $callerDisplayPhone,
        private readonly CallRecordingPlayback $recordingPlayback,
        private readonly ConversationLeadResolver $conversationLeads,
        private readonly LeadConfirmationAuditConversation $confirmationAudit,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function forConversation(Conversation $conversation): ?array
    {
        $conversation->loadMissing(['owner:id,name']);

        $lead = $this->conversationLeads->forTurn($conversation)?->loadMissing(['customer', 'repairOrder.vehicle']);

        $phone = $conversation->contact_surface === ConversationContactSurface::Phone
            ? trim((string) $conversation->contact_address)
            : null;

        $callContext = $phone !== '' && $phone !== null
            ? $this->callContextResolver->resolve($phone)
            : null;

        $customer = $callContext?->customer ?? $lead?->customer;
        $primaryRo = $callContext?->openRepairOrders->first()?->repairOrder ?? $lead?->repairOrder;
        $primaryRo?->loadMissing('vehicle');

        $displayHeadline = match (true) {
            filled($lead?->contact_name) => (string) $lead->contact_name,
            $customer !== null => (string) $customer->name,
            $phone !== null => PhoneNumber::display($phone) ?? $phone,
            default => 'Unknown contact',
        };

        $fields = array_filter([
            'Phone' => $phone !== null
                ? (PhoneNumber::display($phone) ?? $phone)
                : Str::limit((string) $conversation->contact_address, 40),
            'Email' => filled($customer?->email)
                ? (string) $customer->email
                : (filled($lead?->contact_email) ? (string) $lead->contact_email : 'No email'),
            'Location' => filled($customer?->city)
                ? trim(implode(', ', array_filter([(string) $customer->city, (string) ($customer->state ?? '')])))
                : null,
            'Address' => $customer?->display_address,
            'Posture' => $conversation->waiting_on?->label(),
            'Status' => $conversation->status->label(),
            'Assigned' => $conversation->owner?->name ?? 'Unassigned',
        ]);

        if ($conversation->contact_surface !== ConversationContactSurface::Phone) {
            $fields = ['Channel' => $conversation->contact_surface->label()] + $fields;
        }

        if ($lead !== null) {
            $fields['Source'] = $lead->source->label();

            if ($lead->conversation_id !== null && $lead->conversation_id !== $conversation->id) {
                $fields['Active thread'] = filled($lead->contact_phone)
                    ? (PhoneNumber::display((string) $lead->contact_phone) ?? (string) $lead->contact_phone)
                    : 'Primary conversation';
            }
        }

        if ($customer !== null && strcasecmp($displayHeadline, (string) $customer->name) !== 0) {
            $fields['Customer'] = $customer->name;
        }

        $estimateViews = 0;

        if ($primaryRo !== null) {
            $estimateViews = $primaryRo->communicationEvents
                ->where('event_type', OperationalCommunicationType::EstimateViewed)
                ->count();
        }

        $linkStatus = match (true) {
            $customer !== null => 'Customer linked',
            $lead !== null && $lead->conversation_id !== null && $lead->conversation_id !== $conversation->id && $lead->first_contacted_at !== null => 'Handled on text thread',
            $lead !== null => 'Lead linked',
            default => 'No customer linked yet',
        };

        if ($this->confirmationAudit->isAuditOnly($conversation)) {
            $linkStatus = $lead !== null && $lead->first_contacted_at !== null
                ? 'Confirmation only · handled elsewhere'
                : 'Confirmation only';
        }

        return [
            'headline' => $customer !== null ? (string) $customer->name : ($displayHeadline === (PhoneNumber::display($phone) ?? $phone) ? 'Unknown contact' : $displayHeadline),
            'link_status' => $linkStatus,
            'sections' => [
                'customer' => array_filter([
                    'Phone' => $fields['Phone'] ?? null,
                    'Email' => $fields['Email'] ?? null,
                    'Location' => $fields['Location'] ?? null,
                    'Address' => $fields['Address'] ?? null,
                    'Status' => $linkStatus,
                ]),
                'repair' => $primaryRo !== null ? array_filter([
                    'RO' => '#'.$primaryRo->repair_order_id,
                    'Vehicle' => $primaryRo->vehicle !== null
                        ? trim("{$primaryRo->vehicle->year} {$primaryRo->vehicle->make} {$primaryRo->vehicle->model}")
                        : null,
                    'Lifecycle' => $primaryRo->status->label(),
                    'Why waiting' => filled($primaryRo->concern_summary) ? (string) $primaryRo->concern_summary : null,
                    'Estimate' => $estimateViews > 0 ? 'Viewed '.$estimateViews.'×' : null,
                ]) : null,
                'next_move' => array_filter([
                    'Advisor' => $conversation->owner?->name ?? 'Unassigned',
                    'Turn' => $conversation->waiting_on?->label(),
                    'Source' => $lead !== null ? $lead->source->opportunityLabel() : null,
                ]),
            ],
            'fields' => $fields,
            'primary_ro' => $primaryRo !== null ? $this->repairOrderSummary($primaryRo, $estimateViews) : null,
            'actions' => $this->conversationActions($conversation, $phone, $customer, $primaryRo, $lead),
            'assignable_advisors' => $this->assignableAdvisors(),
            'conversation_id' => $conversation->id,
            'lead_id' => $lead?->id,
            'origin_label' => $lead !== null ? $lead->source->opportunityLabel() : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function forLead(Lead $lead): ?array
    {
        return [
            'headline' => filled($lead->contact_name) ? (string) $lead->contact_name : 'Lead',
            'link_status' => filled($lead->customer_id) ? 'Customer linked' : 'No customer linked yet',
            'fields' => array_filter([
                'Source' => $lead->source->opportunityLabel(),
                'Phone' => PhoneNumber::display((string) $lead->contact_phone) ?? $lead->contact_phone,
                'Email' => filled($lead->contact_email) ? (string) $lead->contact_email : 'No email',
                'State' => $lead->state->label(),
                'Concern' => Str::limit((string) $lead->concern, 120),
            ]),
            'primary_ro' => null,
            'actions' => array_values(array_filter([
                $lead->conversation_id !== null
                    ? ['type' => 'link', 'label' => 'Open conversation', 'url' => CommunicationsNeedsYou::url(['conversation' => $lead->conversation_id])]
                    : null,
                $lead->isOpen()
                    ? ['type' => 'link', 'label' => 'Check In', 'url' => route('operations.leads.intake', $lead)]
                    : null,
                IngressCreateContactUrl::forLead($lead) !== null
                    ? ['type' => 'link', 'label' => 'Create contact', 'url' => IngressCreateContactUrl::forLead($lead)]
                    : null,
                $lead->isOpen() && $lead->state !== LeadState::Contacted
                    ? [
                        'type' => 'form',
                        'label' => 'Mark contacted',
                        'method' => 'POST',
                        'url' => route('operations.leads.state', $lead),
                        'fields' => ['_method' => 'PATCH', 'state' => LeadState::Contacted->value],
                    ]
                    : null,
                $lead->isOpen()
                    ? [
                        'type' => 'form',
                        'label' => 'Lost',
                        'method' => 'POST',
                        'url' => route('operations.leads.state', $lead),
                        'fields' => [
                            '_method' => 'PATCH',
                            'state' => LeadState::Lost->value,
                            'lost_reason' => 'Closed without RO',
                        ],
                        'confirm' => 'Mark this lead as lost?',
                    ]
                    : null,
                $lead->isOpen()
                    ? [
                        'type' => 'form',
                        'label' => 'Spam',
                        'method' => 'POST',
                        'url' => route('operations.leads.state', $lead),
                        'fields' => ['_method' => 'PATCH', 'state' => LeadState::Spam->value],
                    ]
                    : null,
            ])),
            'assignable_advisors' => [],
            'lead_id' => $lead->id,
            'origin_label' => $lead->source->opportunityLabel(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function forCallSession(CallSession $session): ?array
    {
        $phone = $this->callerDisplayPhone->normalizedForSession($session) ?? '';
        $displayPhone = $this->callerDisplayPhone->forSession($session);
        $callContext = $phone !== '' ? $this->callContextResolver->resolve($phone) : null;
        $customer = $callContext?->customer ?? $session->customer;
        $primaryRo = $session->repairOrder ?? $callContext?->openRepairOrders->first()?->repairOrder;
        $phoneFieldLabel = $session->direction === CallSessionDirection::Outbound ? 'To' : 'From';
        $playback = $this->recordingPlayback->projectFor($session);

        $recordingActions = [];

        if ($playback['show_play_recording_action'] && filled($playback['recording_url'])) {
            $recordingActions[] = [
                'type' => 'link',
                'label' => 'Play recording',
                'url' => $playback['recording_url'],
                'target' => '_blank',
            ];
        }

        if ($playback['show_play_voicemail_action'] && filled($playback['voicemail_url'])) {
            $recordingActions[] = [
                'type' => 'link',
                'label' => 'Play voicemail',
                'url' => $playback['voicemail_url'],
                'target' => '_blank',
            ];
        }

        return [
            'headline' => $customer?->name ?? ($displayPhone !== '' ? $displayPhone : 'Unknown caller'),
            'link_status' => $customer !== null ? 'Customer linked' : 'Unknown caller',
            'fields' => array_filter([
                'Status' => $session->status->operationalLabel(),
                $phoneFieldLabel => $displayPhone !== '' ? $displayPhone : null,
                'Direction' => $session->direction->queueLabel(),
                'Owned by' => $session->owner?->name ?? 'Unassigned',
                'Started' => $session->started_at
                    ?->timezone(config('app.display_timezone'))
                    ->format('M j, Y g:i A'),
            ]),
            'primary_ro' => $primaryRo !== null ? $this->repairOrderSummary($primaryRo) : null,
            'actions' => array_values(array_filter(array_merge(
                $recordingActions,
                [
                    ['type' => 'link', 'label' => 'Find customer', 'url' => route('operations.customers.search', ['q' => $phone])],
                    ['type' => 'link', 'label' => 'Caller lookup', 'url' => route('operations.caller-lookup', ['phone' => $phone])],
                    $customer === null && $phone !== ''
                        ? ['type' => 'link', 'label' => 'Create contact', 'url' => IngressCreateContactUrl::forPhone($phone, callSessionId: $session->id)]
                        : null,
                    ['type' => 'link', 'label' => 'Start intake', 'url' => route('operations.intake.create', IntakeEntryQuery::fromInboundPhoneMessage($phone, ''))],
                ],
            ))),
            'assignable_advisors' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function repairOrderSummary(RepairOrder $repairOrder, int $estimateViews = 0): array
    {
        $vehicle = $repairOrder->vehicle;
        $url = route('operations.repair-orders.show', $repairOrder);
        $statusMoves = $repairOrder->isTerminal()
            ? []
            : RepairOrderLifecycleSelectProjection::forCatalogTargets($repairOrder, auth()->user())->boardMoves();

        return array_filter([
            'number' => '#'.$repairOrder->repair_order_id,
            'vehicle' => $vehicle !== null
                ? trim("{$vehicle->year} {$vehicle->make} {$vehicle->model}")
                : null,
            'status' => $repairOrder->statusDisplayLabel(),
            'status_tone' => RepairOrderLifecycleSelectProjection::statusTone($repairOrder),
            'signal' => $estimateViews > 0 ? 'Estimate viewed '.$estimateViews.'×' : null,
            'url' => $url,
            'repair_order_id' => $repairOrder->id,
            'status_moves' => $statusMoves === [] ? null : $statusMoves,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function conversationActions(
        Conversation $conversation,
        ?string $phone,
        ?Customer $customer,
        ?RepairOrder $primaryRo,
        ?Lead $lead = null,
    ): array {
        $actions = [];

        if ($lead !== null && $lead->conversation_id !== null && $lead->conversation_id !== $conversation->id) {
            $actions[] = [
                'type' => 'link',
                'label' => 'Open active thread',
                'url' => CommunicationsNeedsYou::url(['conversation' => $lead->conversation_id]),
            ];
        }

        if ($lead !== null && $lead->isOpen() && $primaryRo === null) {
            $actions[] = ['type' => 'link', 'label' => 'Check In', 'url' => route('operations.leads.intake', $lead)];
        }

        if ($lead !== null && $lead->isOpen()) {
            if ($lead->state !== LeadState::Contacted) {
                $actions[] = [
                    'type' => 'form',
                    'label' => 'Mark contacted',
                    'method' => 'POST',
                    'url' => route('operations.leads.state', $lead),
                    'fields' => ['_method' => 'PATCH', 'state' => LeadState::Contacted->value],
                ];
            }

            $actions[] = [
                'type' => 'form',
                'label' => 'Lost',
                'method' => 'POST',
                'url' => route('operations.leads.state', $lead),
                'fields' => [
                    '_method' => 'PATCH',
                    'state' => LeadState::Lost->value,
                    'lost_reason' => 'Closed without RO',
                ],
                'confirm' => 'Mark this lead as lost?',
            ];

            $actions[] = [
                'type' => 'form',
                'label' => 'Spam',
                'method' => 'POST',
                'url' => route('operations.leads.state', $lead),
                'fields' => ['_method' => 'PATCH', 'state' => LeadState::Spam->value],
            ];
        }

        if ($customer !== null) {
            $actions[] = ['type' => 'link', 'label' => 'Open customer', 'url' => route('operations.customers.show', $customer)];
        } else {
            $actions[] = ['type' => 'link', 'label' => 'Find customer', 'url' => route('operations.customers.search', ['q' => $phone ?? ''])];
            if ($phone !== null && $phone !== '') {
                $createContactUrl = IngressCreateContactUrl::forPhone($phone, conversationId: $conversation->id);
                if ($createContactUrl !== null) {
                    $actions[] = ['type' => 'link', 'label' => 'Create contact', 'url' => $createContactUrl];
                }
                if ($lead === null) {
                    $actions[] = ['type' => 'link', 'label' => 'Check In', 'url' => route('operations.intake.create', IntakeEntryQuery::fromInboundPhoneMessage($phone, ''))];
                }
            }
        }

        if ($primaryRo !== null) {
            $actions[] = ['type' => 'link', 'label' => 'Open RO', 'url' => route('operations.repair-orders.show', $primaryRo)];
        }

        $actions[] = ['type' => 'form', 'label' => 'Assign to me', 'method' => 'POST', 'url' => route('operations.communications.conversations.assign', $conversation), 'fields' => ['assign_to' => 'me']];

        if ($conversation->owned_by_user_id !== null) {
            $actions[] = ['type' => 'form', 'label' => 'Unassign', 'method' => 'POST', 'url' => route('operations.communications.conversations.assign', $conversation), 'fields' => ['assign_to' => 'unassign']];
        }

        if ($conversation->status === ConversationStatus::Resolved) {
            $actions[] = ['type' => 'form', 'label' => 'Reopen', 'method' => 'POST', 'url' => route('operations.communications.conversations.reopen', $conversation)];
        } else {
            // Same full clear as the thread-header button — resolve, catch up
            // read state, clear related calls, land back on the shrunken list.
            $actions[] = ['type' => 'form', 'label' => 'Mark handled', 'method' => 'POST', 'url' => route('operations.communications.conversations.mark-handled', $conversation)];
        }

        return $actions;
    }

    /**
     * Customer + RO context for the workspace composer (Send Estimate, pay link, etc.).
     *
     * @return array{
     *     customer: ?Customer,
     *     repair_order: ?RepairOrder,
     *     open_repair_orders: Collection<int, CustomerCallContextOpenRepairOrder>,
     *     lead: ?Lead,
     *     display_name: ?string,
     * }
     */
    public function conversationComposerContext(Conversation $conversation): array
    {
        $phone = $conversation->contact_surface === ConversationContactSurface::Phone
            ? trim((string) $conversation->contact_address)
            : null;

        $callContext = $phone !== '' && $phone !== null
            ? $this->callContextResolver->resolve($phone)
            : null;

        $lead = $this->conversationLeads->forTurn($conversation)?->loadMissing(['customer', 'repairOrder.vehicle']);

        $customer = $callContext?->customer ?? $lead?->customer;
        $repairOrder = $callContext?->openRepairOrders->first()?->repairOrder ?? $lead?->repairOrder;

        $displayName = match (true) {
            filled($lead?->contact_name) => (string) $lead->contact_name,
            $customer !== null => (string) $customer->name,
            default => null,
        };

        return [
            'customer' => $customer,
            'repair_order' => $repairOrder,
            'open_repair_orders' => $callContext?->openRepairOrders ?? collect(),
            'lead' => $lead,
            'display_name' => $displayName,
            'conversation' => $conversation,
        ];
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function assignableAdvisors(): array
    {
        return User::query()
            ->active()
            ->role([ArkRole::Admin->value, ArkRole::Advisor->value])
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->name])
            ->all();
    }
}
