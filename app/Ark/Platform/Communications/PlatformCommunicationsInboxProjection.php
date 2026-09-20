<?php

namespace App\Ark\Platform\Communications;

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationWork;
use App\Ark\Operations\Conversations\CustomerCallContext;
use App\Ark\Operations\Conversations\CustomerCallContextResolver;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Native inbox data plane backed by Platform conversation authority.
 * Core Conversation owns whose turn it is.
 */
final class PlatformCommunicationsInboxProjection
{
    public function __construct(
        private readonly ArkCommunicationsClient $client,
        private readonly PlatformConversationCustomerResolver $customers,
        private readonly ConversationWork $work,
        private readonly CustomerCallContextResolver $callContext,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function inbox(?User $viewer, ?string $selectedPublicId = null, string $filter = 'needs', string $ownerFilter = 'everyone'): array
    {
        if ($viewer === null) {
            return $this->empty($filter);
        }

        $filter = in_array($filter, ['needs', 'waiting', 'resolved', 'all'], true) ? $filter : 'needs';

        $listed = $this->client->listConversations((int) $viewer->id, 80);
        if (! ($listed['ok'] ?? false)) {
            return $this->coreFallbackInbox(
                $filter,
                (string) ($listed['message'] ?? 'ARK Communications is unavailable.'),
            );
        }

        /** @var list<array<string, mixed>> $conversations */
        $conversations = is_array($listed['conversations'] ?? null) ? $listed['conversations'] : [];

        $phoneMap = $this->customers->mapByNormalizedPhone(array_map(
            fn (array $row): string => (string) ($row['contact_address'] ?? ''),
            $conversations,
        ));

        $phones = [];
        foreach ($conversations as $row) {
            $normalized = PhoneNumber::normalize((string) ($row['contact_address'] ?? ''));
            if ($normalized !== null) {
                $phones[] = $normalized;
            }
        }

        $coreByPhone = $this->conversationsByPhone($phones);
        $shopByPhone = $this->callContext->mapForAttentionList($phones);
        $advisors = $this->assignableAdvisors();

        $allItems = [];
        foreach ($conversations as $row) {
            $publicId = (string) ($row['public_id'] ?? '');
            if ($publicId === '') {
                continue;
            }

            $contact = (string) ($row['contact_address'] ?? '');
            $normalized = PhoneNumber::normalize($contact) ?? $contact;
            $statedCustomerId = isset($row['core_customer_id']) ? (int) $row['core_customer_id'] : null;
            $customer = $this->customers->resolve($statedCustomerId, $contact, $phoneMap);
            $displayPhone = PhoneNumber::display($contact) ?? $contact;
            $title = $customer?->name ?? $displayPhone;
            $known = $customer !== null;
            $core = $normalized !== '' ? $coreByPhone->get($normalized) : null;
            $lane = $core instanceof Conversation ? $this->work->lane($core) : 'needs';
            $laneLabel = $core instanceof Conversation ? $this->work->laneLabel($core) : 'Needs attention';
            $ownerName = $core instanceof Conversation
                ? ($core->owner?->name ?? 'Unassigned')
                : 'Unassigned';
            $shop = $shopByPhone[$normalized] ?? null;
            $visitHint = $this->visitHint($shop);

            $allItems[] = [
                'key' => 'platform:'.$publicId,
                'kind' => 'conversation',
                'platform_conversation_public_id' => $publicId,
                'conversation_id' => $core?->id,
                'lane' => $lane,
                'lane_label' => $laneLabel,
                'headline' => $title,
                'title' => $title,
                'name' => $title,
                'subtitle' => $displayPhone,
                'phone' => $displayPhone,
                'normalized_phone' => $normalized,
                'email' => $customer?->email,
                'preview' => $this->previewLine((string) ($row['preview'] ?? ''), $laneLabel),
                'unread' => (bool) ($row['unread'] ?? false),
                'sort_at' => $row['last_message_at'] ?? null,
                'age_label' => $this->ageLabel($row['last_message_at'] ?? null),
                'pressure_score' => $lane === 'needs' ? 50 : 0,
                'customer_id' => $customer?->id,
                'known_customer' => $known,
                'link_status' => $known ? $ownerName : 'Unknown Customer · '.$ownerName,
                'channel_label' => 'SMS',
                'turn_label' => $ownerName,
                'shop_hint' => $visitHint,
                'assigned_label' => $ownerName,
                'select_url' => route('operations.communications.inbox', [
                    'filter' => $filter,
                    'platform_conversation' => $publicId,
                ]),
            ];
        }

        usort($allItems, function (array $a, array $b): int {
            return strcmp((string) ($b['sort_at'] ?? ''), (string) ($a['sort_at'] ?? ''));
        });

        $filterCounts = [
            'needs' => 0,
            'waiting' => 0,
            'resolved' => 0,
            'all' => count($allItems),
        ];
        foreach ($allItems as $item) {
            $lane = (string) ($item['lane'] ?? 'needs');
            if (isset($filterCounts[$lane])) {
                $filterCounts[$lane]++;
            }
        }

        $listItems = $filter === 'all'
            ? $allItems
            : array_values(array_filter(
                $allItems,
                fn (array $item): bool => ($item['lane'] ?? 'needs') === $filter,
            ));

        $presentation = app(\App\Ark\Operations\Communications\CommunicationsInboxPresentation::class);
        $ownerFilter = in_array($ownerFilter, ['everyone', 'mine', 'unassigned'], true) ? $ownerFilter : 'everyone';
        $listItems = $presentation->decorateList($listItems);
        $ownerCounts = [
            'everyone' => count($listItems),
            'mine' => count(array_filter($listItems, fn (array $item): bool => (int) ($item['owner_id'] ?? 0) === (int) $viewer->id)),
            'unassigned' => count(array_filter($listItems, fn (array $item): bool => (int) ($item['owner_id'] ?? 0) === 0)),
        ];
        $listItems = $presentation->applyOwnerFilter($listItems, $ownerFilter, $viewer);

        $selected = null;
        if ($selectedPublicId !== null && $selectedPublicId !== '') {
            foreach ($allItems as $item) {
                if (($item['platform_conversation_public_id'] ?? null) === $selectedPublicId) {
                    $selected = $item;
                    break;
                }
            }
        }
        if ($selected === null && $listItems !== []) {
            $selected = $listItems[0];
        }

        $thread = null;
        $context = null;
        if ($selected !== null) {
            $built = $this->selectedThread($viewer, $selected, $advisors, $filter);
            $thread = $built['thread'];
            $context = $built['context'];
            $thread = $presentation->decorateThread($thread, $selected);
            $context = $presentation->decorateContext($context, $selected, $filter, $ownerFilter);
            if (is_array($thread) && is_array($context['work'] ?? null)) {
                $thread['decision']['work'] = $context['work'];
            }
        }

        $workspace = [
            'section' => 'inbox',
            'list_items' => $listItems,
            'list_count' => count($listItems),
            'filter_counts' => $filterCounts,
            'selected' => $selected,
            'thread' => $thread,
            'context' => $context,
            'platform_backed' => true,
            'owner_filter' => $ownerFilter,
            'owner_counts' => $ownerCounts,
            'list_filter' => $filter,
            'list_title' => match ($filter) {
                'waiting' => 'Waiting',
                'resolved' => 'Resolved',
                'all' => 'Inbox',
                default => 'Needs attention',
            },
            'list_description' => 'Needs attention: shop action is due. Waiting: open, awaiting a customer reply or a future follow-up.',
        ];

        $workspace['poll_signature'] = $this->pollSignature($workspace);

        return $workspace;
    }

    /**
     * @param  array<string, mixed>  $selected
     * @param  list<array{id: int, name: string}>  $advisors
     * @return array{thread: array<string, mixed>|null, context: array<string, mixed>|null}
     */
    private function selectedThread(User $viewer, array $selected, array $advisors, string $filter): array
    {
        $publicId = (string) $selected['platform_conversation_public_id'];
        $shown = $this->client->showConversation($publicId);
        if (! ($shown['ok'] ?? false)) {
            return ['thread' => null, 'context' => null];
        }

        if ($viewer->id) {
            $this->client->markRead($publicId, (int) $viewer->id);
        }

        $messages = is_array($shown['messages'] ?? null) ? $shown['messages'] : [];
        $contact = (string) (($shown['conversation']['contact_address'] ?? null) ?: ($selected['normalized_phone'] ?? $selected['phone'] ?? ''));
        $displayPhone = PhoneNumber::display($contact) ?? $contact;
        $customerId = isset($selected['customer_id']) ? (int) $selected['customer_id'] : 0;
        $customer = $customerId > 0 ? Customer::query()->find($customerId) : null;
        $events = array_map(fn (array $message): array => $this->messageEvent($message), $messages);
        $known = $customer !== null;
        $title = $customer?->name ?? ($selected['title'] ?? $displayPhone);
        $customerUrl = $customer !== null
            ? route('operations.customers.show', $customer)
            : null;
        $shop = $this->callContext->resolveForAttentionList($contact);
        $openRo = $shop?->openRepairOrders->first();
        $workUrl = route('operations.communications.platform-conversations.work', [
            'platformConversation' => $publicId,
        ]);
        $lane = (string) ($selected['lane'] ?? 'needs');
        $ownerName = (string) ($selected['assigned_label'] ?? 'Unassigned');
        $dueLabel = null;
        $core = isset($selected['conversation_id'])
            ? Conversation::query()->with('owner:id,name')->find((int) $selected['conversation_id'])
            : null;
        if ($core instanceof Conversation && $core->follow_up_due_at !== null) {
            $dueLabel = ShopDisplayTimezone::format(
                $core->follow_up_due_at,
                'D M j · g:i A',
            );
        }

        $visitLabel = $this->visitHint($shop);
        $vehicleLabel = $openRo?->vehicle?->display_name;
        $roLabel = $openRo !== null ? 'RO '.$openRo->repairOrder->repairOrderId() : null;
        $roUrl = $openRo !== null
            ? route('operations.repair-orders.show', $openRo->repairOrder)
            : null;
        $roStatus = $openRo?->workflowPostureLabel;

        $thread = [
            'title' => $title,
            'subtitle' => $displayPhone,
            'platform_conversation_public_id' => $publicId,
            'platform_backed' => true,
            'events' => $events,
            'identity' => [
                'name' => $title,
                'phone' => $displayPhone,
                'email' => $customer?->email,
                'known_customer' => $known,
                'customer_status' => $known ? 'Customer' : 'Unknown Customer',
                'link_status' => $known ? 'Customer' : 'Unknown Customer',
                'turn_label' => (string) ($selected['lane_label'] ?? 'Needs attention'),
                'visit_label' => $visitLabel,
                'vehicle_label' => $vehicleLabel,
                'ro_label' => $roLabel,
                'ro_url' => $roUrl,
                'ro_status' => $roStatus,
                'can_mark_handled' => false,
                'mark_read_url' => route('operations.communications.platform-conversations.read', [
                    'platformConversation' => $publicId,
                ]),
                'actions' => array_values(array_filter([
                    $customerUrl !== null ? [
                        'label' => 'Open customer',
                        'url' => $customerUrl,
                        'enabled' => true,
                    ] : null,
                    $roUrl !== null ? [
                        'label' => 'Open RO',
                        'url' => $roUrl,
                        'enabled' => true,
                    ] : null,
                ])),
            ],
            'composer' => [
                'kind' => 'platform_conversation',
                'platform_conversation_public_id' => $publicId,
                'display_phone' => $displayPhone,
                'contact_address' => $contact,
                'customer_id' => $customer?->id,
                'customer' => $customer,
                'conversation' => $core,
                'repair_order' => $openRo?->repairOrder,
                'open_repair_orders' => $shop?->openRepairOrders ?? collect(),
                'send_url' => route('operations.communications.platform-conversations.messages', [
                    'platformConversation' => $publicId,
                ]),
            ],
        ];

        $context = [
            'headline' => $title,
            'phone' => $displayPhone,
            'customer_id' => $customer?->id,
            'conversation_id' => $core?->id,
            'platform_backed' => true,
            'platform_conversation_public_id' => $publicId,
            'link_status' => $known ? 'Customer' : 'Unknown Customer',
            'sections' => [
                'who' => array_filter([
                    'Name' => $title,
                    'Phone' => $displayPhone,
                    'Email' => $customer?->email,
                    'Match' => $known ? 'Customer' : 'Unknown Customer',
                ]),
                'current_visit' => array_filter([
                    'Visit' => $visitLabel,
                    'RO' => $roLabel,
                    'Vehicle' => $vehicleLabel,
                    'Lifecycle' => $roStatus,
                ]),
                'turn' => array_filter([
                    'State' => (string) ($selected['lane_label'] ?? 'Needs attention'),
                    'Advisor' => $ownerName,
                    'Follow-up' => $dueLabel,
                ]),
            ],
            'work' => [
                'url' => $workUrl,
                'filter' => $filter,
                'lane' => $lane,
                'posture_changed_at' => $core?->posture_changed_at?->utc()->toIso8601String(),
                'default_due_at' => $this->defaultDueAt(),
                'advisors' => $advisors,
            ],
            'primary_ro' => $openRo !== null ? [
                'repair_order_id' => $openRo->repairOrder->id,
                'number' => 'RO '.$openRo->repairOrder->repairOrderId(),
                'url' => $roUrl,
                'vehicle' => $vehicleLabel,
                'status' => $roStatus,
            ] : null,
            'actions' => array_values(array_filter([
                $customerUrl !== null ? [
                    'type' => 'link',
                    'label' => 'Open customer',
                    'url' => $customerUrl,
                ] : [
                    'type' => 'link',
                    'label' => 'Find customer',
                    'url' => route('operations.customers.search', ['q' => $displayPhone]),
                ],
                $roUrl !== null ? [
                    'type' => 'link',
                    'label' => 'Open RO',
                    'url' => $roUrl,
                ] : null,
            ])),
        ];

        return ['thread' => $thread, 'context' => $context];
    }

    /**
     * Platform outage: Core work remains visible. Classification is not rewritten.
     *
     * @return array<string, mixed>
     */
    private function coreFallbackInbox(string $filter, string $error): array
    {
        $conversations = Conversation::query()
            ->with('owner:id,name')
            ->where('contact_surface', ConversationContactSurface::Phone)
            ->orderByDesc('posture_changed_at')
            ->orderByDesc('id')
            ->limit(80)
            ->get();

        $allItems = [];
        foreach ($conversations as $conversation) {
            $lane = $this->work->lane($conversation);
            $phone = (string) $conversation->contact_address;
            $displayPhone = PhoneNumber::display($phone) ?? $phone;
            $ownerName = $conversation->owner?->name ?? 'Unassigned';

            $allItems[] = [
                'key' => 'conversation:'.$conversation->id,
                'kind' => 'conversation',
                'conversation_id' => $conversation->id,
                'lane' => $lane,
                'lane_label' => $this->work->laneLabel($conversation),
                'headline' => $displayPhone,
                'title' => $displayPhone,
                'name' => $displayPhone,
                'subtitle' => $displayPhone,
                'phone' => $displayPhone,
                'normalized_phone' => $phone,
                'preview' => $this->work->laneLabel($conversation),
                'sort_at' => optional($conversation->posture_changed_at)?->toIso8601String(),
                'age_label' => $this->ageLabel($conversation->posture_changed_at),
                'pressure_score' => $lane === 'needs' ? 50 : 0,
                'known_customer' => false,
                'link_status' => $ownerName,
                'channel_label' => 'SMS',
                'turn_label' => $ownerName,
                'shop_hint' => 'No current visit',
                'assigned_label' => $ownerName,
                'select_url' => route('operations.communications.inbox', [
                    'filter' => $filter,
                    'conversation' => $conversation->id,
                ]),
            ];
        }

        $filterCounts = [
            'needs' => 0,
            'waiting' => 0,
            'resolved' => 0,
            'all' => count($allItems),
        ];
        foreach ($allItems as $item) {
            $lane = (string) ($item['lane'] ?? 'needs');
            if (isset($filterCounts[$lane])) {
                $filterCounts[$lane]++;
            }
        }

        $listItems = $filter === 'all'
            ? $allItems
            : array_values(array_filter(
                $allItems,
                fn (array $item): bool => ($item['lane'] ?? 'needs') === $filter,
            ));

        $workspace = [
            'section' => 'inbox',
            'list_items' => $listItems,
            'list_count' => count($listItems),
            'filter_counts' => $filterCounts,
            'selected' => $listItems[0] ?? null,
            'thread' => null,
            'context' => null,
            'platform_backed' => true,
            'list_filter' => $filter,
            'list_title' => match ($filter) {
                'waiting' => 'Waiting',
                'resolved' => 'Resolved',
                'all' => 'Inbox',
                default => 'Needs attention',
            },
            'list_description' => 'Who needs the shop right now',
            'error' => $error,
        ];

        $workspace['poll_signature'] = $this->pollSignature($workspace);

        return $workspace;
    }

    private function defaultDueAt(): string
    {
        return ShopDisplayTimezone::now()
            ->addDay()
            ->setTime(8, 0)
            ->format('Y-m-d\TH:i');
    }

    /**
     * @param  list<string>  $phones
     * @return Collection<string, Conversation>
     */
    private function conversationsByPhone(array $phones): Collection
    {
        $normalized = array_values(array_unique(array_filter($phones)));
        if ($normalized === []) {
            return collect();
        }

        return Conversation::query()
            ->with('owner:id,name')
            ->where('contact_surface', ConversationContactSurface::Phone)
            ->whereIn('contact_address', $normalized)
            ->get()
            ->keyBy(fn (Conversation $conversation): string => (string) $conversation->contact_address);
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

    private function visitHint(?CustomerCallContext $shop): string
    {
        $openRo = $shop?->openRepairOrders->first();
        if ($openRo === null) {
            return 'No current visit';
        }

        $vehicle = trim((string) ($openRo->vehicle->display_name ?? ''));
        $status = trim($openRo->workflowPostureLabel);

        return trim('RO '.$openRo->repairOrder->repairOrderId().($vehicle !== '' ? ' · '.$vehicle : '').($status !== '' ? ' · '.$status : ''));
    }

    private function previewLine(string $preview, string $laneLabel): string
    {
        $preview = trim($preview);
        if ($preview === '') {
            return $laneLabel;
        }

        return $preview;
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>
     */
    private function messageEvent(array $message): array
    {
        $direction = (string) ($message['direction'] ?? 'inbound');
        $occurredAt = $message['occurred_at'] ?? null;
        $status = (string) ($message['delivery_status'] ?? '');

        return [
            'platform_message_public_id' => $message['public_id'] ?? null,
            'kind' => 'sms',
            'direction' => $direction,
            'direction_label' => $direction === 'outbound' ? 'Sent' : 'Received',
            'channel_label' => 'SMS'.($status !== '' ? ' · '.$status : ''),
            'body' => (string) ($message['body'] ?? ''),
            'occurred_at' => $occurredAt,
            'occurred_at_label' => $this->occurredLabel($occurredAt),
            'delivery_status' => $status,
            'attachments' => $message['attachments'] ?? [],
        ];
    }

    /**
     * @param  array<string, mixed>  $workspace
     */
    public function pollSignature(array $workspace): string
    {
        $parts = [
            (string) ($workspace['list_count'] ?? 0),
            (string) ($workspace['list_filter'] ?? ''),
            (string) ($workspace['error'] ?? ''),
        ];

        foreach ($workspace['list_items'] ?? [] as $item) {
            $parts[] = implode(':', [
                (string) ($item['platform_conversation_public_id'] ?? ''),
                (string) ($item['sort_at'] ?? ''),
                (string) ($item['preview'] ?? ''),
                (string) ($item['headline'] ?? ''),
                (string) ($item['lane'] ?? ''),
                (string) ($item['assigned_label'] ?? ''),
            ]);
        }

        $thread = is_array($workspace['thread'] ?? null) ? $workspace['thread'] : [];
        $parts[] = (string) count($thread['events'] ?? []);
        $lastEvent = is_array($thread['events'] ?? null) ? (end($thread['events']) ?: []) : [];
        $parts[] = (string) ($lastEvent['platform_message_public_id'] ?? '');
        $parts[] = (string) ($lastEvent['body'] ?? '');
        $parts[] = (string) ($thread['identity']['turn_label'] ?? '');

        return md5(implode('|', $parts));
    }

    private function ageLabel(mixed $timestamp): string
    {
        if (! filled($timestamp)) {
            return '';
        }

        try {
            return Carbon::parse((string) $timestamp)->diffForHumans();
        } catch (\Throwable) {
            return '';
        }
    }

    private function occurredLabel(mixed $timestamp): string
    {
        if (! filled($timestamp)) {
            return '';
        }

        try {
            return Carbon::parse((string) $timestamp)->timezone(config('app.timezone'))->format('M j · g:i A');
        } catch (\Throwable) {
            return (string) $timestamp;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function empty(string $filter = 'needs'): array
    {
        $workspace = [
            'section' => 'inbox',
            'list_items' => [],
            'list_count' => 0,
            'filter_counts' => [
                'needs' => 0,
                'waiting' => 0,
                'resolved' => 0,
                'all' => 0,
            ],
            'selected' => null,
            'thread' => null,
            'context' => null,
            'platform_backed' => true,
            'list_filter' => $filter,
            'list_title' => 'Inbox',
            'list_description' => 'Customer conversations',
        ];

        $workspace['poll_signature'] = $this->pollSignature($workspace);

        return $workspace;
    }
}
