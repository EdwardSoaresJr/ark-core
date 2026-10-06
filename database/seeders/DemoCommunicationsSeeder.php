<?php

namespace Database\Seeders;

use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\TelephonyEndpoint;
use App\Ark\Operations\Telephony\TelephonyProviderType;
use App\Ark\Runtime\DemoInstall;
use Illuminate\Database\Seeder;

class DemoCommunicationsSeeder extends Seeder
{
    public function run(): void
    {
        if (! $this->applies()) {
            return;
        }

        $settings = ShopSettings::current();
        $settings->forceFill([
            'telephony_enabled' => false,
            'telephony_inbound_number' => null,
            'telephony_provider' => TelephonyProviderType::None->value,
            'square_email_pay_enabled' => false,
            'square_portal_pay_enabled' => false,
            'ark_mail_status' => null,
            'ark_mail_tenant_public_id' => null,
            'ark_mail_from_email' => null,
            'ark_mail_service_url' => null,
            'ark_mail_credential' => null,
            'cloud_status' => null,
            'cloud_base_url' => null,
            'cloud_shop_public_id' => null,
            'cloud_credential' => null,
            'platform_status' => null,
            'platform_base_url' => null,
            'platform_shop_public_id' => null,
            'platform_credential' => null,
            'postmark_reply_to' => null,
            'postmark_reply_to_name' => null,
        ])->save();
        ShopSettings::forgetCurrent();

        TelephonyEndpoint::query()->update(['enabled' => false]);
    }

    private function applies(): bool
    {
        return DemoInstall::isDemo();
    }
}
