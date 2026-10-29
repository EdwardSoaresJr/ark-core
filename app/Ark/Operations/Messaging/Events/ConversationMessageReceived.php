<?php

namespace App\Ark\Operations\Messaging\Events;

use App\Ark\Operations\Messaging\ConversationBroadcast;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationMessageReceived implements ShouldBroadcast, ShouldRescue
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /** Sent by a queue worker once the change is committed, so the browser's refresh sees it. */
    public bool $afterCommit = true;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public array $payload) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel(ConversationBroadcast::channelName()),
        ];
    }

    public function broadcastAs(): string
    {
        return 'conversation.message.received';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
