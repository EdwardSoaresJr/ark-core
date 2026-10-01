<?php

namespace Database\Seeders;

use App\Ark\Operations\Telephony\TelephonyEndpoint;
use App\Ark\Operations\Telephony\TelephonyEndpointType;
use Illuminate\Database\Seeder;

class TelephonyEndpointSeeder extends Seeder
{
    /**
     * Default ring endpoint for demo shops when none exist after migrate/seed.
     * Edit under Settings → Communications → Ring group.
     */
    private const DEFAULT_RING_NAME = 'Primary cell';

    private const DEFAULT_RING_DESTINATION = '+17195550199';

    public function run(): void
    {
        if (TelephonyEndpoint::query()->exists()) {
            return;
        }

        TelephonyEndpoint::query()->create([
            'name' => self::DEFAULT_RING_NAME,
            'type' => TelephonyEndpointType::Cell,
            'destination' => self::DEFAULT_RING_DESTINATION,
            'enabled' => true,
            'position' => 0,
        ]);
    }
}
