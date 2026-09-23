<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationWork;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Timeline\OperationalEventEntry;
use App\Ark\Operations\Timeline\OperationalEventKind;
use App\Ark\Operations\Timeline\OperationalEventTone;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

final class CommunicationsInboxPresentation
{
    public function __construct(
        private readonly ConversationWork $work,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    public function decorateList(array $items): array
    {
        $ids = [];
        foreach ($items as $item) {
            $id = $this->conversationId($item);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        $conversations = $ids === []
            ? collect()
            : Conversation::query()->with('owner:id,name')->whereIn('id', array_unique($ids))->get()->keyBy('id');

        return array_values(array_map(function (array $item) use ($conversations): array {
            $conversation = $conversations->get($this->conversationId($item));
            $lane = $conversation instanceof Conversation
                ? $this->work->lane($conversation)
                : (string) ($item['lane'] ?? 'needs');
            $badge = $conversation instanceof Conversation
                ? $this->work->badgeLabel($conversation)
                : (string) ($item['lane_label'] ?? $this->laneTitle($lane));
            $name = (string) ($item['headline'] ?? $item['name'] ?? 'Unknown');
            $ownerName = (string) ($conversation?->owner?->name ?? $item['assigned_label'] ?? '');
            $preview = trim((string) ($item['snippet'] ?? ''));
            if ($preview === '') {
                $preview = trim((string) ($item['preview'] ?? ''));
            }

            $item['lane'] = $lane;
            $item['badge'] = $badge;
            $item['badge_tone'] = $badge === 'Follow-up today' ? 'follow' : $lane;
            $item['initials'] = $this->initials($name);
            $item['owner_id'] = $conversation?->owned_by_user_id ?? ($item['owner_id'] ?? null);
            $item['owner_initials'] = $this->initials($ownerName);
            $item['preview'] = $preview;

            return $item;
        }, $items));
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    public function applyOwnerFilter(array $items, string $ownerFilter, User $viewer): array
    {
        return match ($ownerFilter) {
            'mine' => array_values(array_filter(
                $items,
                fn (array $item): bool => (int) ($item['owner_id'] ?? 0) === (int) $viewer->id,
            )),
            'unassigned' => array_values(array_filter(
                $items,
                fn (array $item): bool => (int) ($item['owner_id'] ?? 0) === 0,
            )),
            default => $items,
        };
    }

    /**
     * @param  array<string, mixed>|null  $thread
     * @param  array<string, mixed>  $selected
     * @return array<string, mixed>|null
     */
    public function decorateThread(?array $thread, array $selected): ?array
    {
        if ($thread === null) {
            return null;
        }

        $conversation = $this->conversationFrom($selected);
        $lane = $conversation instanceof Conversation
            ? $this->work->lane($conversation)
            : (string) ($selected['lane'] ?? 'needs');
        $identity = is_array($thread['identity'] ?? null) ? $thread['identity'] : [];
        $identity['lane'] = $lane;
        $identity['lane_label'] = $conversation instanceof Conversation
            ? $this->work->laneLabel($conversation)
            : $this->laneTitle($lane);
        $last = $this->lastEvidence($thread['events'] ?? []);
        $identity['last_interaction_label'] = filled($last['age'])
            ? trim($last['activity'].' '.$last['age'])
            : '';
        $thread['identity'] = $identity;
        $thread['decision'] = $this->decision($conversation, $lane, $last, is_array($thread['decision']['work'] ?? null) ? $thread['decision']['work'] : null);

        return $thread;
    }

    /**
     * @param  array<string, mixed>|null  $context
     * @param  array<string, mixed>  $selected
     * @return array<string, mixed>|null
     */
    public function decorateContext(?array $context, array $selected, string $filter, string $ownerFilter): ?array
    {
        if ($context === null) {
            return null;
        }

        $conversation = $this->conversationFrom($selected);
        $customerId = (int) (data_get($context, 'customer.id') ?: data_get($context, 'customer_id') ?: ($selected['customer_id'] ?? 0));
        $customer = $customerId > 0 ? Customer::query()->find($customerId) : null;
        $primary = is_array($context['primary_ro'] ?? null) ? $context['primary_ro'] : [];
        $existingWork = is_array($context['work'] ?? null) ? $context['work'] : [];

        $context['customer_url'] = $customer instanceof Customer
            ? route('operations.customers.show', $customer)
            : ($context['customer_url'] ?? null);
        $context['internal_note_url'] = $conversation instanceof Conversation
            ? route('operations.communications.conversations.internal-note', $conversation)
            : ($context['internal_note_url'] ?? null);
        $context['recent_visits'] = $this->recentVisits($customer);
        $context['vehicle'] = [
            'label' => $primary['vehicle'] ?? data_get($context, 'current_visit.vehicle_label'),
            'ro_number' => $primary['number'] ?? data_get($context, 'current_visit.ro_label'),
            'status' => $primary['status'] ?? data_get($context, 'current_visit.lifecycle_label'),
            'url' => $primary['url'] ?? null,
        ];

        if ($conversation instanceof Conversation) {
            $context['work'] = array_merge([
                'url' => route('operations.communications.conversations.work', $conversation),
                'lane' => $this->work->lane($conversation),
                'filter' => $filter,
                'owner' => $ownerFilter,
                'advisors' => is_array($context['assignable_advisors'] ?? null) ? $context['assignable_advisors'] : [],
                'default_due_at' => ShopDisplayTimezone::now()->addDay()->setTime(8, 0)->format('Y-m-d\TH:i'),
                'posture_changed_at' => $conversation->posture_changed_at?->toIso8601String(),
                'owned_by_user_id' => $conversation->owned_by_user_id,
                'owner_name' => $conversation->owner?->name ?? 'Unassigned',
            ], array_filter([
                'url' => $existingWork['url'] ?? null,
                'advisors' => $existingWork['advisors'] ?? null,
            ]));
        }

        return $context;
    }

    /**
     * @param  array{body: string, age: string, outbound: bool, title: string, kind: string, activity: string}  $last
     * @param  array<string, mixed>|null  $existingWork
     * @return array<string, mixed>
     */
    private function decision(?Conversation $conversation, string $lane, array $last, ?array $existingWork): array
    {
        $overdue = $conversation instanceof Conversation && $this->work->isFollowUpOverdue($conversation);
        $tone = $overdue ? 'overdue' : $lane;

        if ($lane === 'resolved') {
            $actor = $conversation?->owner?->name ?? 'an advisor';
            $when = $conversation?->resolved_at
                ? ShopDisplayTimezone::format($conversation->resolved_at, 'M j · g:i A')
                : null;
            $title = 'Resolved';
            $prompt = 'Resolved by '.$actor.($when ? ' at '.$when.'.' : '.');
            $excerpt = '';
        } elseif ($overdue) {
            $when = $conversation?->follow_up_due_at
                ? ShopDisplayTimezone::format($conversation->follow_up_due_at, 'M j · g:i A')
                : null;
            $title = 'Follow-up overdue';
            $prompt = 'Follow-up overdue'.($when ? ' since '.$when.'.' : '.');
            $excerpt = '';
        } elseif ($lane === 'waiting') {
            $when = $conversation?->follow_up_due_at
                ? ShopDisplayTimezone::format($conversation->follow_up_due_at, 'M j · g:i A')
                : ($conversation?->posture_changed_at
                    ? ShopDisplayTimezone::format($conversation->posture_changed_at, 'M j · g:i A')
                    : null);
            $title = 'Waiting';
            $prompt = $conversation?->follow_up_due_at
                ? 'Open - follow-up scheduled'.($when ? ' for '.$when : '').'. Moves to Needs attention when overdue.'
                : 'Open - waiting on the customer'.($when ? ' since '.$when : '').'. Not forgotten.';
            $excerpt = '';
        } else {
            $title = $last['title'] !== '' ? $last['title'] : 'Needs attention';
            $prompt = match ($last['kind']) {
                'sms', 'messenger', 'email' => $last['outbound']
                    ? 'Shop action may be due. Schedule a follow-up, mark waiting, or resolve.'
                    : 'Customer wrote last - reply if needed, mark waiting if the shop is working this, or resolve if finished.',
                'missed_call', 'voicemail', 'call', 'recording' => 'Shop action is due. Call back, reply, or resolve.',
                default => 'Shop action may be due. Reply if needed, mark waiting, or resolve.',
            };
            $excerpt = $last['body'];
        }

        $work = $existingWork;
        if ($work === null && $conversation instanceof Conversation) {
            $work = [
                'url' => route('operations.communications.conversations.work', $conversation),
                'lane' => $lane,
                'filter' => request()->string('filter')->toString() ?: $lane,
                'owner' => request()->string('owner')->toString() ?: 'everyone',
                'default_due_at' => ShopDisplayTimezone::now()->addDay()->setTime(8, 0)->format('Y-m-d\TH:i'),
                'posture_changed_at' => $conversation->posture_changed_at?->toIso8601String(),
            ];
        }

        return [
            'tone' => $tone,
            'title' => $title,
            'age' => $last['age'],
            'excerpt' => $excerpt,
            'prompt' => $prompt,
            'lane' => $lane,
            'work' => $work,
        ];
    }

    /**
     * @param  list<mixed>  $events
     * @return array{body: string, age: string, outbound: bool, title: string, kind: string, activity: string}
     */
    private function lastEvidence(array $events): array
    {
        $empty = [
            'body' => '',
            'age' => '',
            'outbound' => false,
            'title' => '',
            'kind' => '',
            'activity' => '',
        ];

        for ($i = count($events) - 1; $i >= 0; $i--) {
            $event = $events[$i];
            if ($event instanceof OperationalEventEntry) {
                $kind = $event->kind->value;
                $outbound = $event->tone === OperationalEventTone::Shop;
                $body = trim((string) ($event->body ?? ''));
                if ($body === '' || $body === '(attachment)') {
                    $body = $event->headline;
                }

                return [
                    'body' => $body,
                    'age' => $event->occurredAt->diffForHumans(short: true),
                    'outbound' => $outbound,
                    'title' => $this->evidenceTitle($kind, $outbound),
                    'kind' => $kind,
                    'activity' => $this->activityLabel($kind),
                ];
            }

            if (is_array($event)) {
                $body = trim((string) ($event['body'] ?? $event['headline'] ?? ''));
                if ($body === '') {
                    continue;
                }
                $kind = (string) ($event['kind'] ?? OperationalEventKind::Sms->value);
                $direction = (string) ($event['direction'] ?? 'inbound');
                $outbound = in_array($direction, ['outbound', 'shop'], true);

                return [
                    'body' => $body,
                    'age' => (string) ($event['occurred_at_label'] ?? ''),
                    'outbound' => $outbound,
                    'title' => $this->evidenceTitle($kind, $outbound),
                    'kind' => $kind,
                    'activity' => $this->activityLabel($kind),
                ];
            }
        }

        return $empty;
    }

    private function evidenceTitle(string $kind, bool $outbound): string
    {
        return match ($kind) {
            OperationalEventKind::Sms->value,
            OperationalEventKind::Messenger->value,
            OperationalEventKind::Email->value => $outbound ? 'Shop wrote last' : 'Customer wrote last',
            OperationalEventKind::MissedCall->value => 'Missed call',
            OperationalEventKind::Voicemail->value => 'Voicemail',
            OperationalEventKind::Call->value,
            OperationalEventKind::Recording->value => 'Call',
            OperationalEventKind::Portal->value,
            OperationalEventKind::PortalActivity->value,
            OperationalEventKind::EstimateViewed->value => 'Portal activity',
            OperationalEventKind::EstimateSent->value => 'Estimate sent',
            OperationalEventKind::Approval->value => 'Approval recorded',
            OperationalEventKind::Payment->value => 'Payment recorded',
            OperationalEventKind::VehicleStatus->value,
            OperationalEventKind::StatusChange->value => 'Repair order updated',
            OperationalEventKind::Inspection->value => 'Inspection updated',
            OperationalEventKind::Appointment->value => 'Appointment',
            OperationalEventKind::InternalNote->value => 'Internal note',
            default => 'Activity',
        };
    }

    private function activityLabel(string $kind): string
    {
        return match ($kind) {
            OperationalEventKind::Sms->value,
            OperationalEventKind::Messenger->value,
            OperationalEventKind::Email->value => 'Last text',
            OperationalEventKind::MissedCall->value,
            OperationalEventKind::Voicemail->value,
            OperationalEventKind::Call->value,
            OperationalEventKind::Recording->value => 'Last call',
            OperationalEventKind::Portal->value,
            OperationalEventKind::PortalActivity->value,
            OperationalEventKind::EstimateViewed->value,
            OperationalEventKind::EstimateSent->value => 'Last portal activity',
            OperationalEventKind::InternalNote->value => 'Last internal note',
            default => 'Last activity',
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentVisits(?Customer $customer): array
    {
        if (! $customer instanceof Customer) {
            return [];
        }

        return RepairOrder::query()
            ->where('customer_id', $customer->id)
            ->latest('id')
            ->limit(4)
            ->get()
            ->map(fn (RepairOrder $repairOrder): array => [
                'number' => 'RO '.$repairOrder->repair_order_id,
                'date' => optional($repairOrder->created_at)->timezone(config('app.display_timezone'))->format('m/d/Y'),
                'status' => $repairOrder->statusDisplayLabel(),
                'url' => route('operations.repair-orders.show', $repairOrder),
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function conversationFrom(array $item): ?Conversation
    {
        $id = $this->conversationId($item);

        return $id > 0
            ? Conversation::query()->with('owner:id,name')->find($id)
            : null;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function conversationId(array $item): int
    {
        $key = (string) ($item['key'] ?? '');
        if (str_starts_with($key, 'conversation:')) {
            return (int) Str::after($key, 'conversation:');
        }

        return (int) ($item['conversation_id'] ?? 0);
    }

    private function laneTitle(string $lane): string
    {
        return match ($lane) {
            'waiting' => 'Waiting',
            'resolved' => 'Resolved',
            default => 'Needs attention',
        };
    }

    private function initials(string $name): string
    {
        $name = trim($name);
        if ($name === '' || strcasecmp($name, 'Unassigned') === 0) {
            return '';
        }

        if (preg_match('/\d/', $name) === 1 && preg_match('/[A-Za-z]/', $name) !== 1) {
            return '';
        }

        $parts = preg_split('/\s+/', $name) ?: [];
        $letters = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $letters .= strtoupper(substr($part, 0, 1));
        }

        return $letters;
    }
}
