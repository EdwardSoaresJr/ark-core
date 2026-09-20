<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Communications\Events\CommsInterruptReceived;
use App\Ark\Operations\Messaging\ConversationBroadcast;

final class CommsInterruptBroadcast
{
    public static function enabled(): bool
    {
        return ConversationBroadcast::enabled();
    }

    public static function channelName(): string
    {
        return 'operations.comms-interrupts';
    }

    /**
     * @param  array<string, mixed>  $interrupt
     */
    public function show(string $kind, array $interrupt): void
    {
        if (! self::enabled()) {
            return;
        }

        $this->dispatch('show', $kind, $interrupt);
    }

    /**
     * @param  array<string, mixed>  $interrupt
     */
    public function update(string $kind, array $interrupt): void
    {
        if (! self::enabled()) {
            return;
        }

        $this->dispatch('update', $kind, $interrupt);
    }

    public function clear(string $kind, string $interruptKey): void
    {
        if (! self::enabled()) {
            return;
        }

        CommsInterruptReceived::dispatch([
            'kind' => $kind,
            'action' => 'clear',
            'interrupt_key' => $interruptKey,
            'interrupt' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $interrupt
     */
    private function dispatch(string $action, string $kind, array $interrupt): void
    {
        CommsInterruptReceived::dispatch([
            'kind' => $kind,
            'action' => $action,
            'interrupt_key' => self::interruptKey($kind, $interrupt),
            'interrupt' => $interrupt,
        ]);
    }

    /**
     * @param  array<string, mixed>  $interrupt
     */
    public static function interruptKey(string $kind, array $interrupt): string
    {
        return match ($kind) {
            'call' => 'call:'.((int) ($interrupt['call_session_id'] ?? 0)),
            'sms', 'mms' => 'message:'.((int) ($interrupt['conversation_message_id'] ?? 0)),
            'portal' => 'portal:'.((string) ($interrupt['portal_interrupt_key'] ?? '0')),
            'website_lead' => 'lead:'.((int) ($interrupt['lead_id'] ?? 0)),
            default => $kind.':'.((string) ($interrupt['id'] ?? '0')),
        };
    }

}
