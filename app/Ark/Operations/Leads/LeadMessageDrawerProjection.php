<?php

namespace App\Ark\Operations\Leads;

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use Illuminate\Support\Collection;

final class LeadMessageDrawerProjection
{
    public function __construct(
        private readonly ShopIntegrationCredentials $integrations,
    ) {}

    /**
     * @param  Collection<int, Lead>  $leads
     * @return array<int, array<string, mixed>>
     */
    public function forLeads(Collection $leads): array
    {
        return $leads
            ->mapWithKeys(fn (Lead $lead): array => [$lead->id => $this->forLead($lead)])
            ->all();
    }

    /**
     * @return array{
     *     lead_id: int,
     *     contact_name: string,
     *     display_phone: string,
     *     source_label: string,
     *     concern: string,
     *     intake_url: string,
     *     reply_page_url: string|null,
     *     messages: Collection<int, ConversationMessage>,
     *     message_count: int,
     *     conversation: Conversation|null,
     *     messages_list_id: string,
     *     can_sms_reply: bool,
     * }
     */
    public function forLead(Lead $lead): array
    {
        $conversation = $lead->relationLoaded('conversation')
            ? $lead->conversation
            : null;

        $messages = $this->resolveMessages($conversation);
        $messageCount = $messages->count();
        $canSmsReply = $conversation !== null
            && $conversation->contact_surface === ConversationContactSurface::Phone
            && $this->integrations->twilioConfigured();

        $replyPageUrl = $canSmsReply
            ? route('operations.conversations.reply', $conversation)
            : null;

        return [
            'lead_id' => $lead->id,
            'contact_name' => filled($lead->contact_name) ? $lead->contact_name : 'Unknown',
            'display_phone' => $lead->display_phone ?: '—',
            'source_label' => $lead->source->label(),
            'concern' => $lead->concern,
            'intake_url' => route('operations.leads.intake', $lead),
            'reply_page_url' => $replyPageUrl,
            'messages' => $messages,
            'message_count' => $messageCount,
            'conversation' => $conversation,
            'messages_list_id' => 'lead-message-drawer-'.$lead->id,
            'can_sms_reply' => $canSmsReply,
        ];
    }

    /**
     * @return Collection<int, ConversationMessage>
     */
    private function resolveMessages(?Conversation $conversation): Collection
    {
        if ($conversation === null) {
            return collect();
        }

        if ($conversation->relationLoaded('messages')) {
            return $conversation->messages
                ->sortBy([
                    ['occurred_at', 'asc'],
                    ['id', 'asc'],
                ])
                ->values()
                ->take(40);
        }

        return $conversation->messages()
            ->with(['attachments', 'participant'])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(40)
            ->get()
            ->reverse()
            ->values();
    }
}
