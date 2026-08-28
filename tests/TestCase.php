<?php

namespace Tests;

use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        ShopSettings::current()->update([
            'learn_training_gate_enabled' => false,
            'telephony_call_flow' => array_merge(
                ShopSettings::defaultTelephonyCallFlow(),
                ['comms_attention_gate_enabled' => false],
            ),
        ]);
    }
}
