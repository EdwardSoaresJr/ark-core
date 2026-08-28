<?php

namespace App\Ark\Operations\Portal;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Messaging\TwilioMessagingSender;
use App\Ark\Operations\Settings\ShopSettings;
use App\Mail\PortalAccessCodeMail;
use Illuminate\Support\Facades\Mail;

final class PortalAccessChallengeSender
{
    public function __construct(
        private readonly TwilioMessagingSender $twilio,
    ) {}

    public function send(PortalAccessChallenge $challenge, string $plainCode, Customer $customer): void
    {
        $shopName = trim((string) (ShopSettings::current()->shop_name ?? ''));

        if ($shopName === '') {
            $shopName = (string) config('app.name', 'Your shop');
        }

        if ($challenge->channel === PortalAccessChannel::Sms) {
            $this->twilio->send(
                $challenge->destination,
                sprintf('%s: Your sign-in code is %s. It expires in 10 minutes.', $shopName, $plainCode),
            );

            return;
        }

        Mail::to($challenge->destination)->send(new PortalAccessCodeMail(
            shopName: $shopName,
            plainCode: $plainCode,
            customerFirstName: trim($customer->first_name),
        ));
    }
}
