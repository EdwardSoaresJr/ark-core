<?php

namespace Database\Seeders;

use App\Ark\Communications\Provisioning\CommunicationDeviceModel;
use App\Ark\Communications\Provisioning\EndpointProvisionBuilder;
use Illuminate\Database\Seeder;

class CommunicationDeviceModelSeeder extends Seeder
{
    public function run(): void
    {
        $models = [
            [
                'slug' => 'vvx350',
                'manufacturer' => 'poly',
                'label' => 'Poly VVX 350',
                'minimum_firmware' => '6.4.0',
                'recommended_firmware' => '6.5.0',
                'latest_firmware' => '6.5.0',
                'builder' => EndpointProvisionBuilder::Poly,
                'enabled' => true,
            ],
            [
                'slug' => 'vvx450',
                'manufacturer' => 'poly',
                'label' => 'Poly VVX 450',
                'minimum_firmware' => '6.4.0',
                'recommended_firmware' => '6.5.0',
                'latest_firmware' => '6.5.0',
                'builder' => EndpointProvisionBuilder::Poly,
                'enabled' => true,
            ],
        ];

        foreach ($models as $model) {
            CommunicationDeviceModel::query()->updateOrCreate(
                ['slug' => $model['slug']],
                $model,
            );
        }
    }
}
