<?php

namespace App\Ark\Operations\Portal;

use App\Ark\Mail\EmailIntent;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Messaging\OutboundSmsTransport;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Platform\Mail\ArkMailClient;
use App\Ark\Platform\Mail\ManagedMailGate;
use Illuminate\Support\Str;
use RuntimeException;

final class PortalAccessChallengeSender
{
    public function __construct(
        private readonly OutboundSmsTransport $transport,
        private readonly ArkMailClient $mail,
    ) {}

    public function send(PortalAccessChallenge $challenge, string $plainCode, Customer $customer): void
    {
        $shopName = trim((string) (ShopSettings::current()->shop_name ?? ''));

        if ($shopName === '') {
            $shopName = (string) config('app.name', 'Your shop');
        }

        if ($challenge->channel === PortalAccessChannel::Sms) {
            $this->transport->send(
                $challenge->destination,
                sprintf('%s: Your sign-in code is %s. It expires in 10 minutes.', $shopName, $plainCode),
            );

            return;
        }

        if (! ManagedMailGate::platformSend()) {
            throw new RuntimeException("Email isn't configured yet.");
        }

        $variables = [
            'shop_name' => $shopName,
            'code' => $plainCode,
        ];
        $firstName = trim((string) $customer->first_name);
        if ($firstName !== '') {
            $variables['customer_name'] = $firstName;
        }

        $result = $this->mail->sendTransactional(EmailIntent::payload(
            operation: 'portal.sign_in',
            to: $challenge->destination,
            variables: $variables,
            idempotencyKey: 'portal-sign-in-'.Str::uuid(),
        ));

        if (($result['ok'] ?? false) !== true) {
            throw new RuntimeException(
                is_string($result['message'] ?? null) ? $result['message'] : 'Email could not be sent.',
            );
        }
    }
}
