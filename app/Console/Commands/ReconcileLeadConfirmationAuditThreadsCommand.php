<?php

namespace App\Console\Commands;

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationStatus;
use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\Leads\LeadConfirmationAuditConversation;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ReconcileLeadConfirmationAuditThreadsCommand extends Command
{
    protected $signature = 'communications:reconcile-lead-audit-threads';

    protected $description = 'Resolve open email audit threads for website leads already handled on the primary text thread';

    public function handle(LeadConfirmationAuditConversation $audit): int
    {
        $resolved = 0;

        Lead::query()
            ->open()
            ->notSpam()
            ->whereNotNull('contact_email')
            ->whereNotNull('conversation_id')
            ->orderBy('id')
            ->lazyById()
            ->each(function (Lead $lead) use ($audit, &$resolved): void {
                $email = Str::lower(trim((string) $lead->contact_email));

                if ($email === '') {
                    return;
                }

                $emailConversation = Conversation::query()
                    ->where('contact_surface', ConversationContactSurface::Email)
                    ->where('contact_address', $email)
                    ->where('status', ConversationStatus::Open)
                    ->first();

                if (! $emailConversation instanceof Conversation || $emailConversation->id === $lead->conversation_id) {
                    return;
                }

                $audit->resolveSiblingAuditsForLead($lead);

                if ($emailConversation->fresh()?->status === ConversationStatus::Resolved) {
                    $resolved++;
                }
            });

        $this->info("Resolved {$resolved} sibling email audit thread(s).");

        return self::SUCCESS;
    }
}
