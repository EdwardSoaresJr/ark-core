<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Conversations\ConversationWork;
use App\Ark\Operations\Customers\CustomerSmsSendEligibility;
use App\Ark\Operations\Messaging\OutboundAttachmentStore;
use App\Ark\Operations\Messaging\OutboundComposerFiles;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use App\Ark\Platform\Communications\ArkCommunicationsClient;
use App\Ark\Platform\Communications\ManagedCommunicationsGate;
use App\Ark\Platform\Communications\PlatformConversationCustomerResolver;
use App\Ark\Platform\Communications\PlatformMessageMedia;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\File;
use RuntimeException;

/**
 * Platform-backed inbox send - no Core ConversationMessage authority writes.
 */
final class SendPlatformConversationMessageController
{
    public function __invoke(
        Request $request,
        string $platformConversation,
        ArkCommunicationsClient $client,
    ): JsonResponse {
        abort_unless(ManagedCommunicationsGate::platformSend(), 404);

        $validated = $request->validate([
            'body' => ['nullable', 'string', 'max:1600'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
            'to_phone' => ['nullable', 'string', 'max:32'],
            'attachment' => [
                'nullable',
                File::types(['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'mov', 'pdf'])
                    ->max(OutboundAttachmentStore::MAX_BYTES / 1024),
            ],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => [
                File::types(['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'mov', 'pdf'])
                    ->max(OutboundAttachmentStore::MAX_BYTES / 1024),
            ],
        ]);

        $files = OutboundComposerFiles::from($request);
        $body = trim((string) ($validated['body'] ?? ''));
        if ($body === '' && $files === []) {
            return response()->json(['message' => 'Enter a message or attach a file.'], 422);
        }

        $idempotencyKey = trim((string) ($validated['idempotency_key'] ?? ''));
        if ($idempotencyKey === '') {
            $idempotencyKey = 'platform-inbox:'.(string) Str::uuid();
        }

        $cacheKey = 'platform_inbox_send:'.hash('sha256', $platformConversation.'|'.$idempotencyKey);
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return response()->json($cached);
        }

        $shown = $client->showConversation($platformConversation);
        if (! ($shown['ok'] ?? false)) {
            return response()->json([
                'message' => (string) ($shown['message'] ?? 'Conversation could not be loaded.'),
            ], 422);
        }

        $contact = trim((string) (
            $validated['to_phone']
            ?? ($shown['conversation']['contact_address'] ?? '')
        ));
        $normalized = PhoneNumber::normalize($contact) ?? $contact;
        if ($normalized === '') {
            return response()->json(['message' => 'Conversation does not have a phone number.'], 422);
        }

        /** @var User $actor */
        $actor = $request->user();
        $customerId = isset($shown['conversation']['core_customer_id'])
            ? (int) $shown['conversation']['core_customer_id']
            : null;
        $customer = app(PlatformConversationCustomerResolver::class)
            ->resolveOne($customerId, $contact);

        if ($customer) {
            $customerId = (int) $customer->id;
            $block = CustomerSmsSendEligibility::for(
                $customer,
                app(ShopIntegrationCredentials::class),
            )->consentBlockReason();
            if ($block !== null) {
                return response()->json(['message' => $block], 422);
            }
        }

        try {
            $stored = $files === [] ? [] : app(OutboundAttachmentStore::class)->storeMany($files);
            $result = $client->sendConversationMessage(
                toPhone: $normalized,
                body: $body,
                idempotencyKey: $idempotencyKey,
                domainObjectType: $customerId ? 'customer' : 'conversation',
                domainObjectId: $customerId ? (string) $customerId : $platformConversation,
                mediaUrls: array_map(
                    static fn (array $file): string => (string) $file['public_url'],
                    $stored,
                ),
                coreActorUserId: (int) $actor->id,
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        if (! ($result['ok'] ?? false)) {
            return response()->json([
                'message' => (string) ($result['message'] ?? 'Message could not be sent.'),
            ], 422);
        }

        $occurredAt = now()->utc()->toIso8601String();
        $presented = PlatformMessageMedia::present($result['attachments'] ?? []);
        $payload = [
            'platform_authoritative' => true,
            'message_id' => null,
            'provider_message_sid' => $result['provider_message_id'] ?? null,
            'platform_message' => [
                'public_id' => $result['comm_message_public_id'] ?? $result['message_id'] ?? null,
                'direction' => 'outbound',
                'direction_label' => 'Sent',
                'channel_label' => ($presented !== [] ? 'MMS' : 'SMS').' · '.(string) ($result['status'] ?? 'queued'),
                'body' => $body,
                'delivery_status' => (string) ($result['status'] ?? 'queued'),
                'occurred_at' => $occurredAt,
                'occurred_at_label' => now()->timezone(config('app.timezone'))->format('M j · g:i A'),
                'attachments' => $presented,
            ],
        ];

        $work = app(ConversationWork::class);
        $work->recordOutboundOwner(
            $work->ensureForPhone($normalized, $customer),
            $actor,
        );

        Cache::put($cacheKey, $payload, now()->addMinutes(15));

        return response()->json($payload);
    }
}
