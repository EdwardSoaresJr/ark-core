<?php

namespace Database\Seeders;

use App\Ark\Operations\Communications\InternalChannel;
use Illuminate\Database\Seeder;

class InternalChannelSeeder extends Seeder
{
    /**
     * @return list<array{name: string, slug: string, description: string|null}>
     */
    public static function defaultChannels(): array
    {
        return [
            ['name' => 'General', 'slug' => 'general', 'description' => 'Shop-wide coordination and announcements.'],
            ['name' => 'Service Advisors', 'slug' => 'service-advisors', 'description' => 'Advisor floor rhythm and handoffs.'],
            ['name' => 'Technicians', 'slug' => 'technicians', 'description' => 'Production coordination and bay updates.'],
            ['name' => 'Parts', 'slug' => 'parts', 'description' => 'Parts desk and procurement coordination.'],
            ['name' => 'Management', 'slug' => 'management', 'description' => 'Owner and management updates.'],
        ];
    }

    public function run(): void
    {
        foreach (self::defaultChannels() as $channel) {
            InternalChannel::query()->updateOrCreate(
                ['slug' => $channel['slug']],
                [
                    'name' => $channel['name'],
                    'description' => $channel['description'],
                    'is_private' => false,
                ],
            );
        }
    }
}
