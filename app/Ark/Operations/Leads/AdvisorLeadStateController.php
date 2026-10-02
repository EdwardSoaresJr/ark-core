<?php

namespace App\Ark\Operations\Leads;

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationStatus;
use App\Ark\Operations\Conversations\ConversationWork;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdvisorLeadStateController
{
    public function __invoke(Request $request, Lead $lead): RedirectResponse
    {
        $validated = $request->validate([
            'state' => ['required', Rule::enum(LeadState::class)],
            'lost_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $state = LeadState::from($validated['state']);
        $now = now();

        $lead->state = $state;

        if ($state === LeadState::Contacted && $lead->first_contacted_at === null) {
            $lead->first_contacted_at = $now;
        }

        if ($state === LeadState::Scheduled && $lead->scheduled_at === null) {
            $lead->scheduled_at = $now;
        }

        if ($state === LeadState::Arrived && $lead->arrived_at === null) {
            $lead->arrived_at = $now;
        }

        if ($state === LeadState::Converted && $lead->converted_at === null) {
            $lead->converted_at = $now;
        }

        if ($state === LeadState::Lost) {
            $lead->lost_at = $now;
            $lead->lost_reason = filled($validated['lost_reason'] ?? null)
                ? trim((string) $validated['lost_reason'])
                : ($lead->lost_reason ?? 'Closed without RO');
        }

        $lead->save();

        if ($state === LeadState::Spam) {
            $this->closeSpamThread($lead, $request->user());
        }

        if ($lead->first_contacted_at !== null || ! $lead->isOpen()) {
            app(WebsiteLeadInterruptBroadcaster::class)->clearForLead($lead->id);
        }

        return back()->with('status', 'Lead updated - '.$state->label().'.');
    }

    private function closeSpamThread(Lead $lead, mixed $actor): void
    {
        if ($lead->conversation_id !== null) {
            Lead::query()
                ->where('conversation_id', $lead->conversation_id)
                ->whereKeyNot($lead->id)
                ->where('source', LeadSource::Website)
                ->open()
                ->get()
                ->each(function (Lead $sibling): void {
                    $sibling->state = LeadState::Spam;
                    $sibling->save();
                    app(WebsiteLeadInterruptBroadcaster::class)->clearForLead($sibling->id);
                });
        }

        $conversation = $lead->conversation;
        if (
            $conversation instanceof Conversation
            && $actor instanceof User
            && $conversation->status !== ConversationStatus::Resolved
        ) {
            app(ConversationWork::class)->resolve($conversation, $actor);
        }
    }
}
