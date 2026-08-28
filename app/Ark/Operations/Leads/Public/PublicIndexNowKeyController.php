<?php

namespace App\Ark\Operations\Leads\Public;

use App\Ark\Growth\Settings\GrowthIntegrationSettings;
use Illuminate\Http\Response;

final class PublicIndexNowKeyController
{
    public function __invoke(string $token): Response
    {
        $key = GrowthIntegrationSettings::current()->indexNowKey();

        if ($key === null || $token !== $key) {
            abort(404);
        }

        return response($key, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
