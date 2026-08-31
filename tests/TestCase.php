<?php

namespace Tests;

use App\Ark\Install\InstallationState;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        // Existing suites assume a running shop — not the first-run wizard.
        InstallationState::markInstalled();

        if (Schema::hasTable('shop_settings')) {
            ShopSettings::current()->persistTrusted([
                'learn_training_gate_enabled' => false,
                'telephony_call_flow' => array_merge(
                    ShopSettings::defaultTelephonyCallFlow(),
                    ['comms_attention_gate_enabled' => false],
                ),
            ]);
        }
    }
}
