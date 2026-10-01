<?php

namespace App\Ark\Operations\Conversations;

use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Intake\IntakeEntryQuery;
use App\Ark\Operations\PhoneNumber;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ConversationReplyShowController
{
    public function __invoke(Request $request, Conversation $conversation, CustomerCallContextResolver $resolver): View
    {
        abort_unless(
            $conversation->contact_surface === ConversationContactSurface::Phone,
            404,
        );

        $phone = trim((string) $conversation->contact_address);
        $context = $phone !== '' ? $resolver->resolve($phone) : null;
        $displayPhone = $context?->displayPhone ?? PhoneNumber::display($phone) ?? $phone;

        $messages = $conversation->messages()
            ->with(['attachments', 'participant'])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(40)
            ->get()
            ->reverse()
            ->values();

        $latestInbound = $messages
            ->first(fn (ConversationMessage $message): bool => $message->direction === OperationalCommunicationDirection::Inbound);

        return view('operations.conversations.reply', [
            'conversation' => $conversation,
            'context' => $context,
            'displayPhone' => $displayPhone,
            'messages' => $messages,
            'intakeUrl' => $phone !== ''
                ? route('operations.intake.create', IntakeEntryQuery::fromInboundPhoneMessage(
                    $phone,
                    trim((string) ($latestInbound?->body ?? '')),
                ))
                : null,
            'lookupUrl' => $phone !== ''
                ? route('operations.caller-lookup', ['phone' => $phone])
                : null,
        ]);
    }
}
