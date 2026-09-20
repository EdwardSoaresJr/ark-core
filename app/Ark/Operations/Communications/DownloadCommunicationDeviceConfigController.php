<?php

namespace App\Ark\Operations\Communications;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class DownloadCommunicationDeviceConfigController
{
    public function __invoke(CommunicationDevice $communicationDevice): Response|HttpResponse
    {
        abort_unless($communicationDevice->hasProvisionConfig(), 404);

        $contents = Storage::disk('local')->get($communicationDevice->provisionConfigPath());

        return response($contents, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$communicationDevice->provisionConfigFilename().'"',
        ]);
    }
}
