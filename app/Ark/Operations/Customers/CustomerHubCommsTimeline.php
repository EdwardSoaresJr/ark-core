<?php

namespace App\Ark\Operations\Customers;

use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Timeline\OperationalEventEntry;
use App\Ark\Operations\Timeline\Mappers\ConversationMessageEventMapper;
use App\Ark\Operations\Timeline\UnifiedOperationalTimeline;
use Illuminate\Support\Collection;

/**
 * Customer Hub comms tab — unified timeline consumer.
 */
class CustomerHubCommsTimeline
{
    public function __construct(
        private readonly UnifiedOperationalTimeline $timeline,
        private readonly ConversationMessageEventMapper $messageMapper,
    ) {}

    /**
     * @param  Collection<int, ConversationMessage>  $messages
     * @param  iterable<int, CallSession>  $callSessions
     * @return Collection<int, OperationalEventEntry>
     */
    public function build(Collection $messages, iterable $callSessions): Collection
    {
        return $this->timeline->forCustomerComms($messages, $callSessions);
    }

    /**
     * Full customer conversation timeline — messages, calls, workflow, portal, payments.
     *
     * @return Collection<int, OperationalEventEntry>
     */
    public function buildForCustomer(Customer $customer, ?string $normalizedPhone, int $limit = 100): Collection
    {
        return $this->timeline->forCustomerRelationship($customer, $normalizedPhone, $limit);
    }

    /**
     * @param  Collection<int, OperationalEventEntry>  $rows
     * @return array{all: int, call: int, text: int, email: int, portal: int, logged: int}
     */
    public function counts(Collection $rows): array
    {
        return [
            'all' => $rows->count(),
            'call' => $rows->where(fn (OperationalEventEntry $row): bool => $row->hubFilter() === 'call')->count(),
            'text' => $rows->where(fn (OperationalEventEntry $row): bool => $row->hubFilter() === 'text')->count(),
            'email' => $rows->where(fn (OperationalEventEntry $row): bool => $row->hubFilter() === 'email')->count(),
            'portal' => $rows->where(fn (OperationalEventEntry $row): bool => $row->hubFilter() === 'portal')->count(),
            'logged' => $rows->where(fn (OperationalEventEntry $row): bool => $row->hubFilter() === 'logged')->count(),
        ];
    }

    public function filterForMessage(ConversationMessage $message): string
    {
        return $this->messageMapper->map($message)->hubFilter();
    }
}
