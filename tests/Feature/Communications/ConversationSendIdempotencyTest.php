<?php

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Messaging\OutboundSmsResult;
use App\Ark\Operations\Messaging\OutboundSmsTransport;
use App\Ark\Operations\Messaging\SendOutboundContactMessageAction;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);

    $this->sent = new class implements OutboundSmsTransport
    {
        /** @var list<list<array{type: string, id: string}>> */
        public array $contexts = [];

        public function isConfigured(): bool
        {
            return true;
        }

        public function send(string $toPhone, string $body, array $mediaUrls = [], array $context = []): OutboundSmsResult
        {
            $this->contexts[] = $context;

            return new OutboundSmsResult('SM'.str_pad((string) count($this->contexts), 32, '0', STR_PAD_LEFT), 'queued');
        }
    };

    app()->instance(OutboundSmsTransport::class, $this->sent);
});

function idempotencyConversation(): Conversation
{
    return Conversation::query()->create([
        'contact_surface' => ConversationContactSurface::Phone,
        'contact_address' => '7195558090',
    ]);
}

test('a conversation send hands its idempotency key to the transport so a retry is not sent twice', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    app(SendOutboundContactMessageAction::class)->execute(
        idempotencyConversation(), $advisor, 'Your car is ready.', idempotencyKey: 'compose-123',
    );

    expect($this->sent->contexts)->toBe([[['type' => 'idempotency_key', 'id' => 'conversation-send:compose-123']]]);
});

test('a conversation send without a key passes no idempotency context', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    app(SendOutboundContactMessageAction::class)->execute(idempotencyConversation(), $advisor, 'Your car is ready.');

    expect($this->sent->contexts)->toBe([[]]);
});
