<?php

namespace Tests\Support;

use App\Ark\Operations\Communications\CommunicationsAttentionDedupe;
use App\Ark\Operations\Communications\CommunicationsQueueResolver;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationLink;
use App\Ark\Operations\Conversations\ConversationTurnPrecedence;
use App\Ark\Operations\Conversations\ConversationWaitingOn;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Ark\Operations\Timeline\UnifiedOperationalTimeline;
use App\Models\User;

/**
 * H0 measurable probes for The Six Ones — not a product surface.
 *
 * @see docs/communications/ark-conversations-v1.md
 */
final class ConversationsH0
{
    /**
     * @return array{
     *     relationship_id: int,
     *     triage_rows: int,
     *     turn: string,
     *     turn_conflict: bool,
     *     identity: string,
     *     story_count: int,
     *     story_chronology_ok: bool,
     *     next_action: string,
     * }
     */
    public static function probe(Customer $customer, ?RepairOrder $repairOrder = null, ?User $viewer = null): array
    {
        $customer->refresh();
        $phone = PhoneNumber::normalize((string) $customer->phone);

        $triageRows = self::triageRowsForCustomer($customer, $viewer);
        $phoneConversation = self::phoneConversation($customer);
        $unworkedCalls = self::unworkedCalls($customer, $phone);

        $waitingOn = $phoneConversation?->waiting_on;
        $waitingValue = $waitingOn instanceof ConversationWaitingOn
            ? $waitingOn->value
            : (is_string($waitingOn) ? $waitingOn : null);

        $turnConflict = false;
        if ($phoneConversation !== null) {
            $computed = app(ConversationTurnPrecedence::class)
                ->waitingOn($phoneConversation);
            $stored = $phoneConversation->waiting_on instanceof ConversationWaitingOn
                ? $phoneConversation->waiting_on
                : ConversationWaitingOn::tryFrom((string) $waitingValue);
            $turnConflict = $stored !== null && $computed !== $stored;
        }

        $turn = match (true) {
            $waitingValue === ConversationWaitingOn::Shop->value => 'waiting_on_shop',
            $waitingValue === ConversationWaitingOn::Customer->value => 'waiting_on_customer',
            $unworkedCalls > 0 => 'waiting_on_shop',
            default => 'none',
        };

        $entries = app(UnifiedOperationalTimeline::class)
            ->forCustomerRelationship($customer, PhoneNumber::normalize((string) $customer->phone));

        $occurred = [];
        foreach ($entries as $entry) {
            $occurred[] = $entry->occurredAt->getTimestamp();
        }

        $chronologyOk = true;
        for ($i = 1, $n = count($occurred); $i < $n; $i++) {
            // Newest-first is allowed; never ascending then descending chaos.
            // Require monotonically non-increasing (newest first) OR non-decreasing (oldest first).
        }
        if (count($occurred) >= 2) {
            $newestFirst = true;
            $oldestFirst = true;
            for ($i = 1, $n = count($occurred); $i < $n; $i++) {
                if ($occurred[$i] > $occurred[$i - 1]) {
                    $newestFirst = false;
                }
                if ($occurred[$i] < $occurred[$i - 1]) {
                    $oldestFirst = false;
                }
            }
            $chronologyOk = $newestFirst || $oldestFirst;
        }

        $identity = trim(implode(' ', array_filter([
            $customer->first_name,
            $customer->last_name,
        ])));

        $nextAction = self::nextAction($customer, $repairOrder, $unworkedCalls, $waitingValue);

        return [
            'relationship_id' => (int) $customer->id,
            'triage_rows' => count($triageRows),
            'turn' => $turn,
            'turn_conflict' => $turnConflict,
            'identity' => $identity,
            'story_count' => count($entries),
            'story_chronology_ok' => $chronologyOk,
            'next_action' => $nextAction,
        ];
    }

    public static function assertSixOnes(
        Customer $customer,
        ?RepairOrder $repairOrder = null,
        ?User $viewer = null,
        string $step = '',
        bool $requireActiveTurn = true,
    ): void {
        $prefix = $step !== '' ? "[{$step}] " : '';
        $probe = self::probe($customer, $repairOrder, $viewer);

        expect($probe['relationship_id'])->toBe((int) $customer->id, $prefix.'One Relationship');
        expect($probe['identity'])->not->toBe('', $prefix.'One Identity');
        expect($probe['triage_rows'])->toBeLessThanOrEqual(1, $prefix.'One Thread (triage ≤ 1 row for relationship)');
        expect($probe['turn_conflict'])->toBeFalse($prefix.'One Turn (stored Turn matches precedence)');
        if ($requireActiveTurn) {
            expect($probe['turn'])->not->toBe('none', $prefix.'One Turn (must be computable while saga is active)');
        }
        expect($probe['story_chronology_ok'])->toBeTrue($prefix.'One Story (chronological by operational occurrence)');
        expect($probe['next_action'])->not->toBe('', $prefix.'One Next Action');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function triageRowsForCustomer(Customer $customer, ?User $viewer): array
    {
        $queue = app(CommunicationsQueueResolver::class)->resolveAttention($viewer);
        $needs = $queue['needs_attention'] ?? $queue['items'] ?? [];

        $forCustomer = array_values(array_filter(
            is_array($needs) ? $needs : [],
            static fn (array $row): bool => (int) ($row['customer_id'] ?? 0) === (int) $customer->id,
        ));

        return app(CommunicationsAttentionDedupe::class)->dedupe($forCustomer);
    }

    private static function phoneConversation(Customer $customer): ?Conversation
    {
        $phone = PhoneNumber::normalize((string) $customer->phone);
        if ($phone === null) {
            return null;
        }

        $byAddress = Conversation::query()
            ->where('contact_surface', ConversationContactSurface::Phone)
            ->where('contact_address', $phone)
            ->first();

        if ($byAddress !== null) {
            return $byAddress;
        }

        $linkedIds = ConversationLink::query()
            ->where('linkable_type', (new Customer)->getMorphClass())
            ->where('linkable_id', $customer->id)
            ->pluck('conversation_id');

        return Conversation::query()
            ->whereIn('id', $linkedIds)
            ->where('contact_surface', ConversationContactSurface::Phone)
            ->first();
    }

    private static function unworkedCalls(Customer $customer, ?string $phone): int
    {
        return CallSession::query()
            ->whereNull('worked_at')
            ->where(function ($query) use ($customer, $phone): void {
                $query->where('customer_id', $customer->id);
                if ($phone !== null) {
                    $query->orWhere('normalized_from', $phone);
                }
            })
            ->whereIn('status', [
                CallSessionStatus::Ringing->value,
                CallSessionStatus::Missed->value,
                CallSessionStatus::Answered->value,
            ])
            ->count();
    }

    private static function nextAction(
        Customer $customer,
        ?RepairOrder $repairOrder,
        int $unworkedCalls,
        ?string $waitingValue,
    ): string {
        if ($waitingValue === ConversationWaitingOn::Shop->value) {
            return $unworkedCalls > 0 ? 'Handle call' : 'Reply';
        }

        if ($waitingValue === ConversationWaitingOn::Customer->value) {
            return 'Wait for customer';
        }

        if ($unworkedCalls > 0) {
            return 'Handle call';
        }

        if ($repairOrder !== null) {
            $roAction = trim($repairOrder->fresh()->communicationNextAction());
            if ($roAction !== '' && $roAction !== '—') {
                return $roAction;
            }
        }

        return '';
    }
}
