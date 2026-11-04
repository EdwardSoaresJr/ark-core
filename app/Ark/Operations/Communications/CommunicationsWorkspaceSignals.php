<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Customers\Customer;

/**
 * What the Communications workspace shows beside Core's own conversation records: where each outbound message got
 * to, whether the customer may be texted, and what makes an open workspace refresh. Core answers from its own
 * records (CoreCommunicationsWorkspaceSignals); a host application can bind its own to answer from richer sources,
 * such as a provider's delivery callbacks or a consent ledger, without the workspace changing.
 */
interface CommunicationsWorkspaceSignals
{
    /**
     * The provider's status for each outbound message that has one, keyed by message id. Statuses are the provider's
     * own words (queued, sent, delivered, read, undelivered, failed, ...), lower case.
     *
     * @param  iterable<ConversationMessage>  $messages
     * @return array<int, array{status: string, error_code?: string|null, at?: \DateTimeInterface|null}>
     */
    public function deliveries(iterable $messages): array;

    /**
     * Whether the shop may text this conversation's contact now, or null when nothing is known.
     *
     * @return array{texting: 'allowed'|'opted_out'|'unknown', source?: string|null, quiet_hours_until?: \DateTimeInterface|null}|null
     */
    public function contactPermission(Conversation $conversation, ?Customer $customer): ?array;

    /**
     * Where the composer's Email mode posts a reply (fields: body, idempotency_key, return_to), or null when this shop
     * cannot email the conversation's contact from the workspace.
     */
    public function emailReplyUrl(Conversation $conversation): ?string;

    /**
     * Changes when any signal for this conversation (or, without one, for the workspace) changes, so an open workspace
     * refreshes. Empty when the signals only change with Core's own records, which the workspace already watches.
     */
    public function signature(?int $conversationId): string;

    /**
     * Broadcast event names on the shop's operations.conversations channel that should refresh an open workspace,
     * besides Core's own.
     *
     * @return list<string>
     */
    public function liveEvents(): array;
}
