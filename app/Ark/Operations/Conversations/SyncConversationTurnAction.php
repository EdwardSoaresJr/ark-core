<?php

namespace App\Ark\Operations\Conversations;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionDirection;

/**
 * Persist Thread Turn from communication-event precedence (not last transport write).
 */
final class SyncConversationTurnAction
{
    public function __construct(
        private readonly ConversationTurnPrecedence $precedence,
        private readonly ConversationResolver $resolver,
        private readonly ConversationLinker $linker,
    ) {}

    public function execute(Conversation $conversation): Conversation
    {
        if ($conversation->contact_surface !== ConversationContactSurface::Phone
            && $conversation->contact_surface !== ConversationContactSurface::Messenger) {
            return $conversation;
        }

        $waitingOn = $this->precedence->waitingOn($conversation);
        $wasResolved = $conversation->status === ConversationStatus::Resolved;

        $attributes = [
            'waiting_on' => $waitingOn,
            'posture_changed_at' => now(),
        ];

        if ($waitingOn === ConversationWaitingOn::Shop && $wasResolved) {
            $attributes['status'] = ConversationStatus::Open;
            $attributes['resolved_at'] = null;
            $attributes['reopen_count'] = $conversation->reopen_count + 1;
        }

        $dirty = $conversation->waiting_on !== $waitingOn
            || ($waitingOn === ConversationWaitingOn::Shop && $wasResolved);

        if (! $dirty) {
            return $conversation;
        }

        $conversation->forceFill($attributes)->save();

        return $conversation->refresh();
    }

    public function forCallSession(CallSession $session): ?Conversation
    {
        $phone = $this->phoneForSession($session);

        if ($phone === null || $phone === '') {
            return null;
        }

        // Extension legs / feature codes are not customer relationship Threads.
        if (strlen(preg_replace('/\D+/', '', $phone)) < 10) {
            return null;
        }

        $conversation = $this->resolver->forPhone($phone);

        if ($session->customer_id) {
            $customer = Customer::query()->find($session->customer_id);
            if ($customer instanceof Customer) {
                $this->linker->link($conversation, $customer);
            }
        }

        return $this->execute($conversation);
    }

    private function phoneForSession(CallSession $session): ?string
    {
        if ($session->direction === CallSessionDirection::Outbound) {
            return PhoneNumber::normalize((string) ($session->normalized_to ?? $session->to_number ?? ''))
                ?? PhoneNumber::normalize((string) ($session->normalized_from ?? ''));
        }

        return PhoneNumber::normalize((string) ($session->normalized_from ?? $session->from_number ?? ''));
    }
}
