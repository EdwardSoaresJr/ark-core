<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Operations\Communications\CommunicationsWorkspaceContextBuilder;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Messaging\OutboundAttachmentStore;
use App\Ark\Operations\Messaging\SendOutboundMessageAction;
use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\File;
use RuntimeException;

final class MobileConversationMessageStoreController
{
    public function __invoke(
        Request $request,
        Conversation $conversation,
        MobileStaffAccess $access,
        SendOutboundMessageAction $sender,
        CommunicationsWorkspaceContextBuilder $contextBuilder,
    ): JsonResponse {
        abort_unless($access->canViewConversation($request->user(), $conversation), 403);
        abort_unless($access->canReplyToCustomer($request->user()), 403);

        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:1600'],
            'repair_order_id' => ['nullable', 'integer'],
            'attachment' => [
                'nullable',
                File::types(['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'mov', 'pdf'])
                    ->max(OutboundAttachmentStore::MAX_BYTES / 1024),
            ],
        ]);

        abort_if(
            blank($data['body'] ?? null) && ! $request->hasFile('attachment'),
            422,
            'Message body or attachment is required.',
        );

        $composerContext = $contextBuilder->conversationComposerContext($conversation);
        $customer = $composerContext['customer'];

        abort_if($customer === null, 422, 'No customer is linked to this conversation.');

        $repairOrder = null;

        if (filled($data['repair_order_id'] ?? null)) {
            $repairOrder = RepairOrder::query()
                ->where('repair_order_id', $data['repair_order_id'])
                ->where('customer_id', $customer->id)
                ->first();

            abort_if($repairOrder === null, 422, 'Repair order does not belong to this customer.');
        }

        try {
            $result = $sender->execute(
                customer: $customer,
                actor: $request->user(),
                body: (string) ($data['body'] ?? ''),
                repairOrder: $repairOrder,
                conversation: $conversation,
                attachment: $request->file('attachment'),
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message_id' => $result['message']->id,
        ], 201);
    }
}
