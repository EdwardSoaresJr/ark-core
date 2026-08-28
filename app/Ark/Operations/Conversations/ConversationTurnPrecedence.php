<?php

namespace App\Ark\Operations\Conversations;

use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Messaging\MessageActionContract;
use App\Ark\Operations\Messaging\MessageActionReply;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\CallSessionStatus;
use Carbon\CarbonInterface;

/**
 * H0 communication-event precedence.
 *
 * Turn is computed from the newest unresolved inbound customer communication —
 * transport-agnostic. SMS, CallSession, voicemail, and portal-originated inbound
 * messages are all InboundCustomerCommunication projections.
 *
 * Resolution (shop action) after an inbound's operational occurrence:
 * - Advisor outbound SMS / Messenger
 * - Advisor marks inbound call / voicemail handled (worked_at)
 * - Advisor outbound call completed
 *
 * Explicit: an inbound call may be resolved by outbound SMS (shop answered the need).
 *
 * @see docs/communications/ark-conversations-v1.md
 */
final class ConversationTurnPrecedence
{
    public function waitingOn(Conversation $conversation): ConversationWaitingOn
    {
        if ($this->newestUnresolvedInboundOccurredAt($conversation) !== null) {
            return ConversationWaitingOn::Shop;
        }

        return ConversationWaitingOn::Customer;
    }

    public function newestUnresolvedInboundOccurredAt(Conversation $conversation): ?CarbonInterface
    {
        $inbounds = $this->inboundOccurrences($conversation);
        $resolutions = $this->shopResolutionOccurrences($conversation);

        $newestUnresolved = null;

        // An explicit human resolution wins a same-second tie — unlike ambient
        // outbound sends, clicking Mark handled is a deliberate shop response.
        $explicitResolvedAt = $conversation->resolved_at instanceof CarbonInterface
            ? $conversation->resolved_at
            : null;

        foreach ($inbounds as $inboundAt) {
            $resolved = $explicitResolvedAt !== null
                && $explicitResolvedAt->greaterThanOrEqualTo($inboundAt);

            if (! $resolved) {
                foreach ($resolutions as $resolvedAt) {
                    // Strictly after — same-second outbound (e.g. reminder just sent) must not
                    // pretend to resolve a customer reply that arrived in the same second.
                    if ($resolvedAt->greaterThan($inboundAt)) {
                        $resolved = true;
                        break;
                    }
                }
            }

            if (! $resolved && ($newestUnresolved === null || $inboundAt->greaterThan($newestUnresolved))) {
                $newestUnresolved = $inboundAt;
            }
        }

        return $newestUnresolved;
    }

    /**
     * @return list<CarbonInterface>
     */
    private function inboundOccurrences(Conversation $conversation): array
    {
        $times = [];

        $messages = ConversationMessage::query()
            ->where('conversation_id', $conversation->id)
            ->where('direction', OperationalCommunicationDirection::Inbound)
            ->whereIn('channel', [
                OperationalCommunicationChannel::Sms->value,
                OperationalCommunicationChannel::Messenger->value,
                OperationalCommunicationChannel::Website->value,
            ])
            ->get(['occurred_at', 'channel', 'metadata']);

        foreach ($messages as $message) {
            if (! $message->occurred_at instanceof CarbonInterface) {
                continue;
            }

            // Auto-handled Message Action replies do not create shop work.
            $reply = is_array($message->metadata)
                ? ($message->metadata[MessageActionContract::META_REPLY] ?? null)
                : null;

            if (in_array($reply, [
                MessageActionReply::Confirm->value,
                MessageActionReply::Directions->value,
            ], true)) {
                continue;
            }

            $times[] = $message->occurred_at->copy();
        }

        foreach ($this->inboundCallSessions($conversation) as $session) {
            $at = $session->started_at ?? $session->created_at;
            if ($at instanceof CarbonInterface) {
                $times[] = $at->copy();
            }
        }

        return $times;
    }

    /**
     * @return list<CarbonInterface>
     */
    private function shopResolutionOccurrences(Conversation $conversation): array
    {
        $times = [];

        $outbound = ConversationMessage::query()
            ->where('conversation_id', $conversation->id)
            ->where('direction', OperationalCommunicationDirection::Outbound)
            ->whereIn('channel', [
                OperationalCommunicationChannel::Sms->value,
                OperationalCommunicationChannel::Messenger->value,
            ])
            ->whereNotNull('occurred_at')
            ->get(['occurred_at']);

        foreach ($outbound as $message) {
            if ($message->occurred_at instanceof CarbonInterface) {
                $times[] = $message->occurred_at->copy();
            }
        }

        foreach ($this->inboundCallSessions($conversation) as $session) {
            if ($session->worked_at instanceof CarbonInterface) {
                $times[] = $session->worked_at->copy();
            }
        }

        $phone = $this->phoneAddress($conversation);
        if ($phone === null) {
            return $times;
        }

        $outboundCalls = CallSession::query()
            ->where('direction', CallSessionDirection::Outbound)
            ->where(function ($query) use ($phone, $conversation): void {
                $query->where('normalized_to', $phone)
                    ->orWhere('normalized_from', $phone)
                    ->orWhere('to_number', 'like', '%'.$phone.'%');

                $customerId = $this->linkedCustomerId($conversation);
                if ($customerId !== null) {
                    $query->orWhere('customer_id', $customerId);
                }
            })
            ->whereIn('status', [
                CallSessionStatus::Completed->value,
                CallSessionStatus::Answered->value,
            ])
            ->get(['started_at', 'worked_at', 'created_at']);

        foreach ($outboundCalls as $session) {
            $at = $session->worked_at ?? $session->started_at ?? $session->created_at;
            if ($at instanceof CarbonInterface) {
                $times[] = $at->copy();
            }
        }

        return $times;
    }

    /**
     * @return list<CallSession>
     */
    private function inboundCallSessions(Conversation $conversation): array
    {
        $phone = $this->phoneAddress($conversation);
        $customerId = $this->linkedCustomerId($conversation);

        if ($phone === null && $customerId === null) {
            return [];
        }

        return CallSession::query()
            ->where('direction', CallSessionDirection::Inbound)
            ->where(function ($query) use ($phone, $customerId): void {
                if ($customerId !== null) {
                    $query->where('customer_id', $customerId);
                }
                if ($phone !== null) {
                    $query->orWhere('normalized_from', $phone);
                }
            })
            ->get()
            ->all();
    }

    private function phoneAddress(Conversation $conversation): ?string
    {
        if ($conversation->contact_surface !== ConversationContactSurface::Phone) {
            return null;
        }

        return PhoneNumber::normalize((string) $conversation->contact_address)
            ?? (string) $conversation->contact_address;
    }

    private function linkedCustomerId(Conversation $conversation): ?int
    {
        $link = ConversationLink::query()
            ->where('conversation_id', $conversation->id)
            ->where('linkable_type', (new Customer)->getMorphClass())
            ->first();

        return $link !== null ? (int) $link->linkable_id : null;
    }
}
