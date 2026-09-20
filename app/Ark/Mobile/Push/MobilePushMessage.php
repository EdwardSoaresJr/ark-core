<?php

namespace App\Ark\Mobile\Push;

final class MobilePushMessage
{
    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly string $deepLink = 'attention',
        public readonly ?int $repairOrderId = null,
        public readonly ?int $conversationId = null,
        public readonly ?int $callSessionId = null,
        /**
         * Continuity tone from the observation vocabulary: urgent · waiting ·
         * positive · info. Tone decides delivery priority and sound — urgent and
         * waiting chime on mobile; positive/info arrive quietly.
         */
        public readonly string $tone = 'info',
    ) {}

    /**
     * High-priority delivery wakes the device immediately. Urgent and waiting
     * are time-sensitive shop work; positive/info can ride normal delivery.
     */
    public function deliversImmediately(): bool
    {
        return $this->tone === 'urgent' || $this->tone === 'waiting';
    }

    /**
     * Urgent and waiting continuity packets play the default notification sound.
     * Waiting covers inbound customer texts; urgent covers inbound call ring.
     */
    public function makesSound(): bool
    {
        return $this->tone === 'urgent' || $this->tone === 'waiting';
    }

    /**
     * @return array<string, string>
     */
    public function dataPayload(): array
    {
        $data = [
            'deep_link' => $this->deepLink,
            'tone' => $this->tone,
        ];

        if ($this->repairOrderId !== null) {
            $data['repair_order_id'] = (string) $this->repairOrderId;
        }

        if ($this->conversationId !== null) {
            $data['conversation_id'] = (string) $this->conversationId;
        }

        if ($this->callSessionId !== null) {
            $data['call_session_id'] = (string) $this->callSessionId;
        }

        return $data;
    }
}
