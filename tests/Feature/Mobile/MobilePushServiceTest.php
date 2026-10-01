<?php

use App\Ark\Mobile\MobileDevice;
use App\Ark\Mobile\Push\MobilePushMessage;
use App\Ark\Mobile\Push\MobilePushService;
use App\Ark\Mobile\Push\PushTransport;
use App\Models\User;

test('mobile push service delegates delivery to transport for registered device tokens', function (): void {
    $user = User::factory()->create();

    MobileDevice::query()->create([
        'user_id' => $user->id,
        'device_name' => 'Test Phone',
        'platform' => 'ios',
        'fcm_token' => 'device-token-abc',
        'last_seen_at' => now(),
    ]);

    $transport = new class implements PushTransport
    {
        /** @var list<array{token: string, title: string}> */
        public array $deliveries = [];

        public function isAvailable(): bool
        {
            return true;
        }

        public function send(string $deviceToken, MobilePushMessage $message): bool
        {
            $this->deliveries[] = [
                'token' => $deviceToken,
                'title' => $message->title,
            ];

            return true;
        }
    };

    $service = new MobilePushService($transport);

    $sent = $service->sendToUser($user, new MobilePushMessage(
        title: 'Sarah Johnson',
        body: 'Are my brakes ready?',
        deepLink: 'conversation',
        conversationId: 42,
    ));

    expect($sent)->toBe(1)
        ->and($transport->deliveries)->toHaveCount(1)
        ->and($transport->deliveries[0]['token'])->toBe('device-token-abc')
        ->and($transport->deliveries[0]['title'])->toBe('Sarah Johnson');
});

test('push tone decides delivery loudness, not just color', function (): void {
    $urgent = new MobilePushMessage(title: 'x', body: 'y', tone: 'urgent');
    $waiting = new MobilePushMessage(title: 'x', body: 'y', tone: 'waiting');
    $positive = new MobilePushMessage(title: 'x', body: 'y', tone: 'positive');
    $info = new MobilePushMessage(title: 'x', body: 'y');

    expect($urgent->deliversImmediately())->toBeTrue()
        ->and($urgent->makesSound())->toBeTrue()
        ->and($waiting->deliversImmediately())->toBeTrue()
        ->and($waiting->makesSound())->toBeTrue()
        ->and($positive->deliversImmediately())->toBeFalse()
        ->and($positive->makesSound())->toBeFalse()
        ->and($info->tone)->toBe('info')
        ->and($info->deliversImmediately())->toBeFalse()
        ->and($urgent->dataPayload()['tone'])->toBe('urgent');
});

test('mobile push service returns zero when transport is unavailable', function (): void {
    $user = User::factory()->create();

    $transport = new class implements PushTransport
    {
        public function isAvailable(): bool
        {
            return false;
        }

        public function send(string $deviceToken, MobilePushMessage $message): bool
        {
            return false;
        }
    };

    $service = new MobilePushService($transport);

    expect($service->sendToUser($user, new MobilePushMessage(title: 'Test', body: 'Body')))->toBe(0);
});
