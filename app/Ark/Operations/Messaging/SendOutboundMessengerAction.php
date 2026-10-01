<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Messaging\Messenger\MetaMessengerMessageTag;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class SendOutboundMessengerAction
{
    /**
     * @return array{message: ConversationMessage, provider_message_id: string}
     */
    public function execute(
        Customer $customer,
        User $actor,
        string $body,
        ?RepairOrder $repairOrder = null,
        ?MetaMessengerMessageTag $messageTag = null,
        ?UploadedFile $attachment = null,
    ): array {
        throw new RuntimeException('Messenger outbound is not configured.');
    }
}
