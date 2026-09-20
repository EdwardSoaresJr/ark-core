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
use App\Ark\Operations\Vehicles\Vehicle;
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
        private readonly CommunicationsRelationshipContextResolver $relationshipContext,
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
        $relationship = $this->relationshipContext->forConversation($conversation, $lead);
        $visit = $relationship['current_visit'];
        $primaryRo = $visit['repair_order'] instanceof RepairOrder ? $visit['repair_order'] : null;
        $customer = filled($relationship['customer']['id'] ?? null)
            ? Customer::query()->find((int) $relationship['customer']['id'])
            : null;
        $phone = $relationship['thread']['phone'] ?? null;
        $displayHeadline = filled($relationship['customer']['name'] ?? null)
            ? (string) $relationship['customer']['name']
            : ($phone ?? 'Unmatched');

        $fields = array_filter([
            'Phone' => $phone ?? Str::limit((string) $conversation->contact_address, 40),
            'Email' => filled($relationship['customer']['email'] ?? null)
                ? (string) $relationship['customer']['email']
                : 'No email',
            'Location' => filled($customer?->city)
                ? trim(implode(', ', array_filter([(string) $customer->city, (string) ($customer->state ?? '')])))
                : null,
            'Address' => $customer?->display_address,
            'Turn' => (string) ($relationship['turn']['label'] ?? ''),
            'Status' => $conversation->status->label(),
            'Assigned' => $conversation->owner?->name ?? 'Unassigned',
        ]);

        if ($conversation->contact_surface !== ConversationContactSurface::Phone) {
            $fields = ['Channel' => $conversation->contact_surface->label()] + $fields;
        }

        if ($lead !== null) {
            $fields['Source'] = $lead->source->label();
        }

        $estimateViews = 0;
        if ($primaryRo !== null) {
            $primaryRo->loadMissing('communicationEvents');
            $estimateViews = $primaryRo->communicationEvents
                ->where('event_type', OperationalCommunicationType::EstimateViewed)
                ->count();
        }

        $linkStatus = (string) ($relationship['customer']['status'] ?? 'Unmatched');

        return [
            'headline' => $displayHeadline,
            'link_status' => $linkStatus,
            'thread' => $relationship['thread'],
            'customer' => $relationship['customer'],
            'current_visit' => $visit,
            'turn' => $relationship['turn'],
            'sections' => [
                'who' => array_filter([
                    'Name' => $displayHeadline,
                    'Phone' => $fields['Phone'] ?? null,
                    'Email' => $fields['Email'] ?? null,
                    'Match' => $linkStatus,
                ]),
                'current_visit' => $this->visitSection($visit),
                'turn' => [
                    'Turn' => (string) ($relationship['turn']['label'] ?? ''),
                    'Advisor' => $conversation->owner?->name ?? 'Unassigned',
                ],
            ],
            'fields' => $fields,
            'primary_ro' => $primaryRo !== null ? $this->repairOrderSummary($primaryRo, $estimateViews) : null,
            'actions' => $this->conversationActions($conversation, $relationship['thread']['normalized_phone'] ?? $phone, $customer, $primaryRo, $lead),
            'assignable_advisors' => $this->assignableAdvisors(),
            'conversation_id' => $conversation->id,
            'lead_id' => $lead?->id,
            'origin_label' => $lead !== null ? $lead->source->opportunityLabel() : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $visit
     * @return array<string, mixed>
     */
    private function visitSection(array $visit): array
    {
        $fields = [
            'Visit' => (string) ($visit['label'] ?? 'No current visit'),
        ];

        if (filled($visit['ro_label'] ?? null)) {
            $fields['RO'] = (string) $visit['ro_label'];
            $fields['Vehicle'] = $visit['vehicle_label'] ?? null;
            $fields['Lifecycle'] = $visit['lifecycle_label'] ?? null;
            $fields['Source'] = (string) ($visit['source_label'] ?? '');
        } elseif (($visit['source'] ?? '') === CommunicationsVisitSource::Multiple->value) {
            $labels = collect($visit['open_visits'] ?? [])
                ->map(fn (array $row): string => trim(($row['ro_label'] ?? '').' '.($row['vehicle_label'] ?? '')))
                ->filter()
                ->implode(', ');
            $fields['Open'] = $labels !== '' ? $labels : 'More than one open repair order';
        }

        if (filled($visit['linked_visit']['ro_label'] ?? null)) {
            $fields['Linked'] = (string) $visit['linked_visit']['ro_label'];
        }

        return array_filter($fields);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function forLead(Lead $lead): ?array
    {
        return [
            'headline' => filled($lead->contact_name) ? (string) $lead->contact_name : 'Lead',
            'link_status' => filled($lead->customer_id) ? 'Customer' : 'Lead',
            'sections' => [
                'who' => array_filter([
                    'Name' => filled($lead->contact_name) ? (string) $lead->contact_name : 'Unmatched',
                    'Phone' => PhoneNumber::display((string) $lead->contact_phone) ?? $lead->contact_phone,
                    'Email' => filled($lead->contact_email) ? (string) $lead->contact_email : 'No email',
                    'Match' => filled($lead->customer_id) ? 'Customer' : 'Lead',
                ]),
                'current_visit' => [
                    'Visit' => 'No current visit',
                ],
                'turn' => [
                    'Turn' => $lead->state->label(),
                ],
            ],
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
        $session->loadMissing(['customer', 'owner:id,name', 'repairOrder.vehicle']);
        $primaryRo = $session->repairOrder;
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
            'headline' => $customer?->name ?? ($displayPhone !== '' ? $displayPhone : 'Unmatched'),
            'link_status' => $customer !== null ? 'Customer' : 'Unmatched',
            'sections' => [
                'who' => array_filter([
                    'Name' => $customer?->name ?? ($displayPhone !== '' ? $displayPhone : 'Unmatched'),
                    $phoneFieldLabel => $displayPhone !== '' ? $displayPhone : 'No phone',
                    'Match' => $customer !== null ? 'Customer' : 'Unmatched',
                ]),
                'current_visit' => $primaryRo !== null
                    ? array_filter([
                        'Visit' => 'Current visit',
                        'RO' => '#'.$primaryRo->repair_order_id,
                        'Vehicle' => $primaryRo->vehicle !== null
                            ? trim("{$primaryRo->vehicle->year} {$primaryRo->vehicle->make} {$primaryRo->vehicle->model}")
                            : null,
                    ])
                    : ['Visit' => 'No current visit'],
                'turn' => [
                    'Turn' => $session->worked_at !== null ? 'Waiting on customer' : 'Needs shop',
                ],
            ],
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
            'turn' => [
                'waiting_on' => $session->worked_at !== null ? 'customer' : 'shop',
                'label' => $session->worked_at !== null ? 'Waiting on customer' : 'Needs shop',
                'is_shop_turn' => $session->worked_at === null,
            ],
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
            $actions[] = ['type' => 'form', 'label' => 'Follow-up', 'method' => 'POST', 'url' => route('operations.communications.conversations.follow-up', $conversation)];
            $actions[] = ['type' => 'form', 'label' => 'Resolve', 'method' => 'POST', 'url' => route('operations.conversations.resolve', $conversation)];
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
        $lead = $this->conversationLeads->forTurn($conversation)?->loadMissing(['customer', 'repairOrder.vehicle']);
        $relationship = $this->relationshipContext->forConversation($conversation, $lead);
        $visit = $relationship['current_visit'];
        $repairOrder = $visit['repair_order'] instanceof RepairOrder ? $visit['repair_order'] : null;
        $customer = filled($relationship['customer']['id'] ?? null)
            ? Customer::query()->find((int) $relationship['customer']['id'])
            : ($lead?->customer);

        $displayName = filled($relationship['customer']['name'] ?? null)
            ? (string) $relationship['customer']['name']
            : null;

        $openRepairOrders = $relationship['open_repair_orders'] ?? collect();

        return [
            'customer' => $customer,
            'repair_order' => $repairOrder,
            'open_repair_orders' => $openRepairOrders instanceof Collection
                ? $openRepairOrders->map(function ($row) {
                    if ($row instanceof CustomerCallContextOpenRepairOrder) {
                        return $row;
                    }

                    if ($row instanceof RepairOrder) {
                        $row->loadMissing('vehicle');

                        return new CustomerCallContextOpenRepairOrder(
                            repairOrder: $row,
                            vehicle: $row->vehicle ?? new Vehicle,
                            workflowPostureLabel: (string) $row->statusDisplayLabel(),
                            workflowNextAction: '',
                            orientation: null,
                        );
                    }

                    return $row;
                })
                : collect(),
            'lead' => $lead,
            'display_name' => $displayName,
            'conversation' => $conversation,
            'current_visit' => $visit,
            'turn' => $relationship['turn'],
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
