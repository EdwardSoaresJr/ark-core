<?php

namespace Tests;

use App\Ark\Install\InstallationState;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->app->bind(
            \App\Ark\Operations\Documents\PdfRenderer::class,
            \Tests\Support\TestingFakePdfRenderer::class,
        );
        \App\Ark\Operations\Inspections\DefaultInspectionTemplateCatalog::forgetSeededCache();

        // Existing suites assume a running shop — not the first-run wizard.
        InstallationState::markInstalled();

        try {
            if (! Schema::hasTable('shop_settings')) {
                return;
            }
        } catch (\Throwable) {
            return;
        }

        ShopSettings::forgetCurrent();

        $settings = ShopSettings::current();
        $updates = [
            'learn_training_gate_enabled' => false,
            'telephony_call_flow' => array_merge(
                ShopSettings::defaultTelephonyCallFlow(),
                ['comms_attention_gate_enabled' => false],
            ),
        ];

        if (! filled($settings->shop_timezone)) {
            $updates['shop_timezone'] = ShopSettings::INSTALL_DEFAULT_TIMEZONE;
        }

        $settings->update($updates);
        ShopDisplayTimezone::apply();
    }
}
