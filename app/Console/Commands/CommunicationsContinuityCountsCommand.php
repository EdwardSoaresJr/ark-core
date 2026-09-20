<?php

namespace App\Console\Commands;

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationLink;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationMessageAttachment;
use App\Ark\Operations\Customers\Customer;
use Illuminate\Console\Command;

/**
 * Gate 1 — print Core continuity counts before reconcile / Twilio flip.
 */
final class CommunicationsContinuityCountsCommand extends Command
{
    protected $signature = 'ark:communications:continuity-counts';

    protected $description = 'Print Core conversation continuity counts for Track C Gate 1';

    public function handle(): int
    {
        $conversations = Conversation::query()
            ->where('contact_surface', ConversationContactSurface::Phone->value)
            ->count();

        $messages = ConversationMessage::query()
            ->whereHas('conversation', function ($q): void {
                $q->where('contact_surface', ConversationContactSurface::Phone->value);
            })
            ->count();

        $attachments = ConversationMessageAttachment::query()
            ->whereHas('message.conversation', function ($q): void {
                $q->where('contact_surface', ConversationContactSurface::Phone->value);
            })
            ->count();

        $customerMorph = (new Customer)->getMorphClass();
        $linked = ConversationLink::query()
            ->where('linkable_type', $customerMorph)
            ->whereHas('conversation', function ($q): void {
                $q->where('contact_surface', ConversationContactSurface::Phone->value);
            })
            ->distinct('conversation_id')
            ->count('conversation_id');

        $latest = ConversationMessage::query()
            ->whereHas('conversation', function ($q): void {
                $q->where('contact_surface', ConversationContactSurface::Phone->value);
            })
            ->orderByDesc('occurred_at')
            ->value('occurred_at');

        $this->table(
            ['Metric', 'Core'],
            [
                ['conversations (phone)', (string) $conversations],
                ['messages', (string) $messages],
                ['attachments', (string) $attachments],
                ['customer-linked conversations', (string) $linked],
                ['latest message occurred_at', $latest ? (string) $latest : '—'],
            ],
        );

        return self::SUCCESS;
    }
}
