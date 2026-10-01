<?php

namespace App\Ark\Platform\Communications;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Timeline\OperationalEventEntry;
use App\Ark\Operations\Timeline\OperationalEventKind;
use App\Ark\Operations\Timeline\OperationalEventSource;
use App\Ark\Operations\Timeline\OperationalEventTone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Read Platform SMS into Hub / RO timelines. Does not write Core ConversationMessage.
 */
final class PlatformSmsTimelineProjection
{
    public function __construct(
        private readonly ArkCommunicationsClient $client,
    ) {}

    /**
     * @return Collection<int, OperationalEventEntry>
     */
    public function forCustomer(Customer $customer, ?string $normalizedPhone, int $limit = 100): Collection
    {
        if (! ManagedCommunicationsGate::platformInbox()) {
            return collect();
        }

        $listed = $this->client->listConversations(null, 80);

        if (! ($listed['ok'] ?? false)) {
            return collect();
        }

        $conversations = is_array($listed['conversations'] ?? null) ? $listed['conversations'] : [];
        $customerPhones = $this->customerPhones($customer, $normalizedPhone);
        $entries = collect();

        foreach ($conversations as $row) {
            if (! is_array($row)) {
                continue;
            }

            if (! $this->matchesCustomer($row, $customer, $customerPhones)) {
                continue;
            }

            $publicId = (string) ($row['public_id'] ?? '');

            if ($publicId === '') {
                continue;
            }

            $shown = $this->client->showConversation($publicId, $limit);

            if (! ($shown['ok'] ?? false)) {
                continue;
            }

            $messages = is_array($shown['messages'] ?? null) ? $shown['messages'] : [];

            foreach ($messages as $message) {
                if (! is_array($message)) {
                    continue;
                }

                $mapped = $this->mapMessage($message);

                if ($mapped instanceof OperationalEventEntry) {
                    $entries->push($mapped);
                }
            }
        }

        return $entries
            ->sortByDesc(fn (OperationalEventEntry $entry): int => $entry->occurredAt->timestamp)
            ->take($limit)
            ->values();
    }

    /**
     * Messages on the customer phone thread that name this repair order.
     *
     * @return Collection<int, OperationalEventEntry>
     */
    public function forRepairOrder(RepairOrder $repairOrder, int $limit = 50): Collection
    {
        if (! ManagedCommunicationsGate::platformInbox()) {
            return collect();
        }

        $listed = $this->client->messagesForReference(
            'repair_order',
            (string) $repairOrder->repair_order_id,
            $limit,
        );

        if (! ($listed['ok'] ?? false)) {
            return collect();
        }

        $messages = is_array($listed['messages'] ?? null) ? $listed['messages'] : [];
        $entries = collect();

        foreach ($messages as $message) {
            if (! is_array($message)) {
                continue;
            }

            $mapped = $this->mapMessage($message);
            if ($mapped instanceof OperationalEventEntry) {
                $entries->push($mapped);
            }
        }

        return $entries->values();
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $customerPhones
     */
    private function matchesCustomer(array $row, Customer $customer, array $customerPhones): bool
    {
        $coreCustomerId = isset($row['core_customer_id']) ? (int) $row['core_customer_id'] : 0;

        if ($coreCustomerId === (int) $customer->id) {
            return true;
        }

        $contact = PhoneNumber::normalize((string) ($row['contact_address'] ?? ''));

        return $contact !== null && in_array($contact, $customerPhones, true);
    }

    /**
     * @return list<string>
     */
    private function customerPhones(Customer $customer, ?string $normalizedPhone): array
    {
        $phones = array_filter([
            PhoneNumber::normalize((string) $customer->phone),
            PhoneNumber::normalize($normalizedPhone),
        ]);

        return array_values(array_unique($phones));
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function mapMessage(array $message): ?OperationalEventEntry
    {
        $occurredAt = $message['occurred_at'] ?? $message['created_at'] ?? null;

        if (! filled($occurredAt)) {
            return null;
        }

        try {
            $when = Carbon::parse((string) $occurredAt);
        } catch (\Throwable) {
            return null;
        }

        $direction = strtolower(trim((string) ($message['direction'] ?? 'inbound')));
        $outbound = $direction === 'outbound';
        $body = trim((string) ($message['body'] ?? ''));
        $attachments = PlatformMessageMedia::present($message['attachments'] ?? []);
        $contextLabel = CommunicationMessageContext::label($message['context'] ?? []);
        $deliveryStatus = strtolower(trim((string) ($message['delivery_status'] ?? '')));
        $channelLabel = $attachments !== [] ? 'MMS' : 'SMS';
        if (in_array($deliveryStatus, ['failed', 'undelivered'], true)) {
            $channelLabel .= ' · Not delivered';
        }

        return new OperationalEventEntry(
            source: OperationalEventSource::PlatformMessage,
            kind: OperationalEventKind::Sms,
            occurredAt: $when,
            headline: $outbound ? 'Sent' : 'Received',
            body: $body !== '' && $body !== '(attachment)' ? $body : null,
            actor: isset($message['actor_name']) ? (string) $message['actor_name'] : null,
            tone: $outbound ? OperationalEventTone::Shop : OperationalEventTone::Customer,
            links: [],
            metadata: [
                'hub_filter' => 'text',
                'channel_label' => $channelLabel,
                'direction' => $outbound ? 'outbound' : 'inbound',
                'platform_message_public_id' => $message['public_id'] ?? null,
                'provider_message_id' => $message['provider_message_id'] ?? null,
                'delivery_status' => $message['delivery_status'] ?? null,
                'attachments' => $attachments,
                'context_label' => $contextLabel,
            ],
        );
    }
}
