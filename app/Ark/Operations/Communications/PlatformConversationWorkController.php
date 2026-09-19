<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Conversations\ConversationWork;
use App\Ark\Operations\Conversations\StaleConversationWorkException;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Platform\Communications\ArkCommunicationsClient;
use App\Ark\Platform\Communications\ManagedCommunicationsGate;
use App\Ark\Platform\Communications\PlatformConversationCustomerResolver;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Assign / follow-up / resolve for Platform inbox threads.
 * Core Conversation owns the work. Platform owns the messages.
 */
final class PlatformConversationWorkController
{
    public function __invoke(
        Request $request,
        string $platformConversation,
        ArkCommunicationsClient $client,
        ConversationWork $work,
        PlatformConversationCustomerResolver $customers,
    ): RedirectResponse {
        abort_unless(ManagedCommunicationsGate::platformInbox(), 404);

        $data = $request->validate([
            'action' => ['required', Rule::in(['assign', 'follow_up', 'wait', 'resolve', 'reopen'])],
            'assign_to' => ['nullable', Rule::in(['me', 'user', 'unassign'])],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'due_at' => ['nullable', 'date'],
            'filter' => ['nullable', Rule::in(['needs', 'waiting', 'resolved', 'all'])],
            'posture_changed_at' => ['nullable', 'string', 'max:64'],
        ]);

        $shown = $client->showConversation($platformConversation);
        abort_unless((bool) ($shown['ok'] ?? false), 404);

        $contact = (string) ($shown['conversation']['contact_address'] ?? '');
        $normalized = PhoneNumber::normalize($contact) ?? $contact;
        abort_if($normalized === '', 422, 'Conversation does not have a phone number.');

        $statedCustomerId = isset($shown['conversation']['core_customer_id'])
            ? (int) $shown['conversation']['core_customer_id']
            : null;
        $customer = $customers->resolveOne($statedCustomerId, $contact);
        $conversation = $work->ensureForPhone($normalized, $customer);
        $actor = $request->user();

        try {
            $work->assertCurrent($conversation, $data['posture_changed_at'] ?? null);
        } catch (StaleConversationWorkException $exception) {
            return redirect()
                ->route('operations.communications.inbox', [
                    'filter' => $data['filter'] ?? 'needs',
                    'platform_conversation' => $platformConversation,
                ])
                ->with('error', $exception->getMessage());
        }

        match ($data['action']) {
            'assign' => $work->assign($conversation, $this->owner($data, $actor)),
            'follow_up' => $work->followUp(
                $conversation,
                $actor,
                filled($data['due_at'] ?? null)
                    ? ShopDisplayTimezone::parseLocal((string) $data['due_at'])
                    : ShopDisplayTimezone::now()->addDay()->setTime(8, 0),
            ),
            'wait' => $work->wait($conversation, $actor),
            'resolve' => $work->resolve($conversation, $actor),
            'reopen' => $work->markNeedsAttention($conversation),
        };

        $lane = $work->lane($conversation->fresh());
        $status = match ($data['action']) {
            'assign' => 'Assigned.',
            'follow_up' => 'Follow-up scheduled.',
            'wait' => 'Marked waiting.',
            'resolve' => 'Conversation resolved.',
            'reopen' => 'Conversation reopened.',
        };

        $filter = match ($lane) {
            'waiting' => 'waiting',
            'resolved' => 'resolved',
            default => 'needs',
        };

        return redirect()
            ->route('operations.communications.inbox', [
                'filter' => $filter,
                'platform_conversation' => $platformConversation,
            ])
            ->with('status', $status);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function owner(array $data, User $actor): ?User
    {
        return match ((string) ($data['assign_to'] ?? 'me')) {
            'unassign' => null,
            'user' => User::query()->findOrFail((int) $data['user_id']),
            default => $actor,
        };
    }
}
